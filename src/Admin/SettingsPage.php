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
    }

    public function addMenu(): void
    {
        if (function_exists('add_options_page')) {
            add_options_page(
                'Niche Clever Interactive Core',
                'NCI Core',
                'manage_options',
                self::PAGE_SLUG,
                [$this, 'render']
            );
        }
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

        echo '<div class="wrap ncli-admin">';
        echo '<h1>Niche Clever Interactive Core</h1>';
        echo '<p>One Core profile. Provider credentials are server constants and are never displayed here.</p>';
        echo '<nav class="nav-tab-wrapper" aria-label="NCI Core settings">';
        foreach ($tabs as $id => $label) {
            $class = $id === $tab ? ' nav-tab-active' : '';
            $url = $this->adminUrl(['page' => self::PAGE_SLUG, 'tab' => $id]);
            echo '<a class="nav-tab' . $this->escapeAttribute($class) . '" href="' . $this->escapeAttribute($url) . '">' . $this->escapeHtml($label) . '</a>';
        }
        echo '</nav>';

        if ($tab === 'diagnostics') {
            $this->renderDiagnostics($readiness);
        } elseif ($tab === 'help') {
            $this->renderHelp();
        } elseif ($tab === 'placement') {
            echo '<p>Use the ncli_interactive shortcode or the NCI Interactive block. Contextual and collection placement belongs to Pro.</p>';
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
        echo '<form method="post" action="' . $this->escapeAttribute($this->postUrl()) . '">';
        echo '<input type="hidden" name="action" value="' . self::SAVE_ACTION . '">';
        echo '<input type="hidden" name="ncli_tab" value="' . $this->escapeAttribute($tab) . '">';
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field(self::SAVE_ACTION, '_ncli_nonce');
        }

        if ($tab === 'setup' || $tab === 'profile') {
            echo '<input type="hidden" name="enabled" value="0">';
            $this->checkbox('enabled', 'Enable the Core profile', (bool) $values['enabled']);
            $this->text('corpus_version', 'Corpus version', (string) $values['profile']['corpus_version']);
        }

        if ($tab === 'ai-evidence') {
            $this->text('vector_store_reference', 'Vector store reference', (string) $values['profile']['vector_store_reference']);
            $this->textarea('approved_evidence_json', 'Approved evidence map JSON', json_encode($values['profile']['approved_evidence'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            echo '<p class="description">Only bounded public titles and credential-free HTTPS links are accepted.</p>';
        }

        if ($tab === 'conversation') {
            $this->textarea('instructions', 'Bounded owner instructions', (string) $values['instructions']);
            $this->select('response_preset', 'Response preset', ['concise' => 'Concise', 'balanced' => 'Balanced', 'detailed' => 'Detailed'], (string) $values['response_preset']);
        }

        if ($tab === 'appearance-limits') {
            $this->select('panel_style', 'Panel style', ['classic' => 'Classic', 'minimal' => 'Minimal', 'editorial' => 'Editorial'], (string) $values['appearance']['panel_style']);
            $this->number('visitor_requests_per_day', 'Visitor requests per day', (int) $values['limits']['visitor_requests_per_day']);
            $this->number('provider_requests_per_day', 'Provider requests per day', (int) $values['limits']['provider_requests_per_day']);
        }

        if ($tab === 'setup' || $tab === 'profile' || $tab === 'ai-evidence' || $tab === 'conversation' || $tab === 'appearance-limits') {
            if (function_exists('submit_button')) {
                submit_button('Save Core settings');
            }
        }
        echo '</form>';
    }

    private function renderDiagnostics(Readiness $readiness): void
    {
        echo '<h2>Diagnostics</h2>';
        echo '<p>Readiness: <strong>' . $this->escapeHtml($readiness->state()) . '</strong></p>';
        echo '<p>No credential, provider payload, file identifier, vector-store identifier or retrieved content is shown in diagnostics.</p>';
    }

    private function renderHelp(): void
    {
        echo '<h2>Help</h2>';
        echo '<p>Core provides one default profile. Configure the corpus version, vector-store reference and approved public evidence map before enabling responses.</p>';
        echo '<p>OpenAI Responses and File Search are external services. This development build does not yet make provider calls.</p>';
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
        echo '<p><label><input type="checkbox" name="' . $this->escapeAttribute($name) . '" value="1"' . ($checked ? ' checked' : '') . '> ' . $this->escapeHtml($label) . '</label></p>';
    }

    private function text(string $name, string $label, string $value): void
    {
        echo '<p><label>' . $this->escapeHtml($label) . '<br><input class="regular-text" type="text" name="' . $this->escapeAttribute($name) . '" value="' . $this->escapeAttribute($value) . '"></label></p>';
    }

    private function textarea(string $name, string $label, string $value): void
    {
        echo '<p><label>' . $this->escapeHtml($label) . '<br><textarea class="large-text" rows="8" name="' . $this->escapeAttribute($name) . '">' . $this->escapeHtml($value) . '</textarea></label></p>';
    }

    /**
     * @param array<string, string> $options
     */
    private function select(string $name, string $label, array $options, string $selected): void
    {
        echo '<p><label>' . $this->escapeHtml($label) . '<br><select name="' . $this->escapeAttribute($name) . '">';
        foreach ($options as $value => $text) {
            echo '<option value="' . $this->escapeAttribute($value) . '"' . ($selected === $value ? ' selected' : '') . '>' . $this->escapeHtml($text) . '</option>';
        }
        echo '</select></label></p>';
    }

    private function number(string $name, string $label, int $value): void
    {
        echo '<p><label>' . $this->escapeHtml($label) . '<br><input type="number" min="1" name="' . $this->escapeAttribute($name) . '" value="' . $value . '"></label></p>';
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
            return add_query_arg($query, admin_url('options-general.php'));
        }

        return '/wp-admin/options-general.php';
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
