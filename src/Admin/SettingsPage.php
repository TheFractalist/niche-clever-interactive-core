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
                __('Niche Clever Interactive', 'niche-clever-interactive'),
                __('NC Interactive', 'niche-clever-interactive'),
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
                __('Niche Clever Interactive', 'niche-clever-interactive'),
                $this->hubActive() ? __('Profiles', 'niche-clever-interactive') : __('Settings', 'niche-clever-interactive'),
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
            'setup' => __('Setup', 'niche-clever-interactive'),
            'profile' => __('Profile', 'niche-clever-interactive'),
            'ai-corpus' => __('AI & Corpus', 'niche-clever-interactive'),
            'conversation' => __('Conversation', 'niche-clever-interactive'),
            'appearance-limits' => __('Appearance & Limits', 'niche-clever-interactive'),
            'placement' => __('Placement', 'niche-clever-interactive'),
            'diagnostics' => __('Diagnostics', 'niche-clever-interactive'),
            'help' => __('Help', 'niche-clever-interactive'),
        ];
        if ($this->hubActive()) {
            // Help lives on the site Settings page while the hub is active.
            unset($tabs['help']);
            $tabs = $this->hub->tabs(CoreSettings::DEFAULT_PROFILE_ID, $tabs);
        }

        echo '<div class="wrap ncli-admin ncli-admin--workspace">';
        echo '<h1 class="screen-reader-text">' . esc_html(__('Niche Clever Interactive workspace', 'niche-clever-interactive')) . '</h1>';
        if ($this->hubActive()) {
            $this->hub->header(CoreSettings::DEFAULT_PROFILE_ID, __('Default profile', 'niche-clever-interactive'));
        }
        echo '<header class="ncli-admin__masthead"><img class="ncli-admin__masthead-art" src="' . esc_attr($this->assetUrl('assets/images/ncli-admin-banner.png')) . '" alt="" width="1544" height="500"></header>';
        echo '<nav class="nav-tab-wrapper ncli-admin__tabs" aria-label="' . esc_attr(__('Niche Clever Interactive settings', 'niche-clever-interactive')) . '">';
        foreach ($tabs as $id => $label) {
            $class = $id === $tab ? ' nav-tab-active' : '';
            $url = $this->adminUrl(['page' => self::PAGE_SLUG, 'tab' => $id]);
            echo '<a class="nav-tab' . esc_attr($class) . '" href="' . esc_attr($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        $notice = $this->currentNotice();
        if ($notice !== '') {
            $messages = [
                'saved' => __('Settings saved.', 'niche-clever-interactive'),
                'invalid' => __('Settings could not be saved. Review the field values and try again.', 'niche-clever-interactive'),
                'connection-passed' => __('Connection test passed. Public chat can become ready when the other requirements are met.', 'niche-clever-interactive'),
                'connection-failed' => __('Connection test did not complete. Check server configuration and try again without exposing provider details here.', 'niche-clever-interactive'),
            ];
            $success = in_array($notice, ['saved', 'connection-passed'], true);
            echo '<div class="notice ' . ($success ? 'notice-success' : 'notice-error') . '"><p>' . esc_html($messages[$notice]) . '</p></div>';
        }

        // The default profile uses the same optional General/sub-tab layout as named profiles.
        $sub = $this->hubActive() ? $this->hub->subTab(CoreSettings::DEFAULT_PROFILE_ID, $tab) : null;
        if ($sub !== null) {
            $this->hub->subTabStrip(CoreSettings::DEFAULT_PROFILE_ID, $tab, $sub);
        }

        if ($tab === 'diagnostics') {
            $this->renderDiagnostics($readiness);
        } elseif ($tab === 'help') {
            $this->help->render();
        } elseif ($tab === 'placement') {
            echo '<section class="ncli-admin__tab-panel"><h2>' . esc_html(__('Placement', 'niche-clever-interactive')) . '</h2><p>' . esc_html(__('Use the', 'niche-clever-interactive')) . ' <code>[ncli_interactive]</code> ' . esc_html(__('shortcode or the NC Interactive block. Contextual and collection placement is available in NCI Premium.', 'niche-clever-interactive')) . '</p></section>';
        } elseif ($tab === 'questions' || $tab === 'collections' || $tab === 'cache') {
            // Only the extension's sections, drawn below.
        } elseif ($sub === null || $sub === 'panel') {
            $this->renderForm($settings, $tab);
        }

        if ($this->hubActive()) {
            $this->hub->sections(CoreSettings::DEFAULT_PROFILE_ID, $tab, $sub);
        }

        echo '</div>';
    }

    public function save(): void
    {
        $nonce = AdminRequest::postText('_ncli_nonce');
        if (!$this->guard->allows(self::SAVE_ACTION, $nonce)) {
            $this->deny();
            return;
        }

        $input = $this->requestInput();
        try {
            $settings = CoreSettings::fromArray($input);
            update_option(CoreSettings::OPTION_NAME, $settings->toArray(), false);
            $this->redirect(['page' => self::PAGE_SLUG, 'tab' => $this->safeTab(AdminRequest::postText('ncli_tab')), 'ncli_notice' => 'saved']);
        } catch (\InvalidArgumentException $exception) {
            $this->redirect(['page' => self::PAGE_SLUG, 'tab' => $this->safeTab(AdminRequest::postText('ncli_tab')), 'ncli_notice' => 'invalid']);
        }
    }

    public function testConnection(): void
    {
        $nonce = AdminRequest::postText('_ncli_connection_nonce');
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
            echo '<form id="ncli-connection-test" method="post" action="' . esc_attr($this->postUrl()) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::CONNECTION_TEST_ACTION) . '">';
            if (function_exists('wp_nonce_field')) {
                wp_nonce_field(self::CONNECTION_TEST_ACTION, '_ncli_connection_nonce');
            }
            echo '</form>';
        }
        echo '<form id="ncli-core-save" class="ncli-admin__workspace" method="post" action="' . esc_attr($this->postUrl()) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::SAVE_ACTION) . '">';
        echo '<input type="hidden" name="ncli_tab" value="' . esc_attr($tab) . '">';
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
            echo '<div class="ncli-admin__save-bar"><button type="submit" class="button button-primary">' . esc_html(__('Save settings', 'niche-clever-interactive')) . '</button>' . ($this->hubActive() ? '<a class="button" href="' . esc_attr($this->hub->landingUrl()) . '">' . esc_html(__('All profiles', 'niche-clever-interactive')) . '</a>' : '') . ($this->hubActive() ? '' : '<a class="ncli-admin__upgrade" href="' . esc_attr(HelpRenderer::PREMIUM_URL) . '" target="_blank" rel="noopener">' . esc_html(__('Explore NCI Premium', 'niche-clever-interactive')) . '</a>') . '<a class="ncli-admin__brand" href="https://nicheclever.co.uk/" target="_blank" rel="noopener"><img src="' . esc_attr($this->assetUrl('assets/images/ncli-logo.png')) . '" alt="' . esc_attr(__('Niche Clever', 'niche-clever-interactive')) . '" width="180" height="35"></a></div>';
        }
        echo '</form>';
    }

    private function renderDiagnostics(Readiness $readiness): void
    {
        echo '<section class="ncli-admin__tab-panel"><h2>' . esc_html(__('Diagnostics', 'niche-clever-interactive')) . '</h2>';
        echo '<p>' . esc_html(__('Readiness:', 'niche-clever-interactive')) . ' <strong>' . esc_html($readiness->state()) . '</strong></p>';
        $settings = $this->load();
        $status = $settings->connectionTestStatus();
        $time = $settings->connectionTestTime();
        echo '<p>' . esc_html(__('Connection test:', 'niche-clever-interactive')) . ' <strong>' . esc_html($status) . '</strong>' . ($time !== '' ? ' (' . esc_html(__('UTC', 'niche-clever-interactive')) . ' ' . esc_html($time) . ')' : '') . '</p>';
        $this->renderLastFailure();
        echo '<p>' . esc_html(__('No credential, provider payload, file identifier, vector-store identifier, fingerprint or retrieved content is shown in diagnostics.', 'niche-clever-interactive')) . '</p></section>';
    }

    private function renderLastFailure(): void
    {
        $last = $this->failures->last();
        echo '<h3>' . esc_html(__('Last provider problem', 'niche-clever-interactive')) . '</h3>';
        if ($last === null) {
            echo '<p>' . esc_html(__('No provider problem has been recorded.', 'niche-clever-interactive')) . '</p>';
            return;
        }
        $where = $last['source'] === 'connection_test' ? __('connection test', 'niche-clever-interactive') : __('public question', 'niche-clever-interactive');
        echo '<p><strong>' . esc_html($this->t(ProviderFailure::label($last['reason']))) . '</strong> (' . esc_html($last['reason']) . ')</p>';
        echo '<p>' . esc_html(__('Seen in a', 'niche-clever-interactive')) . ' ' . esc_html($where) . ', ' . esc_html(__('UTC', 'niche-clever-interactive')) . ' ' . esc_html($last['at']) . ', ' . esc_html(__('after about', 'niche-clever-interactive')) . ' ' . esc_html((string) $last['elapsed']) . ' ' . esc_html(__('seconds', 'niche-clever-interactive')) . '.</p>';
        echo '<p>' . esc_html(__('What to try:', 'niche-clever-interactive')) . ' ' . esc_html($this->t(ProviderFailure::advice($last['reason']))) . '</p>';
        echo '<p>' . esc_html(__('Only the reason, time and duration are kept. Visitors always see the same generic message. Only the latest problem is shown.', 'niche-clever-interactive')) . '</p>';
    }

    private function renderSetupChecklist(CoreSettings $settings): void
    {
        $readiness = Readiness::evaluate($settings, new SecretResolver());
        $secrets = new SecretResolver();
        $keyReady = $secrets->isAvailable();
        $keyState = $keyReady ? __('The server-side constant is available.', 'niche-clever-interactive') : __('Add the constant to the server configuration before testing.', 'niche-clever-interactive');
        $corpusReady = $settings->vectorStoreReference() !== '' && $settings->corpusVersion() !== '';
        $corpusState = $corpusReady
            ? __('A vector-store reference and corpus version are saved.', 'niche-clever-interactive')
            : __('Save the vector-store reference in AI & Corpus and the corpus version in Profile.', 'niche-clever-interactive');
        $testReady = $settings->connectionTestStatus() === 'passed' && $readiness->isReady();
        $testState = $testReady
            ? __('The current configuration passed its File Search connection test.', 'niche-clever-interactive')
            : __('Run a new test after changing the key, transport mode, vector store, corpus version, response guidance, response preset or any model setting.', 'niche-clever-interactive');
        $placements = $this->publishedPlacements();
        $placementReady = $placements !== [];
        $placementState = $placementReady
            ? __('Detected placement:', 'niche-clever-interactive') . ' ' . implode(', ', $placements) . '.'
            : esc_html(__('Add the NC Interactive block or', 'niche-clever-interactive')) . ' <code>[ncli_interactive]</code> ' . esc_html(__('shortcode to a published page.', 'niche-clever-interactive'));
        $items = [
            [__('Add your API key', 'niche-clever-interactive'), esc_html($keyState) . ' ' . esc_html(__('Configure', 'niche-clever-interactive')) . ' <code>NCLI_OPENAI_API_KEY_DEFAULT</code> ' . esc_html(__('in server configuration only, for example', 'niche-clever-interactive')) . ' <code>define(\'NCLI_OPENAI_API_KEY_DEFAULT\', \'…\');</code>.', $keyReady, ''],
            [__('Choose your vector store', 'niche-clever-interactive'), esc_html($corpusState) . ' <a href="' . esc_attr($this->adminUrl(['page' => self::PAGE_SLUG, 'tab' => 'ai-corpus'])) . '">' . esc_html(__('Open AI & Corpus', 'niche-clever-interactive')) . '</a>.', $corpusReady, ''],
            [__('Test your connection', 'niche-clever-interactive'), esc_html($testState), $testReady, '<button type="submit" class="button button-secondary" form="ncli-connection-test">' . esc_html(__('Test connection', 'niche-clever-interactive')) . '</button>'],
            [__('Place your conversation', 'niche-clever-interactive'), $placementState . ' <a href="' . esc_attr($this->adminUrl(['page' => self::PAGE_SLUG, 'tab' => 'placement'])) . '">' . esc_html(__('Open placement guidance', 'niche-clever-interactive')) . '</a>.', $placementReady, ''],
        ];

        $done = count(array_filter($items, static fn (array $item): bool => $item[2]));
        /* translators: 1: completed setup steps, 2: total setup steps. */
        echo '<section class="ncli-admin__get-started"><h2><span>' . esc_html(__('Get started', 'niche-clever-interactive')) . '</span><span class="ncli-admin__get-started-count">' . esc_html(sprintf(__('%1$d of %2$d complete', 'niche-clever-interactive'), $done, count($items))) . '</span></h2>';
        echo '<p>' . esc_html(__('Niche Clever Interactive serves one carefully bounded public profile. It does not expose provider details.', 'niche-clever-interactive')) . '</p>';
        echo '<ol class="ncli-admin__checklist">';
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
        echo '<li class="ncli-admin__checklist-item ncli-admin__checklist-item--' . esc_attr($class) . '"><span class="ncli-admin__check ncli-admin__check--' . esc_attr($class) . '"><span aria-hidden="true">' . esc_html($symbol) . '</span><span class="screen-reader-text">' . esc_html($state) . '</span></span><div class="ncli-admin__checklist-copy"><div class="ncli-admin__checklist-heading"><strong>' . esc_html($title) . '</strong>' . ($action !== '' ? '<span class="ncli-admin__checklist-action">' . wp_kses($action, ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols()) . '</span>' : '') . '</div><p>' . wp_kses($description, ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols()) . '</p></div></li>';
    }

    private function currentNotice(): string
    {
        $notice = AdminRequest::queryText('ncli_notice');
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
            if (!$this->contentUsesDefaultProfile($post->post_content)) {
                continue;
            }
            $title = is_string($post->post_title) && $post->post_title !== '' ? $post->post_title : __('Published page', 'niche-clever-interactive');
            $url = function_exists('get_edit_post_link') ? get_edit_post_link((int) $post->ID, 'raw') : false;
            $placements[] = is_string($url) && $url !== '' ? '<a href="' . esc_attr($url) . '">' . esc_html($title) . '</a>' : esc_html($title);
        }
        return $placements;
    }

    /** A default-profile checklist must not be completed by a named-profile placement. */
    private function contentUsesDefaultProfile(string $content): bool
    {
        if (preg_match_all('/\\[ncli_interactive\\b([^\\]]*)\\]/i', $content, $shortcodes, PREG_SET_ORDER) === 1 || !empty($shortcodes)) {
            foreach ($shortcodes as $shortcode) {
                $attributes = $shortcode[1] ?? '';
                if (!is_string($attributes) || preg_match("/\\bprofile\\s*=\\s*(?:\"([^\"]*)\"|'([^']*)'|([^\\s\\]]+))/i", $attributes, $profile) !== 1) {
                    return true;
                }
                $selected = $profile[1] !== '' ? $profile[1] : ($profile[2] !== '' ? $profile[2] : $profile[3]);
                if ($selected === '' || $selected === CoreSettings::DEFAULT_PROFILE_ID) {
                    return true;
                }
            }
        }
        if (!function_exists('parse_blocks')) {
            return false;
        }
        $blocks = parse_blocks($content);
        if (!is_array($blocks)) {
            return false;
        }
        $contains = function (array $blocks) use (&$contains): bool {
            foreach ($blocks as $block) {
                if (!is_array($block)) {
                    continue;
                }
                $attrs = $block['attrs'] ?? [];
                if (($block['blockName'] ?? null) === 'ncli/interactive' && is_array($attrs) && (!isset($attrs['profile']) || $attrs['profile'] === CoreSettings::DEFAULT_PROFILE_ID)) {
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
            'enabled' => AdminRequest::postOptionalText('enabled') !== null ? AdminRequest::postCheckbox('enabled') : $existing['enabled'],
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
                'show_credit' => AdminRequest::postOptionalText('show_credit') !== null ? AdminRequest::postCheckbox('show_credit') : $existing['appearance']['show_credit'],
            ],
            'conversation_log' => [
                'enabled' => AdminRequest::postOptionalText('conversation_log_enabled') !== null ? AdminRequest::postCheckbox('conversation_log_enabled') : $existing['conversation_log']['enabled'],
                'retention_days' => $this->intPost('conversation_retention_days', $existing['conversation_log']['retention_days']),
            ],
            'streaming' => [
                'progressive' => AdminRequest::postOptionalText('streaming_progressive') !== null ? AdminRequest::postCheckbox('streaming_progressive') : $existing['streaming']['progressive'],
            ],
            'lifecycle' => [
                'delete_on_uninstall' => AdminRequest::postOptionalText('delete_on_uninstall') !== null ? AdminRequest::postCheckbox('delete_on_uninstall') : $existing['lifecycle']['delete_on_uninstall'],
            ],
            'presentation' => [
                'title' => $this->stringPost('presentation_title', $existing['presentation']['title']),
                'headline' => $this->stringPost('presentation_headline', $existing['presentation']['headline']),
                'introduction' => $this->stringPost('presentation_introduction', $existing['presentation']['introduction']),
                'placeholder' => $this->stringPost('presentation_placeholder', $existing['presentation']['placeholder']),
                'footer_left' => $this->stringPost('presentation_footer_left', $existing['presentation']['footer_left']),
                'footer_right' => $this->stringPost('presentation_footer_right', $existing['presentation']['footer_right']),
                'unavailable_message' => $this->stringPost('presentation_unavailable_message', $existing['presentation']['unavailable_message']),
                'processing_message' => $this->stringPost('presentation_processing_message', $existing['presentation']['processing_message']),
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
        return $this->safeTab(AdminRequest::queryText('tab'));
    }

    private function safeTab($value): string
    {
        $valid = ['setup', 'profile', 'ai-corpus', 'conversation', 'appearance-limits', 'placement', 'diagnostics', 'help'];
        if ($this->hubActive()) {
            // An installed extension may add these two tabs; they carry only its sections.
            $valid = array_merge($valid, ['questions', 'collections', 'cache']);
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
            wp_die(esc_html(__('You are not permitted to change NCI Core settings.', 'niche-clever-interactive')));
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
        $value = in_array($key, ['instructions', 'presentation_introduction'], true)
            ? AdminRequest::postOptionalTextarea($key)
            : AdminRequest::postOptionalText($key);

        return $value ?? $default;
    }

    private function accentPost(string $default): string
    {
        if (AdminRequest::postCheckbox('accent_color_default')) {
            return '';
        }

        return $this->stringPost('accent_color', $default);
    }

    private function intPost(string $key, int $default): int
    {
        return AdminRequest::postPositiveInt($key) ?? $default;
    }

    private function unslash(string $value): string
    {
        return function_exists('wp_unslash') ? wp_unslash($value) : stripslashes($value);
    }

    private function t(string $text): string
    {
        return $text;
    }
}
