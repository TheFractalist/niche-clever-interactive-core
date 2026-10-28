<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;

use NicheCleverInteractive\Core\Api\V1\Value\ProfileEditV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileSectionV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileSummaryV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceContextV1;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\ModelCatalogue;
use NicheCleverInteractive\Core\Configuration\ModelSettings;
use NicheCleverInteractive\Core\Configuration\PanelAppearance;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;
use NicheCleverInteractive\Core\Security\AdminActionGuard;

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
        'appearance-limits' => 'Appearance & Limits', 'questions' => 'Authored questions', 'collections' => 'Question collections', 'placement' => 'Placement',
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
        echo '<div class="ncli-admin__titlebar"><span class="ncli-admin__titlebar-name">' . esc_html($left) . '</span><span>' . esc_html($right) . '</span></div>';
    }

    /**
     * The navy title strip of a profile workspace: the profile's name on the left; on the right the
     * profile switcher, the link to every profile and the words "Profile workspace", in white.
     */
    public function header(string $profileId, string $title): void
    {
        $tab = $this->query('tab');
        echo '<div class="ncli-admin__titlebar"><span class="ncli-admin__titlebar-name">' . esc_html($title) . '</span><div class="ncli-admin__titlebar-tools">';
        echo '<form class="ncli-admin__titlebar-switch" method="get" action="' . esc_attr($this->adminUrl([])) . '"><input type="hidden" name="page" value="' . esc_attr(SettingsPage::PAGE_SLUG) . '">';
        if ($tab !== '' && array_key_exists($tab, self::TABS)) {
            echo '<input type="hidden" name="tab" value="' . esc_attr($tab) . '">';
        }
        echo '<label class="screen-reader-text" for="ncli-profile-switch">' . esc_html(__('Profile', 'niche-clever-interactive')) . '</label><select id="ncli-profile-switch" name="profile">';
        echo '<option value="default"' . ($profileId === CoreSettings::DEFAULT_PROFILE_ID ? ' selected' : '') . '>' . esc_html(__('Default profile', 'niche-clever-interactive')) . '</option>';
        foreach ($this->profiles->summaries() as $summary) {
            $id = $summary->id()->value();
            echo '<option value="' . esc_attr($id) . '"' . ($id === $profileId ? ' selected' : '') . '>' . esc_html($summary->label()) . '</option>';
        }
        echo '</select> <button type="submit" class="button">' . esc_html(__('Switch profile', 'niche-clever-interactive')) . '</button></form>';
        echo '<a class="button" href="' . esc_attr($this->adminUrl(['view' => 'profiles'])) . '">' . esc_html(__('All profiles', 'niche-clever-interactive')) . '</a>';
        echo '<span class="ncli-admin__titlebar-label">' . esc_html(__('Profile workspace', 'niche-clever-interactive')) . '</span></div></div>';
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
            } elseif (isset($wanted[$id]) && in_array($id, ['questions', 'collections', 'cache'], true)) {
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

    /** Draws the extension's sections for one tab, either with General or in their own sub-tab. */
    public function sections(string $profileId, string $tab, ?string $only = null): void
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
            if ($section->tab() !== $tab
                || ($only === 'panel' && !$section->inlineWithPanel())
                || ($only !== null && $only !== 'panel' && $only !== $section->key())) {
                continue;
            }
            echo '<section class="ncli-admin__tab-panel ncli-admin__extension ncli-admin__profile-section"><h2>' . esc_html($section->title()) . '</h2>';
            $level = ob_get_level();
            ob_start();
            try {
                $action = ExtensionScreens::NONCE_PREFIX . $section->key();
                $nonce = function_exists('wp_create_nonce') ? (string) wp_create_nonce($action) : '';
                $workspace->render($section->key(), $id, new WorkspaceContextV1(true, $nonce, '', $action));
                echo wp_kses((string) ob_get_clean(), ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
            } catch (\Throwable $exception) {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }
                echo '<div class="notice notice-error"><p>' . esc_html(__('This section is temporarily unavailable.', 'niche-clever-interactive')) . '</p></div>';
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

        echo '<div class="wrap ncli-admin ncli-admin--workspace"><h1 class="screen-reader-text">' . esc_html(__('Niche Clever Interactive profiles', 'niche-clever-interactive')) . '</h1>';
        echo '<div class="ncli-admin__titlebar"><span class="ncli-admin__titlebar-name">' . esc_html(__('Profiles', 'niche-clever-interactive')) . '</span><span>' . esc_html(__('Dashboard', 'niche-clever-interactive')) . '</span></div>';
        $this->masthead();
        $this->notice();

        echo '<section class="ncli-admin__tab-panel ncli-admin__profiles"><h2>' . esc_html(__('Your profiles', 'niche-clever-interactive')) . '</h2>';
        echo '<p>' . esc_html(__('Each profile answers from its own vector store and has its own connection test. Open a profile to change its settings, or add a new one.', 'niche-clever-interactive')) . '</p>';
        echo '<p class="ncli-admin__profiles-actions">';
        if (count($summaries) < $limit) {
            echo '<a class="button button-primary" href="' . esc_attr($this->adminUrl(['view' => 'new'])) . '">' . esc_html(__('Add profile', 'niche-clever-interactive')) . '</a>';
        } else {
            echo '<span class="description">' . esc_html(__('The maximum number of profiles has been reached.', 'niche-clever-interactive')) . '</span>';
        }
        echo '</p>';

        echo '<table class="widefat striped ncli-admin__profile-table"><thead><tr>';
        foreach (['Profile', 'Tag', 'Availability', 'Connection test', 'Last tested', 'Access', 'Actions'] as $heading) {
            echo '<th scope="col">' . esc_html($this->t($heading)) . '</th>';
        }
        echo '</tr></thead><tbody>';
        $this->landingRow(CoreSettings::DEFAULT_PROFILE_ID, __('Default profile', 'niche-clever-interactive'), $settings->isEnabled(), false, $settings);
        foreach ($summaries as $summary) {
            $this->landingRow($summary->id()->value(), $summary->label(), $summary->isEnabled(), $summary->isProtected(), $settings);
        }
        echo '</tbody></table>';
        echo '<p class="description">' . esc_html(__('"Current" means the profile passed a connection test with its present settings. Changing its vector store, corpus version, guidance, preset or model requires a new test.', 'niche-clever-interactive')) . '</p></section>';

        echo '<section class="ncli-admin__cards">';
        echo '<div class="ncli-admin__card"><h3>' . esc_html(__('Extension', 'niche-clever-interactive')) . '</h3><p class="description">' . esc_html(__('An installed extension supplies the list of profiles and the extra tabs shown here.', 'niche-clever-interactive')) . '</p></div>';
        echo '<div class="ncli-admin__card"><h3>' . esc_html(__('Help and settings', 'niche-clever-interactive')) . '</h3><p><a href="' . esc_attr($this->adminUrl(['page' => SiteSettingsPage::PAGE_SLUG, 'tab' => 'help'])) . '">' . esc_html(__('Open the Help Centre', 'niche-clever-interactive')) . '</a></p></div>';
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

        echo '<tr><th scope="row"><a href="' . esc_attr($open) . '">' . esc_html($label) . '</a></th><td><code>' . esc_html($id) . '</code></td>';
        echo '<td>' . esc_html($enabled ? __('Enabled', 'niche-clever-interactive') : __('Disabled', 'niche-clever-interactive')) . '</td>';
        echo '<td><span class="ncli-admin__status ncli-admin__status--' . esc_attr($tone) . '">' . esc_html($this->t($text)) . '</span></td>';
        echo '<td>' . ($status['tested_at'] !== '' ? esc_html($this->testedAt($status['tested_at'])) : '&mdash;') . '</td>';
        echo '<td>' . esc_html($protected ? __('Protected', 'niche-clever-interactive') : __('Public', 'niche-clever-interactive')) . '</td><td>';
        echo '<a href="' . esc_attr($open) . '">' . esc_html(__('Open', 'niche-clever-interactive')) . '</a> ';
        if ($id === CoreSettings::DEFAULT_PROFILE_ID) {
        echo wp_kses($this->testForm('ncli_test_connection', '_ncli_connection_nonce', 'ncli_test_connection', []), ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
        } elseif ($enabled) {
        echo wp_kses($this->testForm(self::TEST_ACTION, '_ncli_ext_nonce', self::TEST_NONCE_ACTION, ['profile_id' => $id]), ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
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
        $fields = '<input type="hidden" name="action" value="' . esc_attr($action) . '"><input type="hidden" name="' . esc_attr($nonceField) . '" value="' . esc_attr($nonce) . '">';
        foreach ($hidden as $name => $value) {
            $fields .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
        }

        return '<form class="ncli-admin__inline-form" method="post" action="' . esc_attr($this->postUrl()) . '">' . $fields . '<button type="submit" class="button-link">' . esc_html(__('Test connection', 'niche-clever-interactive')) . '</button></form>';
    }

    // ---- add a profile ----------------------------------------------------------------------------------------------------

    private function renderNew(): void
    {
        $fields = new ProfileFieldRenderer();
        echo '<div class="wrap ncli-admin ncli-admin--workspace"><h1 class="screen-reader-text">' . esc_html(__('Add a profile', 'niche-clever-interactive')) . '</h1>';
        echo '<div class="ncli-admin__titlebar"><span class="ncli-admin__titlebar-name">' . esc_html(__('New profile', 'niche-clever-interactive')) . '</span><span>' . esc_html(__('Profile workspace', 'niche-clever-interactive')) . '</span></div>';
        $this->masthead();
        $this->notice();
        if (count($this->profiles->summaries()) >= $this->limit()) {
            echo '<section class="ncli-admin__tab-panel"><p>' . esc_html(__('The maximum number of profiles has been reached.', 'niche-clever-interactive')) . '</p><p><a class="button" href="' . esc_attr($this->adminUrl(['view' => 'profiles'])) . '">' . esc_html(__('All profiles', 'niche-clever-interactive')) . '</a></p></section></div>';
            return;
        }

        echo '<form class="ncli-admin__workspace" method="post" action="' . esc_attr($this->postUrl()) . '"><input type="hidden" name="action" value="' . esc_attr(self::CREATE_ACTION) . '">';
        echo wp_kses($this->nonceField(self::CREATE_ACTION), ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
        echo '<table class="form-table ncli-admin__form-table" role="presentation"><tbody>';
        echo '<tr class="ncli-admin__field"><th scope="row"><label for="ncli-new_id">' . esc_html(__('Profile id', 'niche-clever-interactive')) . '</label></th><td><input id="ncli-new_id" class="regular-text" type="text" name="profile_id" maxlength="64" required autocomplete="off"><p class="description">' . esc_html(__('Lower-case letters, numbers, hyphens and underscores. Used in the shortcode and the request; it cannot be changed later.', 'niche-clever-interactive')) . '</p></td></tr>';
        echo '<tr class="ncli-admin__field"><th scope="row"><label for="ncli-label">' . esc_html(__('Profile name', 'niche-clever-interactive')) . '</label></th><td><input id="ncli-label" class="regular-text" type="text" name="label" maxlength="80" required></td></tr>';
        $fields->profileRows('setup', $this->effective($this->blank()), true);
        echo '</tbody></table>';
        echo '<div class="ncli-admin__save-bar"><button type="submit" class="button button-primary">' . esc_html(__('Add profile', 'niche-clever-interactive')) . '</button><a class="button" href="' . esc_attr($this->adminUrl(['view' => 'profiles'])) . '">' . esc_html(__('All profiles', 'niche-clever-interactive')) . '</a></div></form></div>';
    }

    // ---- a named profile's workspace -----------------------------------------------------------------------------------------

    private function renderProfile(string $id, string $tab): void
    {
        $edit = $this->load($id);
        echo '<div class="wrap ncli-admin ncli-admin--workspace"><h1 class="screen-reader-text">' . esc_html(__('Niche Clever Interactive profile', 'niche-clever-interactive')) . '</h1>';
        if ($edit === null) {
            $this->masthead();
            echo '<section class="ncli-admin__tab-panel"><p>' . esc_html(__('That profile does not exist or cannot be edited right now.', 'niche-clever-interactive')) . '</p><p><a class="button" href="' . esc_attr($this->adminUrl(['view' => 'profiles'])) . '">' . esc_html(__('All profiles', 'niche-clever-interactive')) . '</a></p></section></div>';
            return;
        }

        $this->header($id, $edit->label());
        $this->masthead();
        $coreTabs = [];
        foreach (['setup', 'profile', 'ai-corpus', 'conversation', 'appearance-limits', 'placement', 'diagnostics'] as $coreTab) {
            $coreTabs[$coreTab] = $this->t(self::TABS[$coreTab]);
        }
        $tabs = $this->tabs($id, $coreTabs);
        echo '<nav class="nav-tab-wrapper ncli-admin__tabs" aria-label="' . esc_attr(__('Profile settings', 'niche-clever-interactive')) . '">';
        foreach ($tabs as $tabId => $label) {
            echo '<a class="nav-tab' . ($tabId === $tab ? ' nav-tab-active' : '') . '"' . ($tabId === $tab ? ' aria-current="page"' : '') . ' href="' . esc_attr($this->adminUrl(['profile' => $id, 'tab' => $tabId])) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
        $this->notice();

        // A form tab with separately presented extension sections splits into General and one sub-tab
        // per section. Inline sections remain with General, so closely related controls stay together.
        $sub = $this->subTab($id, $tab);
        if ($sub !== null) {
            $this->subTabStrip($id, $tab, $sub);
        }
        if (in_array($tab, self::FORM_TABS, true)) {
            if ($sub === null || $sub === 'panel') {
                $this->renderForm($id, $tab, $edit);
            }
        } elseif ($tab === 'placement') {
            echo '<section class="ncli-admin__tab-panel"><h2>' . esc_html(__('Placement', 'niche-clever-interactive')) . '</h2><p>' . esc_html(__('Place this profile with the shortcode', 'niche-clever-interactive')) . ' <code>[ncli_interactive profile="' . esc_html($id) . '"]</code> ' . esc_html(__('or the NC Interactive block with its profile setting.', 'niche-clever-interactive')) . '</p></section>';
        } elseif ($tab === 'diagnostics') {
            $this->renderDiagnostics($id, $edit);
        }
        $this->sections($id, $tab, $sub);
        echo '</div>';
    }

    /** The sub-tabs offered on form tabs with separately presented extension sections. */
    private function subTabs(string $profileId, string $tab): array
    {
        $generalLabels = [
            'ai-corpus' => __('General', 'niche-clever-interactive'),
            'conversation' => __('General', 'niche-clever-interactive'),
            'appearance-limits' => __('Panel style', 'niche-clever-interactive'),
        ];
        if (!isset($generalLabels[$tab])) {
            return [];
        }
        $tabs = ['panel' => $generalLabels[$tab]];
        foreach ($this->profiles->sections($profileId) as $section) {
            if ($section->tab() === $tab && !$section->inlineWithPanel()) {
                $tabs[$section->key()] = $section->title();
            }
        }

        return count($tabs) > 1 ? $tabs : [];
    }

    /** The chosen sub-tab (default: Core's own panel fields), or null when this tab does not split. */
    public function subTab(string $profileId, string $tab): ?string
    {
        $tabs = $this->subTabs($profileId, $tab);
        if ($tabs === []) {
            return null;
        }
        $wanted = AdminRequest::queryText('ncli_sub');

        return array_key_exists($wanted, $tabs) ? $wanted : 'panel';
    }

    public function subTabStrip(string $profileId, string $tab, string $current): void
    {
        echo '<nav class="ncli-admin__substrip" aria-label="' . esc_attr(__('Sections of this tab', 'niche-clever-interactive')) . '">';
        foreach ($this->subTabs($profileId, $tab) as $key => $label) {
            echo '<a class="ncli-admin__subtab' . ($key === $current ? ' ncli-admin__subtab--active' : '') . '"' . ($key === $current ? ' aria-current="page"' : '') . ' href="' . esc_attr($this->adminUrl(['profile' => $profileId, 'tab' => $tab, 'ncli_sub' => $key])) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
    }

    private function renderForm(string $id, string $tab, ProfileEditV1 $edit): void
    {
        $values = $this->effective($edit);
        if ($tab === 'setup') {
            $this->checklist($id, $values);
        }
        echo '<form class="ncli-admin__workspace" method="post" action="' . esc_attr($this->postUrl()) . '"><input type="hidden" name="action" value="' . esc_attr(self::SAVE_ACTION) . '"><input type="hidden" name="profile_id" value="' . esc_attr($id) . '"><input type="hidden" name="ncli_tab" value="' . esc_attr($tab) . '">';
        echo wp_kses($this->nonceField(self::SAVE_ACTION), ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
        echo '<table class="form-table ncli-admin__form-table" role="presentation"><tbody>';
        (new ProfileFieldRenderer())->profileRows($tab, $values);
        echo '</tbody></table>';
        echo '<div class="ncli-admin__save-bar"><button type="submit" class="button button-primary">' . esc_html(__('Save this profile', 'niche-clever-interactive')) . '</button><a class="button" href="' . esc_attr($this->adminUrl(['view' => 'profiles'])) . '">' . esc_html(__('All profiles', 'niche-clever-interactive')) . '</a></div></form>';

        if ($tab === 'setup') {
        echo '<section class="ncli-admin__tab-panel ncli-admin__extension"><h2>' . esc_html(__('Delete this profile', 'niche-clever-interactive')) . '</h2><form method="post" action="' . esc_attr($this->postUrl()) . '"><input type="hidden" name="action" value="' . esc_attr(self::DELETE_ACTION) . '"><input type="hidden" name="profile_id" value="' . esc_attr($id) . '">';
        echo wp_kses($this->nonceField(self::DELETE_ACTION), ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
            echo '<p>' . esc_html(__('Deleting a profile removes its settings and the data the extension keeps for it. Conversation history already recorded by Core is not removed.', 'niche-clever-interactive')) . '</p>';
            echo '<p><label><input type="checkbox" name="confirm" value="1" required> ' . esc_html(__('I want to delete this profile', 'niche-clever-interactive')) . '</label> <button type="submit" class="button button-link-delete">' . esc_html(__('Delete profile', 'niche-clever-interactive')) . '</button></p></form></section>';
        }
    }

    /** @param array<string, mixed> $values */
    private function checklist(string $id, array $values): void
    {
        $settings = call_user_func($this->settings);
        $status = $this->profiles->status($id, $settings, (bool) $values['enabled']);
        $keyReady = (new SecretResolver())->isAvailable();
        $corpusReady = $values['reference_saved'] && $values['corpus_version'] !== '';
        $tested = $status['state'] === Readiness::READY;
        $placementReady = $this->hasPublishedProfilePlacement($id);
        $test = $this->testForm(self::TEST_ACTION, '_ncli_ext_nonce', self::TEST_NONCE_ACTION, ['profile_id' => $id]);
        $testState = !$values['enabled']
            ? __('Enable this profile, then run a connection test.', 'niche-clever-interactive')
            : ($tested ? __('The current configuration passed its File Search connection test.', 'niche-clever-interactive') : __('Run a new test after changing the vector store, corpus version, guidance, preset or any model setting.', 'niche-clever-interactive'));
        $items = [
            [__('Add your API key', 'niche-clever-interactive'), $keyReady ? __('The server-side constant is available.', 'niche-clever-interactive') : __('Add the server-side constant before testing.', 'niche-clever-interactive'), $keyReady, ''],
            [__('Choose your vector store', 'niche-clever-interactive'), $corpusReady ? __('A vector-store reference and corpus version are saved for this profile.', 'niche-clever-interactive') : __('Save the vector store in AI & Corpus and the corpus version in Profile.', 'niche-clever-interactive'), $corpusReady, ''],
            [__('Test the connection', 'niche-clever-interactive'), $testState, $tested, $test],
            [__('Place this profile', 'niche-clever-interactive'), $placementReady ? __('A matching placement was found on a published page.', 'niche-clever-interactive') : __('Add this profile’s block or shortcode to a published page.', 'niche-clever-interactive'), $placementReady, ''],
        ];
        $done = count(array_filter($items, static fn (array $item): bool => $item[2]));
        /* translators: 1: completed setup steps, 2: total setup steps. */
        echo '<section class="ncli-admin__get-started"><h2><span>' . esc_html(__('Get started', 'niche-clever-interactive')) . '</span><span class="ncli-admin__get-started-count">' . esc_html(sprintf(__('%1$d of %2$d complete', 'niche-clever-interactive'), $done, count($items))) . '</span></h2><ol class="ncli-admin__checklist">';
        foreach ($items as $item) {
            $this->checklistItem($item[0], $item[1], $item[2], $item[3]);
        }
        echo '</ol></section>';
    }

    private function checklistItem(string $title, string $description, bool $complete, string $action): void
    {
        $state = $complete ? __('Complete', 'niche-clever-interactive') : __('Needs attention', 'niche-clever-interactive');
        $symbol = $complete ? '✓' : '✕';
        $class = $complete ? 'complete' : 'incomplete';
        echo '<li class="ncli-admin__checklist-item ncli-admin__checklist-item--' . esc_attr($class) . '"><span class="ncli-admin__check ncli-admin__check--' . esc_attr($class) . '"><span aria-hidden="true">' . esc_html($symbol) . '</span><span class="screen-reader-text">' . esc_html($state) . '</span></span><div class="ncli-admin__checklist-copy"><div class="ncli-admin__checklist-heading"><strong>' . esc_html($title) . '</strong>' . ($action !== '' ? '<span class="ncli-admin__checklist-action">' . wp_kses($action, ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols()) . '</span>' : '') . '</div><p>' . esc_html($description) . '</p></div></li>';
    }

    /** Does a published shortcode or block select this exact non-default profile? */
    private function hasPublishedProfilePlacement(string $profileId): bool
    {
        if (!function_exists('get_posts')) {
            return false;
        }
        $posts = get_posts([
            'post_type' => ['page', 'post'],
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);
        if (!is_array($posts)) {
            return false;
        }
        $quoted = preg_quote($profileId, '/');
        $shortcode = '/\\[ncli_interactive(?=[^\\]]*\\bprofile\\s*=)[^\\]]*\\bprofile\\s*=\\s*(?:["\']' . $quoted . '["\']|' . $quoted . ')(?=[\\s\\]])[^\\]]*\\]/i';
        foreach ($posts as $post) {
            if (!is_object($post) || !isset($post->post_content) || !is_string($post->post_content)) {
                continue;
            }
            if (preg_match($shortcode, $post->post_content) === 1 || $this->blockUsesProfile($post->post_content, $profileId)) {
                return true;
            }
        }

        return false;
    }

    private function blockUsesProfile(string $content, string $profileId): bool
    {
        if (!function_exists('parse_blocks')) {
            return false;
        }
        $blocks = parse_blocks($content);
        if (!is_array($blocks)) {
            return false;
        }
        $contains = function (array $blocks) use (&$contains, $profileId): bool {
            foreach ($blocks as $block) {
                if (!is_array($block)) {
                    continue;
                }
                $attrs = $block['attrs'] ?? [];
                if (($block['blockName'] ?? null) === 'ncli/interactive' && is_array($attrs) && ($attrs['profile'] ?? null) === $profileId) {
                    return true;
                }
                $inner = $block['innerBlocks'] ?? [];
                if (is_array($inner) && $contains($inner)) {
                    return true;
                }
            }

            return false;
        };

        return $contains($blocks);
    }

    private function renderDiagnostics(string $id, ProfileEditV1 $edit): void
    {
        $settings = call_user_func($this->settings);
        $status = $this->profiles->status($id, $settings, $edit->isEnabled());
        [, $text] = self::STATES[$status['state']] ?? ['warn', 'Unavailable: check its settings'];
        echo '<section class="ncli-admin__tab-panel"><h2>' . esc_html(__('Diagnostics', 'niche-clever-interactive')) . '</h2>';
        echo '<p>' . esc_html(__('Readiness:', 'niche-clever-interactive')) . ' <strong>' . esc_html($this->t($text)) . '</strong></p>';
        echo '<p>' . esc_html(__('Connection test:', 'niche-clever-interactive')) . ' <strong>' . esc_html($status['status']) . '</strong>' . ($status['tested_at'] !== '' ? ' (' . esc_html(__('UTC', 'niche-clever-interactive')) . ' ' . esc_html($status['tested_at']) . ')' : '') . '</p>';
        if ($edit->isEnabled()) {
        echo '<p>' . wp_kses($this->testForm(self::TEST_ACTION, '_ncli_ext_nonce', self::TEST_NONCE_ACTION, ['profile_id' => $id]), ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols()) . '</p>';
        }
        echo '<p>' . esc_html(__('No credential, provider payload, file identifier, vector-store identifier, fingerprint or retrieved content is shown in diagnostics.', 'niche-clever-interactive')) . '</p></section>';
    }

    // ---- handlers ---------------------------------------------------------------------------------------------------------------

    public function save(): void
    {
        if (!$this->guard->allows(self::SAVE_ACTION, AdminRequest::postText('_ncli_nonce'))) {
            $this->deny();
            return;
        }

        $id = $this->postId();
        $tab = $this->safeTab(AdminRequest::postText('ncli_tab'));
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
        if (!$this->guard->allows(self::CREATE_ACTION, AdminRequest::postText('_ncli_nonce'))) {
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
        if (!$this->guard->allows(self::DELETE_ACTION, AdminRequest::postText('_ncli_nonce'))) {
            $this->deny();
            return;
        }

        $id = $this->postId();
        $editor = $this->profiles->editor();
        if ($id !== null && $id !== CoreSettings::DEFAULT_PROFILE_ID && $editor !== null && !AdminRequest::postCheckbox('confirm')) {
            call_user_func($this->redirect, ['profile' => $id, 'tab' => 'setup', 'ncli_notice' => 'confirm']);
            return;
        }
        if ($id === null || $id === CoreSettings::DEFAULT_PROFILE_ID || $editor === null) {
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
        $page = AdminRequest::queryText('page');
        if ($page === '' || !$this->active()) {
            return;
        }

        if ($page === SettingsPage::PAGE_SLUG && AdminRequest::queryText('tab') === 'help') {
            $help = AdminRequest::queryText('help');
            call_user_func($this->redirect, ['page' => SiteSettingsPage::PAGE_SLUG, 'tab' => 'help'] + (in_array($help, ['overview', 'faq', 'premium'], true) ? ['help' => $help] : []));
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
            'presentation' => array_merge(['title' => '', 'headline' => '', 'introduction' => '', 'placeholder' => '', 'footer_left' => '', 'footer_right' => '', 'unavailable_message' => '', 'processing_message' => ''], $edit->presentation()),
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
        $presentation = array_merge(['title' => '', 'headline' => '', 'introduction' => '', 'placeholder' => '', 'footer_left' => '', 'footer_right' => '', 'unavailable_message' => '', 'processing_message' => ''], $current->presentation());
        foreach (array_keys($presentation) as $key) {
            $presentation[$key] = $this->stringPost('presentation_' . $key, $presentation[$key]);
        }

        $model = $current->model();
        if (AdminRequest::postOptionalText('model_id') !== null) {
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
        if (AdminRequest::postOptionalText('panel_width') !== null) {
            $appearance = [
                'panel_width' => $this->stringPost('panel_width', 'standard'),
                'accent_color' => AdminRequest::postCheckbox('accent_color_default') ? '' : strtolower($this->stringPost('accent_color', '')),
                'show_credit' => AdminRequest::postCheckbox('show_credit'),
            ];
            if (!isset(PanelAppearance::WIDTHS[$appearance['panel_width']]) || ($appearance['accent_color'] !== '' && !PanelAppearance::isHexColour($appearance['accent_color']))) {
                throw new \InvalidArgumentException('The panel width or accent colour is not one Core offers.');
            }
        }

        $reference = $this->stringPost('vector_store_reference', '');

        return new ProfileEditV1(
            $this->stringPost('label', $current->label() !== '' ? $current->label() : AdminRequest::postText('profile_id')),
            AdminRequest::postOptionalText('enabled') !== null ? AdminRequest::postCheckbox('enabled') : $current->isEnabled(),
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
        return !in_array($tab, ['questions', 'collections', 'cache'], true);
    }

    private function safeTab($value): string
    {
        return is_string($value) && array_key_exists($value, self::TABS) ? $value : 'setup';
    }

    private function postId(): ?string
    {
        $id = AdminRequest::postText('profile_id');

        return $this->validId($id) ? $id : null;
    }

    private function validId(string $id): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $id) === 1;
    }

    private function query(string $key): string
    {
        $value = AdminRequest::queryText($key);

        return preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $value) === 1 ? $value : '';
    }

    private function stringPost(string $key, string $default = ''): string
    {
        $value = $key === 'instructions' ? AdminRequest::postOptionalTextarea($key) : AdminRequest::postOptionalText($key);

        return $value ?? $default;
    }

    private function intPost(string $key, int $default): int
    {
        return AdminRequest::postPositiveInt($key) ?? $default;
    }

    private function unslash(string $value): string
    {
        return function_exists('wp_unslash') ? wp_unslash($value) : stripslashes($value);
    }

    private function deny(): void
    {
        if (function_exists('wp_die')) {
            wp_die(esc_html(__('You are not permitted to change NCI profiles.', 'niche-clever-interactive')));
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
            'confirm' => ['error', 'Tick the box to confirm before deleting the profile. Nothing was deleted.'],
            'failed' => ['error', 'The request could not be completed. Nothing was changed.'],
        ];
        $notice = AdminRequest::queryText('ncli_notice');
        if (is_string($notice) && isset($messages[$notice])) {
        echo '<div class="notice notice-' . esc_attr($messages[$notice][0]) . '"><p>' . esc_html($this->t($messages[$notice][1])) . '</p></div>';
        }
        $tests = [
            'passed' => ['success', 'The connection test passed.'],
            'failed' => ['error', 'The connection test did not pass. Check the vector store and corpus version, then try again.'],
            'unavailable' => ['error', 'That profile could not be tested. Save it and make sure it is enabled.'],
        ];
        $test = AdminRequest::queryText('ncli_profile_test');
        if (is_string($test) && isset($tests[$test])) {
        echo '<div class="notice notice-' . esc_attr($tests[$test][0]) . '"><p>' . esc_html($this->t($tests[$test][1])) . '</p></div>';
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

        echo '<header class="ncli-admin__masthead"><img class="ncli-admin__masthead-art" src="' . esc_attr($url) . '" alt="" width="1544" height="500"></header>';
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

    /** The nonce as a hidden field. WordPress's own helper gives every field the same id, which is invalid when a page has two forms. */
    private function nonceField(string $action): string
    {
        $nonce = function_exists('wp_create_nonce') ? (string) wp_create_nonce($action) : '';

        return '<input type="hidden" name="_ncli_nonce" value="' . esc_attr($nonce) . '">';
    }

    private function t(string $text): string
    {
        return $text;
    }
}
