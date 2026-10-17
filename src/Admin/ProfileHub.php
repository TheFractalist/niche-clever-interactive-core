<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;

use NicheCleverInteractive\Core\Api\V1\Value\ProfileEditV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileSectionV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileSummaryV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceContextV1;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\ModelCatalogue;
use NicheCleverInteractive\Core\Configuration\ModelSettings;
use NicheCleverInteractive\Core\Configuration\PanelAppearance;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;
use NicheCleverInteractive\Core\Security\AdminActionGuard;
use NicheCleverInteractive\Core\Support\Translator;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Core's own markup uses the escape wrappers below; an extension section body is administrator-only output from a registered extension, which escapes its own content.
// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- AdminActionGuard verifies capability and nonce before any POST value is read; GET values only choose a view and are matched against fixed patterns.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Values are unslashed and validated by ProfileEditV1 before anything is stored.
/**
 * The Profiles page and the workspace of each extension profile. It exists only while an extension
 * has registered a profile editor; otherwise Core's single-profile Settings page is unchanged. Core
 * draws every page and validates every value; the extension only stores profiles and supplies
 * sections. The default profile stays on SettingsPage, which asks this class for its extra tabs,
 * sections and switcher.
 */
final class ProfileHub
{
    public const SAVE_ACTION = 'ncli_save_profile';
    public const CREATE_ACTION = 'ncli_create_profile';
    public const DELETE_ACTION = 'ncli_delete_profile';
    private const TEST_ACTION = 'ncli_test_profile';
    private const TEST_NONCE_ACTION = 'ncli_ext_profiles';
    private const TABS = [
        'setup' => 'Setup', 'profile' => 'Profile', 'ai-corpus' => 'AI & Corpus', 'conversation' => 'Conversation',
        'questions' => 'Questions & sharing', 'appearance-limits' => 'Appearance & Limits', 'placement' => 'Placement',
        'cache' => 'Cached answers', 'diagnostics' => 'Diagnostics',
    ];
    private const FORM_TABS = ['setup', 'profile', 'ai-corpus', 'conversation', 'appearance-limits'];
    private const STATES = [
        Readiness::READY => ['ready', 'Ready (current test)'],
        Readiness::STALE_CONNECTION_TEST => ['warn', 'Needs a new test: settings changed'],
        Readiness::MISSING_CONNECTION_TEST => ['warn', 'Not tested yet'],
        Readiness::MISSING_VECTOR_STORE => ['warn', 'Needs a vector store'],
        Readiness::MISSING_CORPUS_VERSION => ['warn', 'Needs a corpus version'],
        Readiness::MISSING_SECRET => ['warn', 'Needs the server API key'],
        Readiness::MISSING_SCOPE_SECRET => ['warn', 'Needs the visitor-scope secret'],
        Readiness::DISABLED => ['off', 'Disabled'],
        'unavailable' => ['warn', 'Unavailable: check its settings'],
    ];

    private ProfileDirectory $profiles;
    private AdminActionGuard $guard;
    /** @var callable(): CoreSettings */
    private $settings;
    /** @var callable(array<string, string>): void */
    private $redirect;

    /**
     * @param callable(): CoreSettings|null $settings
     * @param callable(array<string, string>): void|null $redirect Receives the query for the Core page to return to.
     */
    public function __construct(ProfileDirectory $profiles, ?AdminActionGuard $guard = null, ?callable $settings = null, ?callable $redirect = null)
    {
        $this->profiles = $profiles;
        $this->guard = $guard ?? AdminActionGuard::forWordPress();
        $this->settings = $settings ?? static function (): CoreSettings {
            $stored = function_exists('get_option') ? get_option(CoreSettings::OPTION_NAME, CoreSettings::defaults()) : CoreSettings::defaults();

            return CoreSettings::fromArray(is_array($stored) ? $stored : CoreSettings::defaults());
        };
        $this->redirect = $redirect ?? [$this, 'redirectTo'];
    }

    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('admin_post_' . self::SAVE_ACTION, [$this, 'save']);
        add_action('admin_post_' . self::CREATE_ACTION, [$this, 'create']);
        add_action('admin_post_' . self::DELETE_ACTION, [$this, 'delete']);
        // WordPress refuses an unregistered admin page while it builds the menu, before admin_init, so redirect from here.
        add_action('admin_menu', [$this, 'redirectRetiredPages'], 99);
    }

    /** Whether an extension profile editor is registered: the hub exists only then. */
    public function active(): bool
    {
        return $this->profiles->editor() !== null;
    }

    // ---- routing -----------------------------------------------------------------------------------------------

    /**
     * Draws the Profiles page, the add-profile page or a named profile's workspace and returns true,
     * or returns false when the request is for the default profile's workspace, which SettingsPage draws.
     */
    public function render(): bool
    {
        $profile = $this->query('profile');
        $view = $this->query('view');
        if ($profile === CoreSettings::DEFAULT_PROFILE_ID || ($profile === '' && $this->query('tab') !== '' && $view === '')) {
            return false;
        }

        if ($view === 'new') {
            $this->renderNew();
        } elseif ($profile !== '' && $this->validId($profile)) {
            $this->renderProfile($profile, $this->tab($profile));
        } else {
            $this->renderLanding();
        }

        return true;
    }

    // ---- shared chrome (also used by SettingsPage for the default profile) ----------------------------------------------

    /** A plain title strip: the page name on the left, a short label on the right. */
    public function titleBar(string $left, string $right): void
    {
        echo '<div class="ncli-admin__titlebar"><span class="ncli-admin__titlebar-name">' . $this->html($left) . '</span><span>' . $this->html($right) . '</span></div>';
    }

    /**
     * The navy title strip of a profile workspace: the profile's name on the left; on the right the
     * profile switcher, the link to every profile and the words "Profile workspace", in white.
     */
    public function header(string $profileId, string $title): void
    {
        $tab = $this->query('tab');
        echo '<div class="ncli-admin__titlebar"><span class="ncli-admin__titlebar-name">' . $this->html($title) . '</span><div class="ncli-admin__titlebar-tools">';
        echo '<form class="ncli-admin__titlebar-switch" method="get" action="' . $this->attr($this->adminUrl([])) . '"><input type="hidden" name="page" value="' . $this->attr(SettingsPage::PAGE_SLUG) . '">';
        if ($tab !== '' && array_key_exists($tab, self::TABS)) {
            echo '<input type="hidden" name="tab" value="' . $this->attr($tab) . '">';
        }
        echo '<label class="screen-reader-text" for="ncli-profile-switch">' . $this->html($this->t('Profile')) . '</label><select id="ncli-profile-switch" name="profile">';
        echo '<option value="default"' . ($profileId === CoreSettings::DEFAULT_PROFILE_ID ? ' selected' : '') . '>' . $this->html($this->t('Default profile')) . '</option>';
        foreach ($this->profiles->summaries() as $summary) {
            $id = $summary->id()->value();
            echo '<option value="' . $this->attr($id) . '"' . ($id === $profileId ? ' selected' : '') . '>' . $this->html($summary->label()) . '</option>';
        }
        echo '</select> <button type="submit" class="button">' . $this->html($this->t('Switch profile')) . '</button></form>';
        echo '<a class="button" href="' . $this->attr($this->adminUrl(['view' => 'profiles'])) . '">' . $this->html($this->t('All profiles')) . '</a>';
        echo '<span class="ncli-admin__titlebar-label">' . $this->html($this->t('Profile workspace')) . '</span></div></div>';
    }

    /**
     * Tab labels for a profile, in order: Core's own tabs with the extension's Questions & sharing
     * and Cached answers tabs added only when it supplies sections for them.
     *
     * @param array<string, string> $coreTabs Core's tabs for this profile (id => label).
     * @return array<string, string>
     */
    public function tabs(string $profileId, array $coreTabs): array
    {
        $wanted = [];
        foreach ($this->profiles->sections($profileId) as $section) {
            $wanted[$section->tab()] = true;
        }

        $tabs = [];
        foreach (self::TABS as $id => $label) {
            if (isset($coreTabs[$id])) {
                $tabs[$id] = $coreTabs[$id];
            } elseif (isset($wanted[$id]) && in_array($id, ['questions', 'cache'], true)) {
                $tabs[$id] = $this->t($label);
            }
        }
        foreach ($coreTabs as $id => $label) {
            if (!isset($tabs[$id])) {
                $tabs[$id] = $label;
            }
        }

        return $tabs;
    }

    /** Draws the extension's sections for one tab, each in its own panel beneath the tab's own content. */
    public function sections(string $profileId, string $tab): void
    {
        if (!in_array($tab, ProfileSectionV1::TABS, true)) {
            return;
        }

        $workspace = $this->profiles->workspace();
        if ($workspace === null) {
            return;
        }

        $id = new ProfileIdV1($profileId);
        foreach ($this->profiles->sections($profileId) as $section) {
            if ($section->tab() !== $tab) {
                continue;
            }
            echo '<section class="ncli-admin__tab-panel ncli-admin__extension ncli-admin__profile-section"><h2>' . $this->html($section->title()) . '</h2>';
            $level = ob_get_level();
            ob_start();
            try {
                $action = ExtensionScreens::NONCE_PREFIX . $section->key();
                $nonce = function_exists('wp_create_nonce') ? (string) wp_create_nonce($action) : '';
                $workspace->render($section->key(), $id, new WorkspaceContextV1(true, $nonce, '', $action));
                echo (string) ob_get_clean();
            } catch (\Throwable $exception) {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
                echo '<div class="notice notice-error"><p>' . $this->html($this->t('This section is temporarily unavailable.')) . '</p></div>';
            }
            echo '</section>';
        }
    }

    /** Whether any section targets this tab (used for the extension-only tabs). */
    public function hasSections(string $profileId, string $tab): bool
    {
        foreach ($this->profiles->sections($profileId) as $section) {
            if ($section->tab() === $tab) {
                return true;
            }
        }

        return false;
    }

    /** Address of the Profiles page. */
    public function landingUrl(): string
    {
        return $this->adminUrl(['view' => 'profiles']);
    }

    // ---- Profiles page --------------------------------------------------------------------------------------------------

    private function renderLanding(): void
    {
        $settings = call_user_func($this->settings);
        $summaries = $this->profiles->summaries();
        $limit = $this->limit();

        echo '<div class="wrap ncli-admin ncli-admin--workspace"><h1 class="screen-reader-text">' . $this->html($this->t('Niche Clever Interactive profiles')) . '</h1>';
        echo '<div class="ncli-admin__titlebar"><span class="ncli-admin__titlebar-name">' . $this->html($this->t('Profiles')) . '</span><span>' . $this->html($this->t('Dashboard')) . '</span></div>';
        $this->masthead();
        $this->notice();

        echo '<section class="ncli-admin__tab-panel ncli-admin__profiles"><h2>' . $this->html($this->t('Your profiles')) . '</h2>';
        echo '<p>' . $this->html($this->t('Each profile answers from its own vector store and has its own connection test. Open a profile to change its settings, or add a new one.')) . '</p>';
        echo '<p class="ncli-admin__profiles-actions">';
        if (count($summaries) < $limit) {
            echo '<a class="button button-primary" href="' . $this->attr($this->adminUrl(['view' => 'new'])) . '">' . $this->html($this->t('Add profile')) . '</a>';
        } else {
            echo '<span class="description">' . $this->html($this->t('The maximum number of profiles has been reached.')) . '</span>';
        }
        echo '</p>';

        echo '<table class="widefat striped ncli-admin__profile-table"><thead><tr>';
        foreach (['Profile', 'Tag', 'Availability', 'Connection test', 'Last tested', 'Access', 'Actions'] as $heading) {
            echo '<th scope="col">' . $this->html($this->t($heading)) . '</th>';
        }
        echo '</tr></thead><tbody>';
        $this->landingRow(CoreSettings::DEFAULT_PROFILE_ID, $this->t('Default profile'), $settings->isEnabled(), false, $settings);
        foreach ($summaries as $summary) {
            $this->landingRow($summary->id()->value(), $summary->label(), $summary->isEnabled(), $summary->isProtected(), $settings);
        }
        echo '</tbody></table>';
        echo '<p class="description">' . $this->html($this->t('"Current" means the profile passed a connection test with its present settings. Changing its vector store, corpus version, guidance, preset or model requires a new test.')) . '</p></section>';

        echo '<section class="ncli-admin__cards">';
        echo '<div class="ncli-admin__card"><h3>' . $this->html($this->t('Extension')) . '</h3><p class="description">' . $this->html($this->t('An installed extension supplies the list of profiles and the extra tabs shown here.')) . '</p></div>';
        echo '<div class="ncli-admin__card"><h3>' . $this->html($this->t('Help and settings')) . '</h3><p><a href="' . $this->attr($this->adminUrl(['page' => SiteSettingsPage::PAGE_SLUG, 'tab' => 'help'])) . '">' . $this->html($this->t('Open the Help Centre')) . '</a></p></div>';
        echo '</section></div>';
    }

    private function landingRow(string $id, string $label, bool $enabled, bool $protected, CoreSettings $settings): void
    {
        $status = $this->profiles->status($id, $settings, $enabled);
        [$tone, $text] = self::STATES[$status['state']] ?? ['warn', 'Unavailable: check its settings'];
        if ($status['state'] === Readiness::MISSING_CONNECTION_TEST && $status['status'] === 'failed') {
            $text = 'The last test failed';
        }
        $open = $this->adminUrl(['profile' => $id, 'tab' => 'setup']);

        echo '<tr><th scope="row"><a href="' . $this->attr($open) . '">' . $this->html($label) . '</a></th><td><code>' . $this->html($id) . '</code></td>';
        echo '<td>' . $this->html($enabled ? $this->t('Enabled') : $this->t('Disabled')) . '</td>';
        echo '<td><span class="ncli-admin__status ncli-admin__status--' . $tone . '">' . $this->html($this->t($text)) . '</span></td>';
        echo '<td>' . ($status['tested_at'] !== '' ? $this->html($this->testedAt($status['tested_at'])) : '&mdash;') . '</td>';
        echo '<td>' . $this->html($protected ? $this->t('Protected') : $this->t('Public')) . '</td><td>';
        echo '<a href="' . $this->attr($open) . '">' . $this->html($this->t('Open')) . '</a> ';
        if ($id === CoreSettings::DEFAULT_PROFILE_ID) {
            echo $this->testForm('ncli_test_connection', '_ncli_connection_nonce', 'ncli_test_connection', []);
        } elseif ($enabled) {
            echo $this->testForm(self::TEST_ACTION, '_ncli_ext_nonce', self::TEST_NONCE_ACTION, ['profile_id' => $id]);
        }
        echo '</td></tr>';
    }

    /** "2026-10-16T10:00:00Z" as "2026-10-16 10:00 UTC". */
    private function testedAt(string $time): string
    {
        $stamp = strtotime($time);

        return $stamp === false ? $time : gmdate('Y-m-d H:i', $stamp) . ' UTC';
    }

    /** @param array<string, string> $hidden */
    private function testForm(string $action, string $nonceField, string $nonceAction, array $hidden): string
    {
        $nonce = function_exists('wp_create_nonce') ? (string) wp_create_nonce($nonceAction) : '';
        $fields = '<input type="hidden" name="action" value="' . $this->attr($action) . '"><input type="hidden" name="' . $this->attr($nonceField) . '" value="' . $this->attr($nonce) . '">';
        foreach ($hidden as $name => $value) {
            $fields .= '<input type="hidden" name="' . $this->attr($name) . '" value="' . $this->attr($value) . '">';
        }

        return '<form class="ncli-admin__inline-form" method="post" action="' . $this->attr($this->postUrl()) . '">' . $fields . '<button type="submit" class="button-link">' . $this->html($this->t('Test connection')) . '</button></form>';
    }

    // ---- add a profile ----------------------------------------------------------------------------------------------------

    private function renderNew(): void
    {
        $fields = new ProfileFieldRenderer();
        echo '<div class="wrap ncli-admin ncli-admin--workspace"><h1 class="screen-reader-text">' . $this->html($this->t('Add a profile')) . '</h1>';
        echo '<div class="ncli-admin__titlebar"><span class="ncli-admin__titlebar-name">' . $this->html($this->t('New profile')) . '</span><span>' . $this->html($this->t('Profile workspace')) . '</span></div>';
        $this->masthead();
        $this->notice();
        if (count($this->profiles->summaries()) >= $this->limit()) {
            echo '<section class="ncli-admin__tab-panel"><p>' . $this->html($this->t('The maximum number of profiles has been reached.')) . '</p><p><a class="button" href="' . $this->attr($this->adminUrl(['view' => 'profiles'])) . '">' . $this->html($this->t('All profiles')) . '</a></p></section></div>';
            return;
        }

        echo '<form class="ncli-admin__workspace" method="post" action="' . $this->attr($this->postUrl()) . '"><input type="hidden" name="action" value="' . self::CREATE_ACTION . '">';
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field(self::CREATE_ACTION, '_ncli_nonce');
        }
        echo '<table class="form-table ncli-admin__form-table" role="presentation"><tbody>';
        echo '<tr class="ncli-admin__field"><th scope="row"><label for="ncli-new_id">' . $this->html($this->t('Profile id')) . '</label></th><td><input id="ncli-new_id" class="regular-text" type="text" name="profile_id" maxlength="64" required autocomplete="off"><p class="description">' . $this->html($this->t('Lower-case letters, numbers, hyphens and underscores. Used in the shortcode and the request; it cannot be changed later.')) . '</p></td></tr>';
        echo '<tr class="ncli-admin__field"><th scope="row"><label for="ncli-label">' . $this->html($this->t('Profile name')) . '</label></th><td><input id="ncli-label" class="regular-text" type="text" name="label" maxlength="80" required></td></tr>';
        $fields->profileRows('setup', $this->effective($this->blank()));
        echo '</tbody></table>';
        echo '<div class="ncli-admin__save-bar"><button type="submit" class="button button-primary">' . $this->html($this->t('Add profile')) . '</button><a class="button" href="' . $this->attr($this->adminUrl(['view' => 'profiles'])) . '">' . $this->html($this->t('All profiles')) . '</a></div></form></div>';
    }

    // ---- a named profile's workspace -----------------------------------------------------------------------------------------

    private function renderProfile(string $id, string $tab): void
    {
        $edit = $this->load($id);
        echo '<div class="wrap ncli-admin ncli-admin--workspace"><h1 class="screen-reader-text">' . $this->html($this->t('Niche Clever Interactive profile')) . '</h1>';
        if ($edit === null) {
            $this->masthead();
            echo '<section class="ncli-admin__tab-panel"><p>' . $this->html($this->t('That profile does not exist or cannot be edited right now.')) . '</p><p><a class="button" href="' . $this->attr($this->adminUrl(['view' => 'profiles'])) . '">' . $this->html($this->t('All profiles')) . '</a></p></section></div>';
            return;
        }

        $this->header($id, $edit->label());
        $this->masthead();
        $coreTabs = [];
        foreach (['setup', 'profile', 'ai-corpus', 'conversation', 'appearance-limits', 'placement', 'diagnostics'] as $coreTab) {
            $coreTabs[$coreTab] = $this->t(self::TABS[$coreTab]);
        }
        $tabs = $this->tabs($id, $coreTabs);
        echo '<nav class="nav-tab-wrapper ncli-admin__tabs" aria-label="' . $this->attr($this->t('Profile settings')) . '">';
        foreach ($tabs as $tabId => $label) {
            echo '<a class="nav-tab' . ($tabId === $tab ? ' nav-tab-active' : '') . '"' . ($tabId === $tab ? ' aria-current="page"' : '') . ' href="' . $this->attr($this->adminUrl(['profile' => $id, 'tab' => $tabId])) . '">' . $this->html($label) . '</a>';
        }
        echo '</nav>';
        $this->notice();

        if (in_array($tab, self::FORM_TABS, true)) {
            $this->renderForm($id, $tab, $edit);
        } elseif ($tab === 'placement') {
            echo '<section class="ncli-admin__tab-panel"><h2>' . $this->html($this->t('Placement')) . '</h2><p>' . $this->html($this->t('Place this profile with the shortcode')) . ' <code>[ncli_interactive profile="' . $this->html($id) . '"]</code> ' . $this->html($this->t('or the NC Interactive block with its profile setting.')) . '</p></section>';
        } elseif ($tab === 'diagnostics') {
            $this->renderDiagnostics($id, $edit);
        }
        $this->sections($id, $tab);
        echo '</div>';
    }

    private function renderForm(string $id, string $tab, ProfileEditV1 $edit): void
    {
        $values = $this->effective($edit);
        if ($tab === 'setup') {
            $this->checklist($id, $values);
        }
        echo '<form class="ncli-admin__workspace" method="post" action="' . $this->attr($this->postUrl()) . '"><input type="hidden" name="action" value="' . self::SAVE_ACTION . '"><input type="hidden" name="profile_id" value="' . $this->attr($id) . '"><input type="hidden" name="ncli_tab" value="' . $this->attr($tab) . '">';
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field(self::SAVE_ACTION, '_ncli_nonce');
        }
        echo '<table class="form-table ncli-admin__form-table" role="presentation"><tbody>';
        (new ProfileFieldRenderer())->profileRows($tab, $values);
        echo '</tbody></table>';
        echo '<div class="ncli-admin__save-bar"><button type="submit" class="button button-primary">' . $this->html($this->t('Save this profile')) . '</button><a class="button" href="' . $this->attr($this->adminUrl(['view' => 'profiles'])) . '">' . $this->html($this->t('All profiles')) . '</a></div></form>';

        if ($tab === 'setup') {
            echo '<section class="ncli-admin__tab-panel ncli-admin__extension"><h2>' . $this->html($this->t('Delete this profile')) . '</h2><form method="post" action="' . $this->attr($this->postUrl()) . '"><input type="hidden" name="action" value="' . self::DELETE_ACTION . '"><input type="hidden" name="profile_id" value="' . $this->attr($id) . '">';
            if (function_exists('wp_nonce_field')) {
                wp_nonce_field(self::DELETE_ACTION, '_ncli_nonce');
            }
            echo '<p>' . $this->html($this->t('Deleting a profile removes its settings and the data the extension keeps for it. Conversation history already recorded by Core is not removed.')) . '</p>';
            echo '<p><label><input type="checkbox" name="confirm" value="1" required> ' . $this->html($this->t('I want to delete this profile')) . '</label> <button type="submit" class="button button-link-delete">' . $this->html($this->t('Delete profile')) . '</button></p></form></section>';
        }
    }

    /** @param array<string, mixed> $values */
    private function checklist(string $id, array $values): void
    {
        $settings = call_user_func($this->settings);
        $status = $this->profiles->status($id, $settings, (bool) $values['enabled']);
        $tested = $status['state'] === Readiness::READY;
        $test = $this->testForm(self::TEST_ACTION, '_ncli_ext_nonce', self::TEST_NONCE_ACTION, ['profile_id' => $id]);
        $items = [
            [$this->t('Enter your vector store'), $values['reference_saved'] ? $this->t('A vector store reference is saved for this profile.') : $this->t('Save the vector store reference in AI & Corpus.'), $values['reference_saved'], ''],
            [$this->t('Set a corpus version'), $values['corpus_version'] !== '' ? $this->t('A corpus version is saved.') : $this->t('Enter a corpus version below.'), $values['corpus_version'] !== '', ''],
            [$this->t('Test the connection'), $tested ? $this->t('The current configuration passed its File Search connection test.') : $this->t('Run a new test after changing the vector store, corpus version, guidance, preset or any model setting.'), $tested, $test],
        ];
        $done = count(array_filter($items, static fn (array $item): bool => $item[2]));
        echo '<section class="ncli-admin__get-started"><h2><span>' . $this->html($this->t('Get started')) . '</span><span class="ncli-admin__get-started-count">' . $this->html(sprintf($this->t('%1$d of %2$d complete'), $done, count($items))) . '</span></h2><ol class="ncli-admin__checklist">';
        foreach ($items as $n => $item) {
            echo '<li><span class="ncli-admin__check">' . ($item[2] ? '&#10003;' : (string) ($n + 1)) . '</span><div><strong>' . $this->html($item[0]) . '</strong><p>' . $this->html($item[1]) . '</p>' . $item[3] . '</div></li>';
        }
        echo '</ol></section>';
    }

    private function renderDiagnostics(string $id, ProfileEditV1 $edit): void
    {
        $settings = call_user_func($this->settings);
        $status = $this->profiles->status($id, $settings, $edit->isEnabled());
        [, $text] = self::STATES[$status['state']] ?? ['warn', 'Unavailable: check its settings'];
        echo '<section class="ncli-admin__tab-panel"><h2>' . $this->html($this->t('Diagnostics')) . '</h2>';
        echo '<p>' . $this->html($this->t('Readiness:')) . ' <strong>' . $this->html($this->t($text)) . '</strong></p>';
        echo '<p>' . $this->html($this->t('Connection test:')) . ' <strong>' . $this->html($status['status']) . '</strong>' . ($status['tested_at'] !== '' ? ' (' . $this->html($this->t('UTC')) . ' ' . $this->html($status['tested_at']) . ')' : '') . '</p>';
        if ($edit->isEnabled()) {
            echo '<p>' . $this->testForm(self::TEST_ACTION, '_ncli_ext_nonce', self::TEST_NONCE_ACTION, ['profile_id' => $id]) . '</p>';
        }
        echo '<p>' . $this->html($this->t('No credential, provider payload, file identifier, vector-store identifier, fingerprint or retrieved content is shown in diagnostics.')) . '</p></section>';
    }

    // ---- handlers ---------------------------------------------------------------------------------------------------------------

    public function save(): void
    {
        if (!$this->guard->allows(self::SAVE_ACTION, $_POST['_ncli_nonce'] ?? null)) {
            $this->deny();
            return;
        }

        $id = $this->postId();
        $tab = $this->safeTab($_POST['ncli_tab'] ?? 'setup');
        $editor = $this->profiles->editor();
        $current = $id !== null ? $this->load($id) : null;
        if ($id === null || $editor === null || $current === null) {
            call_user_func($this->redirect, ['view' => 'profiles', 'ncli_notice' => 'missing']);
            return;
        }

        try {
            $edit = $this->merge($current);
            $editor->save(new ProfileIdV1($id), $edit);
            call_user_func($this->redirect, ['profile' => $id, 'tab' => $tab, 'ncli_notice' => 'saved']);
        } catch (\InvalidArgumentException $exception) {
            call_user_func($this->redirect, ['profile' => $id, 'tab' => $tab, 'ncli_notice' => 'invalid']);
        } catch (\Throwable $exception) {
            call_user_func($this->redirect, ['profile' => $id, 'tab' => $tab, 'ncli_notice' => 'failed']);
        }
    }

    public function create(): void
    {
        if (!$this->guard->allows(self::CREATE_ACTION, $_POST['_ncli_nonce'] ?? null)) {
            $this->deny();
            return;
        }

        $id = $this->postId();
        $editor = $this->profiles->editor();
        if ($id === null || $id === CoreSettings::DEFAULT_PROFILE_ID || $editor === null) {
            call_user_func($this->redirect, ['view' => 'new', 'ncli_notice' => 'invalid']);
            return;
        }

        try {
            $edit = $this->merge($this->blank());
            $editor->create(new ProfileIdV1($id), $edit);
            call_user_func($this->redirect, ['profile' => $id, 'tab' => 'setup', 'ncli_notice' => 'created']);
        } catch (\InvalidArgumentException $exception) {
            call_user_func($this->redirect, ['view' => 'new', 'ncli_notice' => 'invalid']);
        } catch (\LogicException $exception) {
            call_user_func($this->redirect, ['view' => 'new', 'ncli_notice' => 'exists']);
        } catch (\Throwable $exception) {
            call_user_func($this->redirect, ['view' => 'new', 'ncli_notice' => 'failed']);
        }
    }

    public function delete(): void
    {
        if (!$this->guard->allows(self::DELETE_ACTION, $_POST['_ncli_nonce'] ?? null)) {
            $this->deny();
            return;
        }

        $id = $this->postId();
        $editor = $this->profiles->editor();
        if ($id === null || $id === CoreSettings::DEFAULT_PROFILE_ID || $editor === null || ($_POST['confirm'] ?? null) !== '1') {
            call_user_func($this->redirect, ['view' => 'profiles', 'ncli_notice' => 'missing']);
            return;
        }

        try {
            $removed = $editor->delete(new ProfileIdV1($id));
        } catch (\Throwable $exception) {
            $removed = false;
        }
        call_user_func($this->redirect, ['view' => 'profiles', 'ncli_notice' => $removed ? 'deleted' : 'failed']);
    }

    /**
     * While the hub is active the extension's screens are tabs and panels of Core's pages, so every
     * old `ncli-ext-*` address leads to where its content now lives, and the default profile's Help
     * tab leads to the Help tab of Settings. Runs while the menu is built, before WordPress would
     * refuse an unregistered page.
     */
    public function redirectRetiredPages(): void
    {
        $page = $_GET['page'] ?? null;
        if (!is_string($page) || !$this->active()) {
            return;
        }

        if ($page === SettingsPage::PAGE_SLUG && ($_GET['tab'] ?? null) === 'help') {
            $help = $_GET['help'] ?? null;
            call_user_func($this->redirect, ['page' => SiteSettingsPage::PAGE_SLUG, 'tab' => 'help'] + (is_string($help) && in_array($help, ['overview', 'faq', 'premium'], true) ? ['help' => $help] : []));
            return;
        }

        if (preg_match('/^' . preg_quote(ExtensionScreens::PAGE_PREFIX, '/') . '([a-z]+)$/D', $page, $m) !== 1) {
            return;
        }
        $targets = [
            'archive' => ['page' => ConversationsPage::PAGE_SLUG, 'tab' => 'governance'],
            'settings' => ['page' => SiteSettingsPage::PAGE_SLUG],
        ];
        call_user_func($this->redirect, $targets[$m[1]] ?? ['view' => 'profiles']);
    }

    public function redirectTo(array $query): void
    {
        if (!function_exists('wp_safe_redirect')) {
            return;
        }

        wp_safe_redirect($this->adminUrl($query));
        exit;
    }

    // ---- values -----------------------------------------------------------------------------------------------------------------

    /** A starting point for a new profile: the inherited model and appearance, blank wording. */
    private function blank(): ProfileEditV1
    {
        return new ProfileEditV1('New profile', true, null, '', '', 'balanced', [], 'classic', 10);
    }

    private function load(string $id): ?ProfileEditV1
    {
        $editor = $this->profiles->editor();
        if ($editor === null || !$this->validId($id) || $id === CoreSettings::DEFAULT_PROFILE_ID) {
            return null;
        }

        try {
            $edit = $editor->values(new ProfileIdV1($id));

            return $edit instanceof ProfileEditV1 ? $edit : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * The values the form shows: the profile's own where it has them, otherwise those it inherits from the default profile.
     *
     * @return array<string, mixed>
     */
    private function effective(ProfileEditV1 $edit): array
    {
        $defaults = call_user_func($this->settings)->toArray();
        $appearance = array_merge(
            ['panel_width' => $defaults['appearance']['panel_width'], 'accent_color' => $defaults['appearance']['accent_color'], 'show_credit' => $defaults['appearance']['show_credit']],
            $edit->appearance() ?? []
        );

        return [
            'label' => $edit->label(),
            'enabled' => $edit->isEnabled(),
            'reference_saved' => $edit->hasStoredReference(),
            'corpus_version' => $edit->corpusVersion(),
            'instructions' => $edit->guidance(),
            'response_preset' => $edit->preset(),
            'presentation' => array_merge(['title' => '', 'headline' => '', 'introduction' => '', 'placeholder' => '', 'footer_left' => '', 'footer_right' => '', 'unavailable_message' => ''], $edit->presentation()),
            'panel_style' => $edit->panelStyle(),
            'visitor_requests_per_day' => $edit->visitorRequestsPerDay(),
            'model' => $edit->model() ?? $defaults['model'],
            'appearance' => $appearance,
        ];
    }

    /**
     * Overlays whatever the form posted on the current values. A value the form did not post is kept, so
     * each tab saves only its own fields; the model and appearance blocks become the profile's own only
     * when their tab is saved.
     */
    private function merge(ProfileEditV1 $current): ProfileEditV1
    {
        $presentation = array_merge(['title' => '', 'headline' => '', 'introduction' => '', 'placeholder' => '', 'footer_left' => '', 'footer_right' => '', 'unavailable_message' => ''], $current->presentation());
        foreach (array_keys($presentation) as $key) {
            $presentation[$key] = $this->stringPost('presentation_' . $key, $presentation[$key]);
        }

        $model = $current->model();
        if (isset($_POST['model_id'])) {
            $existing = $model ?? call_user_func($this->settings)->model();
            $id = $this->stringPost('model_id', $existing['id']);
            $effort = $this->stringPost('model_reasoning_effort', $existing['reasoning_effort']);
            if (ModelCatalogue::has($id) && !ModelCatalogue::validEffort($id, $effort)) {
                $effort = ModelCatalogue::DEFAULT_EFFORT;
            }
            $model = ModelSettings::validate([
                'id' => $id,
                'reasoning_effort' => $effort,
                'retrieval_depth' => $this->intPost('model_retrieval_depth', $existing['retrieval_depth']),
                'max_output_tokens' => $this->intPost('model_max_output_tokens', $existing['max_output_tokens']),
                'timeout_seconds' => $this->intPost('model_timeout_seconds', $existing['timeout_seconds']),
            ], $existing);
        }

        $appearance = $current->appearance();
        if (isset($_POST['panel_width'])) {
            $appearance = [
                'panel_width' => $this->stringPost('panel_width', 'standard'),
                'accent_color' => isset($_POST['accent_color_default']) && $_POST['accent_color_default'] === '1' ? '' : strtolower($this->stringPost('accent_color', '')),
                'show_credit' => isset($_POST['show_credit']) && $_POST['show_credit'] === '1',
            ];
            if (!isset(PanelAppearance::WIDTHS[$appearance['panel_width']]) || ($appearance['accent_color'] !== '' && !PanelAppearance::isHexColour($appearance['accent_color']))) {
                throw new \InvalidArgumentException('The panel width or accent colour is not one Core offers.');
            }
        }

        $reference = $this->stringPost('vector_store_reference', '');

        return new ProfileEditV1(
            $this->stringPost('label', $current->label() !== '' ? $current->label() : (string) ($_POST['profile_id'] ?? '')),
            isset($_POST['enabled']) ? $_POST['enabled'] === '1' : $current->isEnabled(),
            $reference !== '' ? $reference : null,
            $this->stringPost('corpus_version', $current->corpusVersion()),
            $this->stringPost('instructions', $current->guidance()),
            $this->stringPost('response_preset', $current->preset()),
            $presentation,
            $this->stringPost('panel_style', $current->panelStyle()),
            $this->intPost('visitor_requests_per_day', $current->visitorRequestsPerDay()),
            $model,
            $appearance,
            $current->hasStoredReference()
        );
    }

    // ---- small helpers ---------------------------------------------------------------------------------------------------------

    private function limit(): int
    {
        $editor = $this->profiles->editor();
        try {
            $limit = $editor !== null ? $editor->limit() : 0;
        } catch (\Throwable $exception) {
            $limit = 0;
        }

        return is_int($limit) && $limit > 0 && $limit <= 100 ? $limit : 0;
    }

    private function tab(string $profile): string
    {
        $tab = $this->query('tab');

        return $tab !== '' && array_key_exists($tab, self::TABS) && ($this->isCoreTab($tab) || $this->hasSections($profile, $tab)) ? $tab : 'setup';
    }

    private function isCoreTab(string $tab): bool
    {
        return !in_array($tab, ['questions', 'cache'], true);
    }

    private function safeTab($value): string
    {
        return is_string($value) && array_key_exists($value, self::TABS) ? $value : 'setup';
    }

    private function postId(): ?string
    {
        $id = $_POST['profile_id'] ?? null;

        return is_string($id) && $this->validId($this->unslash($id)) ? $this->unslash($id) : null;
    }

    private function validId(string $id): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $id) === 1;
    }

    private function query(string $key): string
    {
        $value = $_GET[$key] ?? '';

        return is_string($value) && preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $value) === 1 ? $value : '';
    }

    private function stringPost(string $key, string $default = ''): string
    {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? $this->unslash($_POST[$key]) : $default;
    }

    private function intPost(string $key, int $default): int
    {
        return isset($_POST[$key]) && is_string($_POST[$key]) && ctype_digit($_POST[$key]) ? (int) $_POST[$key] : $default;
    }

    private function unslash(string $value): string
    {
        return function_exists('wp_unslash') ? wp_unslash($value) : stripslashes($value);
    }

    private function deny(): void
    {
        if (function_exists('wp_die')) {
            wp_die($this->t('You are not permitted to change NCI profiles.'));
        }
    }

    private function notice(): void
    {
        $messages = [
            'saved' => ['success', 'Profile saved. Run the connection test before visitors can use it.'],
            'created' => ['success', 'Profile added. Enter its vector store, then run the connection test.'],
            'deleted' => ['success', 'Profile deleted.'],
            'invalid' => ['error', 'The profile could not be saved. Check the values: the id uses lower-case letters, numbers, hyphens or underscores, and every value has a maximum length.'],
            'exists' => ['error', 'A profile with that id already exists, or the maximum number of profiles has been reached.'],
            'missing' => ['error', 'That profile no longer exists.'],
            'failed' => ['error', 'The request could not be completed. Nothing was changed.'],
        ];
        $notice = $_GET['ncli_notice'] ?? null;
        if (is_string($notice) && isset($messages[$notice])) {
            echo '<div class="notice notice-' . $messages[$notice][0] . '"><p>' . $this->html($this->t($messages[$notice][1])) . '</p></div>';
        }
        $tests = [
            'passed' => ['success', 'The connection test passed.'],
            'failed' => ['error', 'The connection test did not pass. Check the vector store and corpus version, then try again.'],
            'unavailable' => ['error', 'That profile could not be tested. Save it and make sure it is enabled.'],
        ];
        $test = $_GET['ncli_profile_test'] ?? null;
        if (is_string($test) && isset($tests[$test])) {
            echo '<div class="notice notice-' . $tests[$test][0] . '"><p>' . $this->html($this->t($tests[$test][1])) . '</p></div>';
        }
    }

    public function masthead(): void
    {
        $path = 'assets/images/ncli-admin-banner.png';
        $url = function_exists('plugins_url') ? (string) plugins_url($path, dirname(__DIR__, 2) . '/niche-clever-interactive-core.php') : '';
        $file = dirname(__DIR__, 2) . '/' . $path;
        $modified = is_file($file) ? (int) filemtime($file) : 0;
        if ($url !== '' && $modified > 0) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'ver=' . $modified;
        }

        echo '<header class="ncli-admin__masthead"><img class="ncli-admin__masthead-art" src="' . $this->attr($url) . '" alt="" width="1544" height="500"></header>';
    }

    /** @param array<string, string> $query */
    private function adminUrl(array $query): string
    {
        $query = array_merge(['page' => SettingsPage::PAGE_SLUG], $query);
        if (function_exists('admin_url')) {
            return (string) admin_url('admin.php?' . http_build_query($query));
        }

        return '/wp-admin/admin.php?' . http_build_query($query);
    }

    private function postUrl(): string
    {
        return function_exists('admin_url') ? (string) admin_url('admin-post.php') : '/wp-admin/admin-post.php';
    }

    private function html(string $value): string
    {
        return function_exists('esc_html') ? esc_html($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function attr(string $value): string
    {
        return function_exists('esc_attr') ? esc_attr($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function t(string $text): string
    {
        return Translator::text($text);
    }
}
