<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;

use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Security\AdminActionGuard;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic markup uses the tested escapeHtml()/escapeAttribute() wrappers, which keep this PHP 8.1-compatible class usable in pure tests.
// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- AdminActionGuard centralises capability and nonce verification before this class reads a request.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- requestInput() unslashes values and CoreSettings performs bounded validation after AdminActionGuard admits the request.
final class SettingsPage
{
    private const PAGE_SLUG = 'ncli-core';
    private const SAVE_ACTION = 'ncli_save_settings';

    private AdminActionGuard $guard;

    public function __construct(?AdminActionGuard $guard = null)
    {
        $this->guard = $guard ?? AdminActionGuard::forWordPress();
    }

    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_post_' . self::SAVE_ACTION, [$this, 'save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenu(): void
    {
        if (function_exists('add_menu_page')) {
            add_menu_page(
                'Niche Clever Interactive Core',
                'NCI Core',
                'manage_options',
                self::PAGE_SLUG,
                [$this, 'render'],
                'dashicons-format-chat',
                58
            );
        }
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . self::PAGE_SLUG || !function_exists('wp_enqueue_style') || !function_exists('plugins_url')) {
            return;
        }

        wp_enqueue_style('ncli-core-admin', plugins_url('assets/css/admin.css', dirname(__DIR__, 2) . '/niche-clever-interactive-core.php'), [], NCLI_CORE_VERSION);
    }

    public function render(): void
    {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            $this->deny();
            return;
        }

        $settings = $this->load();
        $tab = $this->currentTab();
        $readiness = Readiness::evaluate($settings, new SecretResolver());
        $tabs = [
            'setup' => 'Setup',
            'profile' => 'Profile',
            'ai-evidence' => 'AI & Evidence',
            'conversation' => 'Conversation',
            'appearance-limits' => 'Appearance & Limits',
            'placement' => 'Placement',
            'diagnostics' => 'Diagnostics',
            'help' => 'Help',
        ];

        echo '<div class="wrap ncli-admin ncli-admin--workspace">';
        echo '<h1 class="screen-reader-text">Niche Clever Interactive Core workspace</h1>';
        echo '<header class="ncli-admin__masthead"><img class="ncli-admin__masthead-art" src="' . $this->escapeAttribute($this->assetUrl('assets/images/ncli-admin-banner.png')) . '" alt="" width="1544" height="500"></header>';
        echo '<nav class="nav-tab-wrapper ncli-admin__tabs" aria-label="NCI Core settings">';
        foreach ($tabs as $id => $label) {
            $class = $id === $tab ? ' nav-tab-active' : '';
            $url = $this->adminUrl(['page' => self::PAGE_SLUG, 'tab' => $id]);
            echo '<a class="nav-tab' . $this->escapeAttribute($class) . '" href="' . $this->escapeAttribute($url) . '">' . $this->escapeHtml($label) . '</a>';
        }
        echo '</nav>';

        $notice = $this->currentNotice();
        if ($notice !== '') {
            echo '<div class="notice ' . ($notice === 'saved' ? 'notice-success' : 'notice-error') . '"><p>' . $this->escapeHtml($notice === 'saved' ? 'Core settings saved.' : 'Core settings could not be saved. Review the bounded field values and try again.') . '</p></div>';
        }

        if ($tab === 'diagnostics') {
            $this->renderDiagnostics($readiness);
        } elseif ($tab === 'help') {
            $this->renderHelp();
        } elseif ($tab === 'placement') {
            echo '<section class="ncli-admin__tab-panel"><h2>Placement</h2><p>Use the <code>[ncli_interactive]</code> shortcode or the NCI Interactive block. Contextual and collection placement belongs to Pro.</p></section>';
        } else {
            $this->renderForm($settings, $tab);
        }

        echo '</div>';
    }

    public function save(): void
    {
        $nonce = $_POST['_ncli_nonce'] ?? null;
        if (!$this->guard->allows(self::SAVE_ACTION, $nonce)) {
            $this->deny();
            return;
        }

        $input = $this->requestInput();
        try {
            $settings = CoreSettings::fromArray($input);
            update_option(CoreSettings::OPTION_NAME, $settings->toArray(), false);
            $this->redirect(['page' => self::PAGE_SLUG, 'tab' => $this->safeTab($_POST['ncli_tab'] ?? 'setup'), 'ncli_notice' => 'saved']);
        } catch (\InvalidArgumentException $exception) {
            $this->redirect(['page' => self::PAGE_SLUG, 'tab' => $this->safeTab($_POST['ncli_tab'] ?? 'setup'), 'ncli_notice' => 'invalid']);
        }
    }

    private function renderForm(CoreSettings $settings, string $tab): void
    {
        $values = $settings->toArray();
        echo '<form id="ncli-core-save" class="ncli-admin__workspace" method="post" action="' . $this->escapeAttribute($this->postUrl()) . '">';
        echo '<input type="hidden" name="action" value="' . self::SAVE_ACTION . '">';
        echo '<input type="hidden" name="ncli_tab" value="' . $this->escapeAttribute($tab) . '">';
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field(self::SAVE_ACTION, '_ncli_nonce');
        }

        if ($tab === 'setup') {
            $this->renderSetupChecklist($settings);
        }

        echo '<table class="form-table ncli-admin__form-table" role="presentation"><tbody>';
        if ($tab === 'setup' || $tab === 'profile') {
            echo '<input type="hidden" name="enabled" value="0">';
            $this->checkbox('enabled', 'Enable the Core profile', (bool) $values['enabled']);
            $this->text('corpus_version', 'Corpus version', (string) $values['profile']['corpus_version']);
        }

        if ($tab === 'profile') {
            $this->text('presentation_title', 'Panel title', (string) $values['presentation']['title']);
            $this->text('presentation_headline', 'Panel headline', (string) $values['presentation']['headline']);
            $this->textarea('presentation_introduction', 'Panel introduction', (string) $values['presentation']['introduction']);
        }

        if ($tab === 'ai-evidence') {
            $this->text('vector_store_reference', 'Vector store reference', (string) $values['profile']['vector_store_reference']);
            $this->textarea('approved_evidence_json', 'Approved evidence map JSON', json_encode($values['profile']['approved_evidence'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->descriptionRow('Only bounded public titles and credential-free HTTPS links are accepted.');
        }

        if ($tab === 'conversation') {
            $this->textarea('instructions', 'Bounded owner instructions', (string) $values['instructions']);
            $this->select('response_preset', 'Response preset', ['concise' => 'Concise', 'balanced' => 'Balanced', 'detailed' => 'Detailed'], (string) $values['response_preset']);
            $this->text('presentation_placeholder', 'Question placeholder', (string) $values['presentation']['placeholder']);
            $this->text('presentation_footer_left', 'Panel footer left label', (string) $values['presentation']['footer_left']);
            $this->text('presentation_footer_right', 'Panel footer right label', (string) $values['presentation']['footer_right']);
        }

        if ($tab === 'appearance-limits') {
            $this->select('panel_style', 'Panel style', ['classic' => 'Classic', 'minimal' => 'Minimal', 'dark' => 'Dark'], (string) $values['appearance']['panel_style']);
            $this->number('visitor_requests_per_day', 'Visitor requests per day', (int) $values['limits']['visitor_requests_per_day']);
            $this->number('provider_requests_per_day', 'Provider requests per day', (int) $values['limits']['provider_requests_per_day']);
        }

        echo '</tbody></table>';

        if ($tab === 'setup' || $tab === 'profile' || $tab === 'ai-evidence' || $tab === 'conversation' || $tab === 'appearance-limits') {
            echo '<div class="ncli-admin__save-bar"><button type="submit" class="button button-primary">Save Core settings</button><a class="ncli-admin__brand" href="https://nicheclever.co.uk/" target="_blank" rel="noopener"><img src="' . $this->escapeAttribute($this->assetUrl('assets/images/ncli-logo.png')) . '" alt="Niche Clever" width="180" height="35"></a></div>';
        }
        echo '</form>';
    }

    private function renderDiagnostics(Readiness $readiness): void
    {
        echo '<section class="ncli-admin__tab-panel"><h2>Diagnostics</h2>';
        echo '<p>Readiness: <strong>' . $this->escapeHtml($readiness->state()) . '</strong></p>';
        echo '<p>No credential, provider payload, file identifier, vector-store identifier or retrieved content is shown in diagnostics.</p></section>';
    }

    private function renderHelp(): void
    {
        echo '<section class="ncli-admin__tab-panel"><h2>Help</h2>';
        echo '<p>Core provides one default profile. Configure the corpus version, vector-store reference and approved public evidence map before enabling responses.</p>';
        echo '<p>OpenAI Responses and File Search are external services. Provider configuration remains server-side and public responses remain evidence-bounded.</p></section>';
    }

    private function renderSetupChecklist(CoreSettings $settings): void
    {
        $readiness = Readiness::evaluate($settings, new SecretResolver());
        $state = $readiness->isReady() ? 'Ready' : 'Needs configuration';
        $detail = $readiness->isReady()
            ? 'The bounded Core profile can accept verified public questions.'
            : 'Complete the Core profile and server-side provider configuration before enabling public answers.';

        echo '<section class="ncli-admin__get-started"><h2><span>Get started</span><span class="ncli-admin__get-started-count">3 Core steps</span></h2>';
        echo '<p>Core serves one carefully bounded public profile. It does not expose provider details or add Pro-only workspace controls.</p>';
        echo '<ol class="ncli-admin__checklist"><li><span class="ncli-admin__check">1</span><div><strong>Profile</strong><p>Record the current corpus version and public panel labels.</p></div></li><li><span class="ncli-admin__check">2</span><div><strong>Evidence</strong><p>Approve only the titles and HTTPS links that may appear publicly.</p></div></li><li><span class="ncli-admin__check">3</span><div><strong>Readiness</strong><p>' . $this->escapeHtml($detail) . '</p></div></li></ol></section>';
    }

    private function currentNotice(): string
    {
        $notice = $_GET['ncli_notice'] ?? '';
        return is_string($notice) && in_array($notice, ['saved', 'invalid'], true) ? $notice : '';
    }

    private function assetUrl(string $path): string
    {
        if (!function_exists('plugins_url')) {
            return '';
        }

        return plugins_url($path, dirname(__DIR__, 2) . '/niche-clever-interactive-core.php');
    }

    private function load(): CoreSettings
    {
        $stored = function_exists('get_option') ? get_option(CoreSettings::OPTION_NAME, CoreSettings::defaults()) : CoreSettings::defaults();
        return CoreSettings::fromArray(is_array($stored) ? $stored : CoreSettings::defaults());
    }

    /**
     * @return array<string, mixed>
     */
    private function requestInput(): array
    {
        $existing = $this->load()->toArray();
        $rawEvidence = $_POST['approved_evidence_json'] ?? '{}';
        $evidence = array_key_exists('approved_evidence_json', $_POST) && is_string($rawEvidence)
            ? json_decode($this->unslash($rawEvidence), true)
            : $existing['profile']['approved_evidence'];
        if (!is_array($evidence)) {
            $evidence = ['invalid' => ['title' => '', 'url' => '']];
        }

        return [
            'enabled' => isset($_POST['enabled']) ? $_POST['enabled'] === '1' : $existing['enabled'],
            'profile' => [
                'id' => CoreSettings::DEFAULT_PROFILE_ID,
                'corpus_version' => $this->stringPost('corpus_version', $existing['profile']['corpus_version']),
                'vector_store_reference' => $this->stringPost('vector_store_reference', $existing['profile']['vector_store_reference']),
                'approved_evidence' => $evidence,
            ],
            'instructions' => $this->stringPost('instructions', $existing['instructions']),
            'response_preset' => $this->stringPost('response_preset', $existing['response_preset']),
            'limits' => [
                'visitor_requests_per_day' => $this->intPost('visitor_requests_per_day', $existing['limits']['visitor_requests_per_day']),
                'provider_requests_per_day' => $this->intPost('provider_requests_per_day', $existing['limits']['provider_requests_per_day']),
            ],
            'appearance' => ['panel_style' => $this->stringPost('panel_style', $existing['appearance']['panel_style'])],
            'presentation' => [
                'title' => $this->stringPost('presentation_title', $existing['presentation']['title']),
                'headline' => $this->stringPost('presentation_headline', $existing['presentation']['headline']),
                'introduction' => $this->stringPost('presentation_introduction', $existing['presentation']['introduction']),
                'placeholder' => $this->stringPost('presentation_placeholder', $existing['presentation']['placeholder']),
                'footer_left' => $this->stringPost('presentation_footer_left', $existing['presentation']['footer_left']),
                'footer_right' => $this->stringPost('presentation_footer_right', $existing['presentation']['footer_right']),
            ],
        ];
    }

    private function currentTab(): string
    {
        return $this->safeTab($_GET['tab'] ?? 'setup');
    }

    private function safeTab($value): string
    {
        $valid = ['setup', 'profile', 'ai-evidence', 'conversation', 'appearance-limits', 'placement', 'diagnostics', 'help'];
        return is_string($value) && in_array($value, $valid, true) ? $value : 'setup';
    }

    private function checkbox(string $name, string $label, bool $checked): void
    {
        echo '<tr class="ncli-admin__field"><th scope="row">' . $this->escapeHtml($label) . '</th><td><label><input type="checkbox" name="' . $this->escapeAttribute($name) . '" value="1"' . ($checked ? ' checked' : '') . '> Enable public answers for the default Core profile.</label></td></tr>';
    }

    private function text(string $name, string $label, string $value): void
    {
        echo '<tr class="ncli-admin__field"><th scope="row"><label for="ncli-' . $this->escapeAttribute($name) . '">' . $this->escapeHtml($label) . '</label></th><td><input id="ncli-' . $this->escapeAttribute($name) . '" class="regular-text" type="text" name="' . $this->escapeAttribute($name) . '" value="' . $this->escapeAttribute($value) . '"></td></tr>';
    }

    private function textarea(string $name, string $label, string $value): void
    {
        echo '<tr class="ncli-admin__field"><th scope="row"><label for="ncli-' . $this->escapeAttribute($name) . '">' . $this->escapeHtml($label) . '</label></th><td><textarea id="ncli-' . $this->escapeAttribute($name) . '" class="large-text" rows="8" name="' . $this->escapeAttribute($name) . '">' . $this->escapeHtml($value) . '</textarea></td></tr>';
    }

    /**
     * @param array<string, string> $options
     */
    private function select(string $name, string $label, array $options, string $selected): void
    {
        echo '<tr class="ncli-admin__field"><th scope="row"><label for="ncli-' . $this->escapeAttribute($name) . '">' . $this->escapeHtml($label) . '</label></th><td><select id="ncli-' . $this->escapeAttribute($name) . '" name="' . $this->escapeAttribute($name) . '">';
        foreach ($options as $value => $text) {
            echo '<option value="' . $this->escapeAttribute($value) . '"' . ($selected === $value ? ' selected' : '') . '>' . $this->escapeHtml($text) . '</option>';
        }
        echo '</select></td></tr>';
    }

    private function number(string $name, string $label, int $value): void
    {
        echo '<tr class="ncli-admin__field"><th scope="row"><label for="ncli-' . $this->escapeAttribute($name) . '">' . $this->escapeHtml($label) . '</label></th><td><input id="ncli-' . $this->escapeAttribute($name) . '" type="number" min="1" name="' . $this->escapeAttribute($name) . '" value="' . $value . '"></td></tr>';
    }

    private function descriptionRow(string $message): void
    {
        echo '<tr class="ncli-admin__field ncli-admin__field--description"><th scope="row"></th><td><p class="description">' . $this->escapeHtml($message) . '</p></td></tr>';
    }

    private function redirect(array $query): void
    {
        $url = $this->adminUrl($query);
        if (function_exists('wp_safe_redirect')) {
            wp_safe_redirect($url);
            exit;
        }
    }

    private function deny(): void
    {
        if (function_exists('wp_die')) {
            wp_die('You are not permitted to change NCI Core settings.');
        }
    }

    private function adminUrl(array $query): string
    {
        if (function_exists('admin_url') && function_exists('add_query_arg')) {
            return add_query_arg($query, admin_url('admin.php'));
        }

        return '/wp-admin/admin.php';
    }

    private function postUrl(): string
    {
        return function_exists('admin_url') ? admin_url('admin-post.php') : '/wp-admin/admin-post.php';
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

    private function escapeHtml(string $value): string
    {
        return function_exists('esc_html') ? esc_html($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function escapeAttribute(string $value): string
    {
        return function_exists('esc_attr') ? esc_attr($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
