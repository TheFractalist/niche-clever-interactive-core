<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;

use NicheCleverInteractive\Core\AI\ConnectionTestService;
use NicheCleverInteractive\Core\AI\Transport\ProviderFailure;
use NicheCleverInteractive\Core\AI\Transport\UnavailableTransport;
use NicheCleverInteractive\Core\Budget\BudgetGate;
use NicheCleverInteractive\Core\Budget\WordPressBucketStore;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\PanelAppearance;
use NicheCleverInteractive\Core\Configuration\ModelCatalogue;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Diagnostics\FailureLog;
use NicheCleverInteractive\Core\Diagnostics\OptionFailureLog;
use NicheCleverInteractive\Core\Security\AdminActionGuard;
use NicheCleverInteractive\Core\Support\Translator;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic markup uses the tested escapeHtml()/escapeAttribute() wrappers, which keep this PHP 8.1-compatible class usable in pure tests.
// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- AdminActionGuard centralises capability and nonce verification before this class reads a request.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- requestInput() unslashes values and CoreSettings performs bounded validation after AdminActionGuard admits the request.
final class SettingsPage
{
    public const PAGE_SLUG = 'ncli-core';
    private const SAVE_ACTION = 'ncli_save_settings';
    private const CONNECTION_TEST_ACTION = 'ncli_test_connection';

    private AdminActionGuard $guard;
    private ConnectionTestService $connectionTest;
    private FailureLog $failures;
    private ProfileFieldRenderer $fields;
    private ?ProfileHub $hub;
    private HelpRenderer $help;

    public function __construct(?AdminActionGuard $guard = null, ?ConnectionTestService $connectionTest = null, ?FailureLog $failures = null, ?ProfileHub $hub = null)
    {
        $this->hub = $hub;
        $this->guard = $guard ?? AdminActionGuard::forWordPress();
        $this->failures = $failures ?? new OptionFailureLog();
        $this->fields = new ProfileFieldRenderer();
        $this->help = new HelpRenderer($this->fields, fn (string $section): string => $this->adminUrl(['page' => self::PAGE_SLUG, 'tab' => 'help', 'help' => $section]));
        $this->connectionTest = $connectionTest ?? new ConnectionTestService(new UnavailableTransport(), new SecretResolver(), new BudgetGate(new WordPressBucketStore()));
    }

    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_post_' . self::SAVE_ACTION, [$this, 'save']);
        add_action('admin_post_' . self::CONNECTION_TEST_ACTION, [$this, 'testConnection']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenu(): void
    {
        if (function_exists('add_menu_page')) {
            add_menu_page(
                $this->t('Niche Clever Interactive'),
                $this->t('NC Interactive'),
                'manage_options',
                self::PAGE_SLUG,
                [$this, 'render'],
                'dashicons-format-chat',
                58
            );
        }

        if (function_exists('add_submenu_page')) {
            // The first submenu entry shares the parent slug so WordPress labels it "Settings"
            // instead of repeating the top-level menu title.
            add_submenu_page(
                self::PAGE_SLUG,
                $this->t('Niche Clever Interactive'),
                $this->hubActive() ? $this->t('Profiles') : $this->t('Settings'),
                'manage_options',
                self::PAGE_SLUG,
                [$this, 'render']
            );
        }
    }

    public function enqueueAssets(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . self::PAGE_SLUG || !function_exists('wp_enqueue_style') || !function_exists('wp_enqueue_script') || !function_exists('plugins_url')) {
            return;
        }

        wp_enqueue_style('ncli-core-admin', plugins_url('assets/css/admin.css', dirname(__DIR__, 2) . '/niche-clever-interactive-core.php'), [], NCLI_CORE_VERSION);
        wp_enqueue_script('ncli-core-admin', plugins_url('assets/js/admin.js', dirname(__DIR__, 2) . '/niche-clever-interactive-core.php'), [], NCLI_CORE_VERSION, true);
    }

    public function render(): void
    {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            $this->deny();
            return;
        }

        if ($this->hubActive() && $this->hub->render()) {
            return;
        }

        $settings = $this->load();
        $tab = $this->currentTab();
        $readiness = Readiness::evaluate($settings, new SecretResolver());
        $tabs = [
            'setup' => $this->t('Setup'),
            'profile' => $this->t('Profile'),
            'ai-corpus' => $this->t('AI & Corpus'),
            'conversation' => $this->t('Conversation'),
            'appearance-limits' => $this->t('Appearance & Limits'),
            'placement' => $this->t('Placement'),
            'diagnostics' => $this->t('Diagnostics'),
            'help' => $this->t('Help'),
        ];
        if ($this->hubActive()) {
            // Help lives on the site Settings page while the hub is active.
            unset($tabs['help']);
            $tabs = $this->hub->tabs(CoreSettings::DEFAULT_PROFILE_ID, $tabs);
        }

        echo '<div class="wrap ncli-admin ncli-admin--workspace">';
        echo '<h1 class="screen-reader-text">' . $this->escapeHtml($this->t('Niche Clever Interactive workspace')) . '</h1>';
        if ($this->hubActive()) {
            $this->hub->header(CoreSettings::DEFAULT_PROFILE_ID, $this->t('Default profile'));
        }
        echo '<header class="ncli-admin__masthead"><img class="ncli-admin__masthead-art" src="' . $this->escapeAttribute($this->assetUrl('assets/images/ncli-admin-banner.png')) . '" alt="" width="1544" height="500"></header>';
        echo '<nav class="nav-tab-wrapper ncli-admin__tabs" aria-label="' . $this->escapeAttribute($this->t('Niche Clever Interactive settings')) . '">';
        foreach ($tabs as $id => $label) {
            $class = $id === $tab ? ' nav-tab-active' : '';
            $url = $this->adminUrl(['page' => self::PAGE_SLUG, 'tab' => $id]);
            echo '<a class="nav-tab' . $this->escapeAttribute($class) . '" href="' . $this->escapeAttribute($url) . '">' . $this->escapeHtml($label) . '</a>';
        }
        echo '</nav>';

        $notice = $this->currentNotice();
        if ($notice !== '') {
            $messages = [
                'saved' => $this->t('Settings saved.'),
                'invalid' => $this->t('Settings could not be saved. Review the field values and try again.'),
                'connection-passed' => $this->t('Connection test passed. Public chat can become ready when the other requirements are met.'),
                'connection-failed' => $this->t('Connection test did not complete. Check server configuration and try again without exposing provider details here.'),
            ];
            $success = in_array($notice, ['saved', 'connection-passed'], true);
            echo '<div class="notice ' . ($success ? 'notice-success' : 'notice-error') . '"><p>' . $this->escapeHtml($messages[$notice]) . '</p></div>';
        }

        if ($tab === 'diagnostics') {
            $this->renderDiagnostics($readiness);
        } elseif ($tab === 'help') {
            $this->help->render();
        } elseif ($tab === 'placement') {
            echo '<section class="ncli-admin__tab-panel"><h2>' . $this->escapeHtml($this->t('Placement')) . '</h2><p>' . $this->escapeHtml($this->t('Use the')) . ' <code>[ncli_interactive]</code> ' . $this->escapeHtml($this->t('shortcode or the NC Interactive block. Contextual and collection placement is available in NCI Premium.')) . '</p></section>';
        } elseif ($tab === 'questions' || $tab === 'cache') {
            // Only the extension's sections, drawn below.
        } else {
            $this->renderForm($settings, $tab);
        }

        if ($this->hubActive()) {
            $this->hub->sections(CoreSettings::DEFAULT_PROFILE_ID, $tab);
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

    public function testConnection(): void
    {
        $nonce = $_POST['_ncli_connection_nonce'] ?? null;
        if (!$this->guard->allows(self::CONNECTION_TEST_ACTION, $nonce)) {
            $this->deny();
            return;
        }

        $settings = $this->load();
        $result = $this->connectionTest->test($settings);
        $updated = $settings->withConnectionTest($result->status(), $result->fingerprint(), gmdate('Y-m-d\\TH:i:s\\Z'));
        update_option(CoreSettings::OPTION_NAME, $updated->toArray(), false);
        $this->redirect([
            'page' => self::PAGE_SLUG,
            'tab' => 'setup',
            'ncli_notice' => $result->passed() ? 'connection-passed' : 'connection-failed',
        ]);
    }

    private function renderForm(CoreSettings $settings, string $tab): void
    {
        $values = $settings->toArray();
        if ($tab === 'setup') {
            echo '<form id="ncli-connection-test" method="post" action="' . $this->escapeAttribute($this->postUrl()) . '">';
            echo '<input type="hidden" name="action" value="' . self::CONNECTION_TEST_ACTION . '">';
            if (function_exists('wp_nonce_field')) {
                wp_nonce_field(self::CONNECTION_TEST_ACTION, '_ncli_connection_nonce');
            }
            echo '</form>';
        }
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
        $this->fields->rows($tab, $values, $this->adminUrl(['page' => ConversationsPage::PAGE_SLUG]), $this->hubActive());
        echo '</tbody></table>';

        if ($tab === 'setup' || $tab === 'profile' || $tab === 'ai-corpus' || $tab === 'conversation' || $tab === 'appearance-limits') {
            echo '<div class="ncli-admin__save-bar"><button type="submit" class="button button-primary">' . $this->escapeHtml($this->t('Save settings')) . '</button>' . ($this->hubActive() ? '<a class="button" href="' . $this->escapeAttribute($this->hub->landingUrl()) . '">' . $this->escapeHtml($this->t('All profiles')) . '</a>' : '') . '<a class="ncli-admin__brand" href="https://nicheclever.co.uk/" target="_blank" rel="noopener"><img src="' . $this->escapeAttribute($this->assetUrl('assets/images/ncli-logo.png')) . '" alt="' . $this->escapeAttribute($this->t('Niche Clever')) . '" width="180" height="35"></a></div>';
        }
        echo '</form>';
    }

    private function renderDiagnostics(Readiness $readiness): void
    {
        echo '<section class="ncli-admin__tab-panel"><h2>' . $this->escapeHtml($this->t('Diagnostics')) . '</h2>';
        echo '<p>' . $this->escapeHtml($this->t('Readiness:')) . ' <strong>' . $this->escapeHtml($readiness->state()) . '</strong></p>';
        $settings = $this->load();
        $status = $settings->connectionTestStatus();
        $time = $settings->connectionTestTime();
        echo '<p>' . $this->escapeHtml($this->t('Connection test:')) . ' <strong>' . $this->escapeHtml($status) . '</strong>' . ($time !== '' ? ' (' . $this->escapeHtml($this->t('UTC')) . ' ' . $this->escapeHtml($time) . ')' : '') . '</p>';
        $this->renderLastFailure();
        echo '<p>' . $this->escapeHtml($this->t('No credential, provider payload, file identifier, vector-store identifier, fingerprint or retrieved content is shown in diagnostics.')) . '</p></section>';
    }

    private function renderLastFailure(): void
    {
        $last = $this->failures->last();
        echo '<h3>' . $this->escapeHtml($this->t('Last provider problem')) . '</h3>';
        if ($last === null) {
            echo '<p>' . $this->escapeHtml($this->t('No provider problem has been recorded.')) . '</p>';
            return;
        }
        $where = $last['source'] === 'connection_test' ? $this->t('connection test') : $this->t('public question');
        echo '<p><strong>' . $this->escapeHtml($this->t(ProviderFailure::label($last['reason']))) . '</strong> (' . $this->escapeHtml($last['reason']) . ')</p>';
        echo '<p>' . $this->escapeHtml($this->t('Seen in a')) . ' ' . $this->escapeHtml($where) . ', ' . $this->escapeHtml($this->t('UTC')) . ' ' . $this->escapeHtml($last['at']) . ', ' . $this->escapeHtml($this->t('after about')) . ' ' . $this->escapeHtml((string) $last['elapsed']) . ' ' . $this->escapeHtml($this->t('seconds')) . '.</p>';
        echo '<p>' . $this->escapeHtml($this->t('What to try:')) . ' ' . $this->escapeHtml($this->t(ProviderFailure::advice($last['reason']))) . '</p>';
        echo '<p>' . $this->escapeHtml($this->t('Only the reason, time and duration are kept. Visitors always see the same generic message. Only the latest problem is shown.')) . '</p>';
    }

    private function renderSetupChecklist(CoreSettings $settings): void
    {
        $readiness = Readiness::evaluate($settings, new SecretResolver());
        $secrets = new SecretResolver();
        $keyState = $secrets->isAvailable() ? $this->t('The server-side constant is available.') : $this->t('Add the constant to the server configuration before testing.');
        $corpusState = $settings->vectorStoreReference() !== '' && $settings->corpusVersion() !== ''
            ? $this->t('A vector-store reference and corpus version are saved.')
            : $this->t('Save the vector-store reference and corpus version in AI & Corpus.');
        $testState = $settings->connectionTestStatus() === 'passed' && $readiness->isReady()
            ? $this->t('The current configuration passed its File Search connection test.')
            : $this->t('Run a new test after changing the key, transport mode, vector store, corpus version, response guidance, response preset or any model setting.');
        $placements = $this->publishedPlacements();

        echo '<section class="ncli-admin__get-started"><h2><span>' . $this->escapeHtml($this->t('Get started')) . '</span><span class="ncli-admin__get-started-count">' . $this->escapeHtml($this->t('4 Core steps')) . '</span></h2>';
        echo '<p>' . $this->escapeHtml($this->t('Niche Clever Interactive serves one carefully bounded public profile. It does not expose provider details.')) . '</p>';
        echo '<ol class="ncli-admin__checklist">';
        echo '<li><span class="ncli-admin__check">1</span><div><strong>' . $this->escapeHtml($this->t('Add your API key')) . '</strong><p>' . $this->escapeHtml($keyState) . ' ' . $this->escapeHtml($this->t('Configure')) . ' <code>NCLI_OPENAI_API_KEY_DEFAULT</code> ' . $this->escapeHtml($this->t('in server configuration only, for example')) . ' <code>define(\'NCLI_OPENAI_API_KEY_DEFAULT\', \'…\');</code>.</p></div></li>';
        echo '<li><span class="ncli-admin__check">2</span><div><strong>' . $this->escapeHtml($this->t('Choose your vector store')) . '</strong><p>' . $this->escapeHtml($corpusState) . ' <a href="' . $this->escapeAttribute($this->adminUrl(['page' => self::PAGE_SLUG, 'tab' => 'ai-corpus'])) . '">' . $this->escapeHtml($this->t('Open AI & Corpus')) . '</a>.</p></div></li>';
        echo '<li><span class="ncli-admin__check">3</span><div><strong>' . $this->escapeHtml($this->t('Test your connection')) . '</strong><p>' . $this->escapeHtml($testState) . '</p><button type="submit" class="button button-secondary" form="ncli-connection-test">' . $this->escapeHtml($this->t('Test connection')) . '</button></div></li>';
        echo '<li><span class="ncli-admin__check">4</span><div><strong>' . $this->escapeHtml($this->t('Place your conversation')) . '</strong><p>';
        if ($placements === []) {
            echo $this->escapeHtml($this->t('Add the NC Interactive block or')) . ' <code>[ncli_interactive]</code> ' . $this->escapeHtml($this->t('shortcode to a published page.'));
        } else {
            echo $this->escapeHtml($this->t('Detected placement:')) . ' ' . implode(', ', $placements) . '.';
        }
        echo ' <a href="' . $this->escapeAttribute($this->adminUrl(['page' => self::PAGE_SLUG, 'tab' => 'placement'])) . '">' . $this->escapeHtml($this->t('Open placement guidance')) . '</a>.</p></div></li>';
        echo '</ol></section>';
    }

    private function currentNotice(): string
    {
        $notice = $_GET['ncli_notice'] ?? '';
        return is_string($notice) && in_array($notice, ['saved', 'invalid', 'connection-passed', 'connection-failed'], true) ? $notice : '';
    }

    /** @return list<string> */
    private function publishedPlacements(): array
    {
        if (!function_exists('get_posts')) {
            return [];
        }
        $posts = get_posts([
            'post_type' => ['page', 'post'],
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);
        if (!is_array($posts)) {
            return [];
        }
        $placements = [];
        foreach ($posts as $post) {
            if (!is_object($post) || !isset($post->post_content, $post->ID, $post->post_title) || !is_string($post->post_content)) {
                continue;
            }
            $content = $post->post_content;
            $isPlaced = (function_exists('has_shortcode') && has_shortcode($content, 'ncli_interactive'))
                || (function_exists('has_block') && has_block('ncli/interactive', $content));
            if (!$isPlaced) {
                continue;
            }
            $title = is_string($post->post_title) && $post->post_title !== '' ? $post->post_title : $this->t('Published page');
            $url = function_exists('get_edit_post_link') ? get_edit_post_link((int) $post->ID, 'raw') : false;
            $placements[] = is_string($url) && $url !== '' ? '<a href="' . $this->escapeAttribute($url) . '">' . $this->escapeHtml($title) . '</a>' : $this->escapeHtml($title);
        }
        return $placements;
    }

    private function assetUrl(string $path): string
    {
        if (!function_exists('plugins_url')) {
            return '';
        }

        return $this->versionedAssetUrl($path);
    }

    /** The asset URL with its file's modification time, so a replaced image is never served from a stale browser cache. */
    private function versionedAssetUrl(string $path): string
    {
        $url = plugins_url($path, dirname(__DIR__, 2) . '/niche-clever-interactive-core.php');
        $file = dirname(__DIR__, 2) . '/' . $path;
        $modified = is_file($file) ? (int) filemtime($file) : 0;

        return $modified > 0 ? $url . (str_contains($url, '?') ? '&' : '?') . 'ver=' . $modified : $url;
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
        return [
            'enabled' => isset($_POST['enabled']) ? $_POST['enabled'] === '1' : $existing['enabled'],
            'profile' => [
                'id' => CoreSettings::DEFAULT_PROFILE_ID,
                'corpus_version' => $this->stringPost('corpus_version', $existing['profile']['corpus_version']),
                'vector_store_reference' => $this->stringPost('vector_store_reference', $existing['profile']['vector_store_reference']),
            ],
            'instructions' => $this->stringPost('instructions', $existing['instructions']),
            'response_preset' => $this->stringPost('response_preset', $existing['response_preset']),
            'model' => $this->modelInput($existing['model']),
            'limits' => [
                'visitor_requests_per_day' => $this->intPost('visitor_requests_per_day', $existing['limits']['visitor_requests_per_day']),
                'provider_requests_per_day' => $this->intPost('provider_requests_per_day', $existing['limits']['provider_requests_per_day']),
            ],
            'appearance' => [
                'panel_style' => $this->stringPost('panel_style', $existing['appearance']['panel_style']),
                'panel_width' => $this->stringPost('panel_width', $existing['appearance']['panel_width']),
                'accent_color' => $this->accentPost($existing['appearance']['accent_color']),
                'show_credit' => isset($_POST['show_credit']) ? $_POST['show_credit'] === '1' : $existing['appearance']['show_credit'],
            ],
            'conversation_log' => [
                'enabled' => isset($_POST['conversation_log_enabled']) ? $_POST['conversation_log_enabled'] === '1' : $existing['conversation_log']['enabled'],
                'retention_days' => $this->intPost('conversation_retention_days', $existing['conversation_log']['retention_days']),
            ],
            'lifecycle' => [
                'delete_on_uninstall' => isset($_POST['delete_on_uninstall']) ? $_POST['delete_on_uninstall'] === '1' : $existing['lifecycle']['delete_on_uninstall'],
            ],
            'presentation' => [
                'title' => $this->stringPost('presentation_title', $existing['presentation']['title']),
                'headline' => $this->stringPost('presentation_headline', $existing['presentation']['headline']),
                'introduction' => $this->stringPost('presentation_introduction', $existing['presentation']['introduction']),
                'placeholder' => $this->stringPost('presentation_placeholder', $existing['presentation']['placeholder']),
                'footer_left' => $this->stringPost('presentation_footer_left', $existing['presentation']['footer_left']),
                'footer_right' => $this->stringPost('presentation_footer_right', $existing['presentation']['footer_right']),
                'unavailable_message' => $this->stringPost('presentation_unavailable_message', $existing['presentation']['unavailable_message']),
            ],
            'diagnostics' => $existing['diagnostics'],
        ];
    }

    /**
     * @param array{id: string, reasoning_effort: string, retrieval_depth: int, max_output_tokens: int, timeout_seconds: int} $existing
     * @return array<string, mixed>
     */
    private function modelInput(array $existing): array
    {
        $id = $this->stringPost('model_id', $existing['id']);
        $effort = $this->stringPost('model_reasoning_effort', $existing['reasoning_effort']);
        if (ModelCatalogue::has($id) && !ModelCatalogue::validEffort($id, $effort)) {
            $effort = ModelCatalogue::DEFAULT_EFFORT;
        }

        return [
            'id' => $id,
            'reasoning_effort' => $effort,
            'retrieval_depth' => $this->intPost('model_retrieval_depth', $existing['retrieval_depth']),
            'max_output_tokens' => $this->intPost('model_max_output_tokens', $existing['max_output_tokens']),
            'timeout_seconds' => $this->intPost('model_timeout_seconds', $existing['timeout_seconds']),
        ];
    }

    private function hubActive(): bool
    {
        return $this->hub !== null && $this->hub->active();
    }

    private function currentTab(): string
    {
        return $this->safeTab($_GET['tab'] ?? 'setup');
    }

    private function safeTab($value): string
    {
        $valid = ['setup', 'profile', 'ai-corpus', 'conversation', 'appearance-limits', 'placement', 'diagnostics', 'help'];
        if ($this->hubActive()) {
            // An installed extension may add these two tabs; they carry only its sections.
            $valid = array_merge($valid, ['questions', 'cache']);
        }
        return is_string($value) && in_array($value, $valid, true) ? $value : 'setup';
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
            wp_die($this->t('You are not permitted to change NCI Core settings.'));
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

    private function accentPost(string $default): string
    {
        if (isset($_POST['accent_color_default']) && $_POST['accent_color_default'] === '1') {
            return '';
        }

        return $this->stringPost('accent_color', $default);
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

    private function t(string $text): string
    {
        return Translator::text($text);
    }
}
