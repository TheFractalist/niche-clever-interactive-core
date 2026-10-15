<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;

use NicheCleverInteractive\Core\AI\ConnectionTestService;
use NicheCleverInteractive\Core\AI\Transport\UnavailableTransport;
use NicheCleverInteractive\Core\Budget\BudgetGate;
use NicheCleverInteractive\Core\Budget\WordPressBucketStore;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\PanelAppearance;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
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

    public function __construct(?AdminActionGuard $guard = null, ?ConnectionTestService $connectionTest = null)
    {
        $this->guard = $guard ?? AdminActionGuard::forWordPress();
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
                $this->t('Settings'),
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

        echo '<div class="wrap ncli-admin ncli-admin--workspace">';
        echo '<h1 class="screen-reader-text">' . $this->escapeHtml($this->t('Niche Clever Interactive workspace')) . '</h1>';
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
            $this->renderHelp();
        } elseif ($tab === 'placement') {
            echo '<section class="ncli-admin__tab-panel"><h2>' . $this->escapeHtml($this->t('Placement')) . '</h2><p>' . $this->escapeHtml($this->t('Use the')) . ' <code>[ncli_interactive]</code> ' . $this->escapeHtml($this->t('shortcode or the NC Interactive block. Contextual and collection placement is planned for NCI Pro.')) . '</p></section>';
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
        if ($tab === 'setup' || $tab === 'profile') {
            if ($tab === 'setup') {
                $this->select('enabled', 'Visitor availability', ['0' => 'Unavailable', '1' => 'Available after a current connection test'], (bool) $values['enabled'] ? '1' : '0', 'Public answers remain unavailable until this setting is enabled and the current configuration has passed a connection test.');
            } else {
                echo '<input type="hidden" name="enabled" value="0">';
                $this->checkbox('enabled', 'Enable the Core profile', (bool) $values['enabled'], 'Enable this only when the saved configuration is ready for public visitors. A current connection test is also required.');
            }
            $this->text('corpus_version', 'Corpus version', (string) $values['profile']['corpus_version'], 'Use your own short version or date label for the prepared material in the connected vector store. Updating it makes the existing connection test stale.');
        }

        if ($tab === 'profile') {
            $this->text('presentation_title', 'Panel title', (string) $values['presentation']['title'], 'The visible title above the public question panel. Keep it short and identify what visitors can ask about.');
            $this->text('presentation_headline', 'Panel headline', (string) $values['presentation']['headline'], 'A short supporting line under the title. It should set expectations without promising that every question can be answered.');
            $this->textarea('presentation_introduction', 'Panel introduction', (string) $values['presentation']['introduction'], 'Optional introductory copy shown before the question box. Do not include confidential information, credentials or unpublished source material.');
            $this->text('presentation_unavailable_message', 'Unavailable message', (string) $values['presentation']['unavailable_message'], 'Shown to visitors in place of the question box while the service is not ready, for example before a passing connection test. Plain text, up to 240 characters. Leave blank to use the default wording.');
        }

        if ($tab === 'ai-corpus') {
            $this->text('vector_store_reference', 'Vector store reference', (string) $values['profile']['vector_store_reference'], 'Enter the identifier for an existing OpenAI File Search vector store that you control. NCI does not create, upload to or manage the store. Changing it requires a new connection test.');
            $this->descriptionRow('NCI answers directly from the configured File Search corpus. It does not publish source links or citation metadata.');
        }

        if ($tab === 'conversation') {
            $this->textarea('instructions', 'Owner response guidance', (string) $values['instructions'], 'Use this for tone, format and level of detail. It cannot override NCI retrieval, safety, privacy or disclosure rules. Editing it requires a new successful connection test.');
            $this->descriptionRow('Applies to live answers after saving and passing a fresh connection test. It can shape response style and format only; retrieval, safety and disclosure rules always apply.');
            $this->select('response_preset', 'Response preset', ['concise' => 'Concise', 'balanced' => 'Balanced', 'detailed' => 'Detailed'], (string) $values['response_preset'], 'Choose the default answer length. Owner response guidance can refine presentation but cannot remove Core safeguards. Changing this setting requires a new connection test.');
            $this->text('presentation_placeholder', 'Question placeholder', (string) $values['presentation']['placeholder'], 'Hint text displayed inside the visitor question field. It should invite suitable questions without exposing internal instructions.');
            $this->text('presentation_footer_left', 'Panel footer left label', (string) $values['presentation']['footer_left'], 'Optional short label displayed in the left side of the panel footer.');
            $this->text('presentation_footer_right', 'Panel footer right label', (string) $values['presentation']['footer_right'], 'Optional short label displayed in the right side of the panel footer.');
            $this->sectionRow('Conversation history', 'Optionally keep a record of the questions visitors ask and the answers they receive, so you can review and delete them. Nothing is recorded unless you turn this on. No IP address, account or browser identifier is stored with a conversation.');
            $this->checkbox('conversation_log_enabled', 'Record conversations', (bool) $values['conversation_log']['enabled'], 'When on, each successfully answered public question and its answer are stored in a table on this site. Refused, failed and administrator test requests are never stored. Tell visitors in your privacy notice that questions and answers are kept.', 'Keep a record of public questions and answers on this site.', true);
            $this->select('conversation_retention_days', 'Keep conversations for', ['30' => '30 days', '90' => '90 days', '365' => '1 year', '0' => 'Until I delete them'], (string) $values['conversation_log']['retention_days'], 'Older conversations are removed automatically once a day. Choose the shortest period that meets your needs. The setting also applies to conversations already stored if you later switch recording off.');
            $this->checkbox('delete_on_uninstall', 'Delete data on uninstall', (bool) $values['lifecycle']['delete_on_uninstall'], 'By default, deleting the plugin leaves your settings, request counters and conversations in the database so a reinstall can pick them up. Turn this on to remove all of them when the plugin is deleted from the Plugins screen.', 'Delete all Niche Clever Interactive settings, counters and conversations when the plugin is deleted.', true);
            echo '<tr class="ncli-admin__field"><th scope="row"><span>' . $this->escapeHtml($this->t('Conversation history')) . '</span></th><td><a class="button button-secondary" href="' . $this->escapeAttribute($this->adminUrl(['page' => ConversationsPage::PAGE_SLUG])) . '">' . $this->escapeHtml($this->t('View conversation history')) . '</a></td></tr>';
        }

        if ($tab === 'appearance-limits') {
            $this->select('panel_style', 'Panel style', ['classic' => 'Classic', 'minimal' => 'Minimal', 'dark' => 'Dark'], (string) $values['appearance']['panel_style'], 'Choose one of the three built-in panel treatments. All styles keep the same Core question-and-answer behaviour.');
            $this->select('panel_width', 'Panel width', ['narrow' => 'Narrow', 'standard' => 'Standard', 'wide' => 'Wide'], (string) $values['appearance']['panel_width'], 'The maximum width of the public panel. It always shrinks to fit narrower screens.');
            $style = (string) $values['appearance']['panel_style'];
            $accent = (string) $values['appearance']['accent_color'];
            $this->accentRow($style, $accent);
            $this->checkbox('show_credit', 'Credit line', (bool) $values['appearance']['show_credit'], 'Adds a small "Powered by NC Interactive" link under the public panel. It is off by default and appears only if you turn it on.', 'Show "Powered by NC Interactive" under the panel.', true);
            $this->number('visitor_requests_per_day', 'Visitor requests per day', (int) $values['limits']['visitor_requests_per_day'], 'Maximum successful or attempted public questions for one privacy-bounded visitor scope in a UTC day. Use this to protect availability and API spend.');
            $this->number('provider_requests_per_day', 'Provider requests per day', (int) $values['limits']['provider_requests_per_day'], 'Maximum provider requests across the whole site in a UTC day, including administrator connection tests. Keep this within your OpenAI budget.');
        }

        echo '</tbody></table>';

        if ($tab === 'setup' || $tab === 'profile' || $tab === 'ai-corpus' || $tab === 'conversation' || $tab === 'appearance-limits') {
            echo '<div class="ncli-admin__save-bar"><button type="submit" class="button button-primary">' . $this->escapeHtml($this->t('Save settings')) . '</button><a class="ncli-admin__brand" href="https://nicheclever.co.uk/" target="_blank" rel="noopener"><img src="' . $this->escapeAttribute($this->assetUrl('assets/images/ncli-logo.png')) . '" alt="' . $this->escapeAttribute($this->t('Niche Clever')) . '" width="180" height="35"></a></div>';
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
        echo '<p>' . $this->escapeHtml($this->t('No credential, provider payload, file identifier, vector-store identifier, fingerprint or retrieved content is shown in diagnostics.')) . '</p></section>';
    }

    private function renderHelp(): void
    {
        $fileSearchUrl = 'https://platform.openai.com/docs/guides/tools-file-search';
        $vectorStoreUrl = 'https://platform.openai.com/docs/api-reference/vector-stores';
        echo '<section class="ncli-admin__tab-panel ncli-admin__help"><h2>' . $this->escapeHtml($this->t('Help Centre')) . '</h2>';
        echo '<h3>' . $this->escapeHtml($this->t('What Niche Clever Interactive is for')) . '</h3>';
        echo '<p>' . $this->escapeHtml($this->t('Niche Clever Interactive adds one configurable question-and-answer panel to a WordPress site. It uses the OpenAI Responses API and File Search against an existing vector store selected by the site administrator. It is designed for material you have prepared for visitors to explore.')) . '</p>';
        echo '<h3>' . $this->escapeHtml($this->t('Before you connect')) . '</h3>';
        echo '<ol><li>' . $this->escapeHtml($this->t('You need a WordPress site where you can install and configure plugins.')) . '</li><li>' . $this->escapeHtml($this->t('You need your own OpenAI API account, billing controls and API key.')) . '</li><li>' . $this->escapeHtml($this->t('Keep the API key in server configuration, using NCLI_OPENAI_API_KEY_DEFAULT. Niche Clever Interactive does not provide a field for entering or storing it in WordPress.')) . '</li><li>' . $this->escapeHtml($this->t('Prepare an existing File Search vector store containing material suitable for public answers.')) . '</li></ol>';
        echo '<h3>' . $this->escapeHtml($this->t('Prepare a useful corpus')) . '</h3>';
        echo '<p>' . $this->escapeHtml($this->t('Use material that is accurate, current enough for the questions you expect, and appropriate to share through a public question interface. The plugin does not decide whether a document is suitable, current or complete. Review your source material before you connect it.')) . '</p>';
        echo '<h3>' . $this->escapeHtml($this->t('Create and connect a vector store')) . '</h3>';
        echo '<p>' . $this->escapeHtml($this->t('Create or select a vector store in your own OpenAI Platform project, add approved files there and wait for indexing to complete. Then enter the existing store reference and a corpus version in AI & Corpus, save the settings and run the connection test.')) . '</p>';
        echo '<p><a href="' . $this->escapeAttribute($fileSearchUrl) . '" target="_blank" rel="noopener">' . $this->escapeHtml($this->t('OpenAI File Search documentation')) . '</a> · <a href="' . $this->escapeAttribute($vectorStoreUrl) . '" target="_blank" rel="noopener">' . $this->escapeHtml($this->t('OpenAI vector store documentation')) . '</a></p>';
        echo '<h3>' . $this->escapeHtml($this->t('Safe public use')) . '</h3>';
        echo '<p>' . $this->escapeHtml($this->t('Each public question is sent to OpenAI to generate an answer; earlier questions in the same conversation are not sent with it. The plugin limits visitor and site-wide requests, but those safeguards do not replace monitoring your API account and spend. Do not use the panel as a substitute for human review.')) . '</p>';
        echo '<h3>' . $this->escapeHtml($this->t('What it does not do')) . '</h3>';
        echo '<p>' . $this->escapeHtml($this->t('Niche Clever Interactive does not create or manage your vector store, upload documents, store your API key in WordPress, or publish provider citations or source links. It keeps no record of visitor conversations unless you switch on the optional conversation history in the Conversation tab.')) . '</p>';
        echo '<h3>' . $this->escapeHtml($this->t('Frequently asked questions')) . '</h3>';
        echo '<details><summary>' . $this->escapeHtml($this->t('Where does the API key go?')) . '</summary><p>' . $this->escapeHtml($this->t('Define NCLI_OPENAI_API_KEY_DEFAULT in wp-config.php or equivalent secure server configuration. Never add the key to the plugin settings, page content or a public repository.')) . '</p></details>';
        echo '<details><summary>' . $this->escapeHtml($this->t('Why did my connection test fail?')) . '</summary><p>' . $this->escapeHtml($this->t('Check the server-side key, transport configuration, vector store reference, corpus version and your provider account. The diagnostic screen intentionally does not reveal provider payloads or credentials.')) . '</p></details>';
        echo '<details><summary>' . $this->escapeHtml($this->t('Why is the public panel unavailable after changing settings?')) . '</summary><p>' . $this->escapeHtml($this->t('A changed key, vector store, corpus version, response guidance or response preset requires a fresh successful connection test before public answers are enabled again.')) . '</p></details>';
        echo '<details><summary>' . $this->escapeHtml($this->t('Does the plugin upload or index my documents?')) . '</summary><p>' . $this->escapeHtml($this->t('No. It connects to an existing vector store that you manage through your own OpenAI account.')) . '</p></details>';
        echo '<h3>' . $this->escapeHtml($this->t('Free and planned NCI Pro')) . '</h3>';
        $this->renderComparisonTable();
        echo '<p class="ncli-admin__comparison-note">' . $this->escapeHtml($this->t('NCI Pro is a planned separately supplied extension. This Core release makes no availability claim and includes no Pro implementation.')) . '</p></section>';
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
            : $this->t('Run a new test after changing the key, transport mode, vector store, corpus version, response guidance or response preset.');
        $placements = $this->publishedPlacements();

        echo '<section class="ncli-admin__get-started"><h2><span>' . $this->escapeHtml($this->t('Get started')) . '</span><span class="ncli-admin__get-started-count">' . $this->escapeHtml($this->t('4 Core steps')) . '</span></h2>';
        echo '<p>' . $this->escapeHtml($this->t('Niche Clever Interactive serves one carefully bounded public profile. It does not expose provider details or add Pro-only workspace controls.')) . '</p>';
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
        return [
            'enabled' => isset($_POST['enabled']) ? $_POST['enabled'] === '1' : $existing['enabled'],
            'profile' => [
                'id' => CoreSettings::DEFAULT_PROFILE_ID,
                'corpus_version' => $this->stringPost('corpus_version', $existing['profile']['corpus_version']),
                'vector_store_reference' => $this->stringPost('vector_store_reference', $existing['profile']['vector_store_reference']),
            ],
            'instructions' => $this->stringPost('instructions', $existing['instructions']),
            'response_preset' => $this->stringPost('response_preset', $existing['response_preset']),
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

    private function currentTab(): string
    {
        return $this->safeTab($_GET['tab'] ?? 'setup');
    }

    private function safeTab($value): string
    {
        $valid = ['setup', 'profile', 'ai-corpus', 'conversation', 'appearance-limits', 'placement', 'diagnostics', 'help'];
        return is_string($value) && in_array($value, $valid, true) ? $value : 'setup';
    }

    private function checkbox(string $name, string $label, bool $checked, string $help, ?string $boxLabel = null, bool $postUnchecked = false): void
    {
        $boxLabel = $boxLabel ?? 'Enable public answers for the default Core profile.';
        // An unchecked box posts nothing; the hidden zero lets an owner switch an option off.
        $hidden = $postUnchecked ? '<input type="hidden" name="' . $this->escapeAttribute($name) . '" value="0">' : '';
        echo '<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel($name, $label, $help, false) . '</th><td>' . $hidden . '<label><input type="checkbox" name="' . $this->escapeAttribute($name) . '" value="1"' . ($checked ? ' checked' : '') . '> ' . $this->escapeHtml($this->t($boxLabel)) . '</label></td></tr>';
    }

    private function sectionRow(string $title, string $description): void
    {
        echo '<tr class="ncli-admin__section-row"><td colspan="2"><h3>' . $this->escapeHtml($this->t($title)) . '</h3><p class="description">' . $this->escapeHtml($this->t($description)) . '</p></td></tr>';
    }

    private function accentRow(string $style, string $accent): void
    {
        $picker = $accent !== '' ? $accent : PanelAppearance::defaultAccent($style);
        $help = 'Overrides the highlight colour of the question button and focus outline. The colour must stay clearly visible against the panel background; if it does not, the style\'s own colour is used instead.';
        echo '<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel('accent_color', 'Accent colour', $help) . '</th><td>'
            . '<input id="ncli-accent_color" class="ncli-admin__colour" type="color" name="accent_color" value="' . $this->escapeAttribute($picker) . '"> '
            . '<label><input id="ncli-accent_color_default" type="checkbox" name="accent_color_default" value="1"' . ($accent === '' ? ' checked' : '') . '> ' . $this->escapeHtml($this->t('Use the panel style\'s own colour')) . '</label>'
            . '</td></tr>';
    }

    private function text(string $name, string $label, string $value, string $help): void
    {
        echo '<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel($name, $label, $help) . '</th><td><input id="ncli-' . $this->escapeAttribute($name) . '" class="regular-text" type="text" name="' . $this->escapeAttribute($name) . '" value="' . $this->escapeAttribute($value) . '"></td></tr>';
    }

    private function textarea(string $name, string $label, string $value, string $help): void
    {
        echo '<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel($name, $label, $help) . '</th><td><textarea id="ncli-' . $this->escapeAttribute($name) . '" class="large-text" rows="8" name="' . $this->escapeAttribute($name) . '">' . $this->escapeHtml($value) . '</textarea></td></tr>';
    }

    /**
     * @param array<string, string> $options
     */
    private function select(string $name, string $label, array $options, string $selected, string $help): void
    {
        echo '<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel($name, $label, $help) . '</th><td><select id="ncli-' . $this->escapeAttribute($name) . '" name="' . $this->escapeAttribute($name) . '">';
        foreach ($options as $value => $text) {
            $optionValue = (string) $value;
            echo '<option value="' . $this->escapeAttribute($optionValue) . '"' . ($selected === $optionValue ? ' selected' : '') . '>' . $this->escapeHtml($this->t($text)) . '</option>';
        }
        echo '</select></td></tr>';
    }

    private function number(string $name, string $label, int $value, string $help): void
    {
        echo '<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel($name, $label, $help) . '</th><td><input id="ncli-' . $this->escapeAttribute($name) . '" type="number" min="1" name="' . $this->escapeAttribute($name) . '" value="' . $value . '"></td></tr>';
    }

    private function descriptionRow(string $message): void
    {
        echo '<tr class="ncli-admin__field ncli-admin__field--description"><th scope="row"></th><td><p class="description">' . $this->escapeHtml($this->t($message)) . '</p></td></tr>';
    }

    private function fieldLabel(string $name, string $label, string $help, bool $forControl = true): string
    {
        $id = 'ncli-help-' . $name;
        $labelMarkup = $forControl
            ? '<label for="ncli-' . $this->escapeAttribute($name) . '">' . $this->escapeHtml($this->t($label)) . '</label>'
            : '<span>' . $this->escapeHtml($this->t($label)) . '</span>';

        return '<span class="ncli-admin__field-label">' . $labelMarkup
            . '<button type="button" class="ncli-admin__help-toggle" aria-controls="' . $this->escapeAttribute($id) . '" aria-expanded="false" aria-label="' . $this->escapeAttribute($this->t('Help for ') . $this->t($label)) . '"><span aria-hidden="true">?</span><span class="screen-reader-text">' . $this->escapeHtml($this->t('Help for ') . $this->t($label)) . '</span></button>'
            . '<span id="' . $this->escapeAttribute($id) . '" class="ncli-admin__field-help" role="note" hidden>' . $this->escapeHtml($this->t($help)) . '</span></span>';
    }

    private function renderComparisonTable(): void
    {
        $rows = [
            ['One knowledge profile and public Q&A panel', true, true],
            ['Bounded owner response guidance and response presets', true, true],
            ['Three built-in panel styles and request safeguards', true, true],
            ['Multiple knowledge profiles', false, true],
            ['Chronology and authored questions or collections', false, true],
            ['Contextual placement and reusable content routes', false, true],
            ['Panel width, accent colour and an optional credit line', true, true],
            ['Optional conversation history with view and delete controls', true, true],
            ['Conversation governance: export, retention schedules and scoped erasure', false, true],
            ['Earlier turns sent as context for follow-up questions', false, true],
            ['Approved-answer cache', false, true],
        ];

        echo '<table class="widefat striped ncli-admin__comparison-table"><thead><tr><th scope="col">' . $this->escapeHtml($this->t('Feature')) . '</th><th scope="col">' . $this->escapeHtml($this->t('NCI Core')) . '</th><th scope="col">' . $this->escapeHtml($this->t('Planned NCI Pro')) . '</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><th scope="row">' . $this->escapeHtml($this->t($row[0])) . '</th>';
            foreach ([$row[1], $row[2]] as $included) {
                echo '<td class="' . ($included ? 'ncli-admin__comparison-yes' : 'ncli-admin__comparison-no') . '"><span aria-hidden="true">' . ($included ? '&#10003;' : '&#10005;') . '</span><span class="screen-reader-text">' . $this->escapeHtml($included ? $this->t('Included') : $this->t('Not included')) . '</span></td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
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
