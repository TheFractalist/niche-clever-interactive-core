<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;

use NicheCleverInteractive\Core\AI\ConnectionTestService;
use NicheCleverInteractive\Core\AI\Transport\UnavailableTransport;
use NicheCleverInteractive\Core\Budget\BudgetGate;
use NicheCleverInteractive\Core\Budget\WordPressBucketStore;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\ModelCatalogue;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Security\AdminActionGuard;
use NicheCleverInteractive\Core\Support\Translator;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic markup uses the tested escapeHtml()/escapeAttribute() wrappers, which keep this PHP 8.1-compatible class usable in pure tests.
// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- AdminActionGuard centralises capability and nonce verification before this class reads a request.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- requestInput() unslashes values and CoreSettings performs bounded validation after AdminActionGuard admits the request.
final class SettingsPage
{
    private const PAGE_SLUG = 'ncli-core';
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
                $this->t('NCI Interactive'),
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
            echo '<section class="ncli-admin__tab-panel"><h2>' . $this->escapeHtml($this->t('Placement')) . '</h2><p>' . $this->escapeHtml($this->t('Use the')) . ' <code>[ncli_interactive]</code> ' . $this->escapeHtml($this->t('shortcode or the NCI Interactive block. Contextual and collection placement is planned for NCI Pro.')) . '</p></section>';
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
        }

        if ($tab === 'ai-corpus') {
            $this->text('vector_store_reference', 'Vector store reference', (string) $values['profile']['vector_store_reference'], 'Enter the identifier for an existing OpenAI File Search vector store that you control. NCI does not create, upload to or manage the store. Changing it requires a new connection test.');
            $this->descriptionRow('NCI answers directly from the configured File Search corpus. It does not publish source links or citation metadata.');
            $this->renderModelFields($values['model']);
        }

        if ($tab === 'conversation') {
            $this->textarea('instructions', 'Owner response guidance', (string) $values['instructions'], 'Use this for tone, format and level of detail. It cannot override NCI retrieval, safety, privacy or disclosure rules. Editing it requires a new successful connection test.');
            $this->descriptionRow('Applies to live answers after saving and passing a fresh connection test. It can shape response style and format only; retrieval, safety and disclosure rules always apply.');
            $this->select('response_preset', 'Response preset', ['concise' => 'Concise', 'balanced' => 'Balanced', 'detailed' => 'Detailed'], (string) $values['response_preset'], 'Choose the default answer length. Owner response guidance can refine presentation but cannot remove Core safeguards. Changing this setting requires a new connection test.');
            $this->text('presentation_placeholder', 'Question placeholder', (string) $values['presentation']['placeholder'], 'Hint text displayed inside the visitor question field. It should invite suitable questions without exposing internal instructions.');
            $this->text('presentation_footer_left', 'Panel footer left label', (string) $values['presentation']['footer_left'], 'Optional short label displayed in the left side of the panel footer.');
            $this->text('presentation_footer_right', 'Panel footer right label', (string) $values['presentation']['footer_right'], 'Optional short label displayed in the right side of the panel footer.');
        }

        if ($tab === 'appearance-limits') {
            $this->select('panel_style', 'Panel style', ['classic' => 'Classic', 'minimal' => 'Minimal', 'dark' => 'Dark'], (string) $values['appearance']['panel_style'], 'Choose one of the three built-in panel treatments. All styles keep the same Core question-and-answer behaviour.');
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
        echo '<h3>' . $this->escapeHtml($this->t('Choosing a model and managing cost')) . '</h3>';
        echo '<p>' . $this->escapeHtml($this->t('Each public question is billed by OpenAI to your own account. The settings in AI & Corpus change how much each answer costs and how long it takes. Core does not estimate or display prices, because they change; check OpenAI pricing for current figures.')) . '</p>';
        echo $this->renderModelTable();
        echo '<ul><li><strong>' . $this->escapeHtml($this->t('Model:')) . '</strong> ' . $this->escapeHtml($this->t('The largest cost lever. Economy models cost far less per answer than Premium models, and Premium models suit nuanced questions.')) . '</li>'
            . '<li><strong>' . $this->escapeHtml($this->t('Retrieval depth:')) . '</strong> ' . $this->escapeHtml($this->t('More retrieved passages give the model more to read, which raises cost and response time and can help broad questions.')) . '</li>'
            . '<li><strong>' . $this->escapeHtml($this->t('Maximum answer length:')) . '</strong> ' . $this->escapeHtml($this->t('Caps output, including reasoning. Pair it with the Concise, Balanced or Detailed response preset. A very low cap can truncate answers.')) . '</li>'
            . '<li><strong>' . $this->escapeHtml($this->t('Reasoning effort:')) . '</strong> ' . $this->escapeHtml($this->t('Higher effort can improve hard answers but uses more tokens and takes longer. Leave it at the provider default unless you have a reason to change it.')) . '</li>'
            . '<li><strong>' . $this->escapeHtml($this->t('Request timeout:')) . '</strong> ' . $this->escapeHtml($this->t('Longer limits suit slower, higher-effort requests. Your hosting limits may stop a request sooner. Core makes one attempt and does not retry.')) . '</li></ul>';
        echo '<p>' . $this->escapeHtml($this->t('Example profiles: for lowest cost, choose an Economy model with a small retrieval depth and a Concise preset. For best quality, choose a Premium model with a larger retrieval depth and a Detailed preset.')) . '</p>';
        echo '<p>' . $this->escapeHtml($this->t('Also set a usage limit in your OpenAI dashboard. Core\'s request limits help, but they are not a billing control.')) . ' <a href="https://openai.com/api/pricing/" target="_blank" rel="noopener">' . $this->escapeHtml($this->t('OpenAI API pricing')) . '</a></p>';
        echo '<h3>' . $this->escapeHtml($this->t('Safe public use')) . '</h3>';
        echo '<p>' . $this->escapeHtml($this->t('Public questions and relevant conversation context are sent to OpenAI to generate an answer. The plugin limits visitor and site-wide requests, but those safeguards do not replace monitoring your API account and spend. Do not use the panel as a substitute for human review.')) . '</p>';
        echo '<h3>' . $this->escapeHtml($this->t('What it does not do')) . '</h3>';
        echo '<p>' . $this->escapeHtml($this->t('Niche Clever Interactive does not create or manage your vector store, upload documents, store your API key in WordPress, publish provider citations or source links, or maintain a server-side public conversation archive.')) . '</p>';
        echo '<h3>' . $this->escapeHtml($this->t('Frequently asked questions')) . '</h3>';
        echo '<details><summary>' . $this->escapeHtml($this->t('Where does the API key go?')) . '</summary><p>' . $this->escapeHtml($this->t('Define NCLI_OPENAI_API_KEY_DEFAULT in wp-config.php or equivalent secure server configuration. Never add the key to the plugin settings, page content or a public repository.')) . '</p></details>';
        echo '<details><summary>' . $this->escapeHtml($this->t('Why did my connection test fail?')) . '</summary><p>' . $this->escapeHtml($this->t('Check the server-side key, transport configuration, vector store reference, corpus version and your provider account. The diagnostic screen intentionally does not reveal provider payloads or credentials.')) . '</p></details>';
        echo '<details><summary>' . $this->escapeHtml($this->t('Why is the public panel unavailable after changing settings?')) . '</summary><p>' . $this->escapeHtml($this->t('A changed key, vector store, corpus version, response guidance, response preset, model, reasoning effort, retrieval depth, answer length or timeout requires a fresh successful connection test before public answers are enabled again.')) . '</p></details>';
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
            : $this->t('Run a new test after changing the key, transport mode, vector store, corpus version, response guidance, response preset or any model setting.');
        $placements = $this->publishedPlacements();

        echo '<section class="ncli-admin__get-started"><h2><span>' . $this->escapeHtml($this->t('Get started')) . '</span><span class="ncli-admin__get-started-count">' . $this->escapeHtml($this->t('4 Core steps')) . '</span></h2>';
        echo '<p>' . $this->escapeHtml($this->t('Niche Clever Interactive serves one carefully bounded public profile. It does not expose provider details or add Pro-only workspace controls.')) . '</p>';
        echo '<ol class="ncli-admin__checklist">';
        echo '<li><span class="ncli-admin__check">1</span><div><strong>' . $this->escapeHtml($this->t('Add your API key')) . '</strong><p>' . $this->escapeHtml($keyState) . ' ' . $this->escapeHtml($this->t('Configure')) . ' <code>NCLI_OPENAI_API_KEY_DEFAULT</code> ' . $this->escapeHtml($this->t('in server configuration only, for example')) . ' <code>define(\'NCLI_OPENAI_API_KEY_DEFAULT\', \'…\');</code>.</p></div></li>';
        echo '<li><span class="ncli-admin__check">2</span><div><strong>' . $this->escapeHtml($this->t('Choose your vector store')) . '</strong><p>' . $this->escapeHtml($corpusState) . ' <a href="' . $this->escapeAttribute($this->adminUrl(['page' => self::PAGE_SLUG, 'tab' => 'ai-corpus'])) . '">' . $this->escapeHtml($this->t('Open AI & Corpus')) . '</a>.</p></div></li>';
        echo '<li><span class="ncli-admin__check">3</span><div><strong>' . $this->escapeHtml($this->t('Test your connection')) . '</strong><p>' . $this->escapeHtml($testState) . '</p><button type="submit" class="button button-secondary" form="ncli-connection-test">' . $this->escapeHtml($this->t('Test connection')) . '</button></div></li>';
        echo '<li><span class="ncli-admin__check">4</span><div><strong>' . $this->escapeHtml($this->t('Place your conversation')) . '</strong><p>';
        if ($placements === []) {
            echo $this->escapeHtml($this->t('Add the NCI Interactive block or')) . ' <code>[ncli_interactive]</code> ' . $this->escapeHtml($this->t('shortcode to a published page.'));
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
            'model' => $this->modelInput($existing['model']),
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

    /**
     * @param array{id: string, reasoning_effort: string, retrieval_depth: int, max_output_tokens: int, timeout_seconds: int} $model
     */
    private function renderModelFields(array $model): void
    {
        $modelOptions = '';
        foreach (ModelCatalogue::all() as $entry) {
            $modelOptions .= '<option value="' . $this->escapeAttribute($entry['id']) . '" data-efforts="' . $this->escapeAttribute(implode(',', $entry['efforts'])) . '"' . ($model['id'] === $entry['id'] ? ' selected' : '') . '>' . $this->escapeHtml($this->t($entry['tier']) . ' – ' . $entry['label'] . ' (' . $entry['id'] . ')') . '</option>';
        }
        $helpHtml = '<span class="ncli-admin__model-help-intro">' . $this->escapeHtml($this->t('Choose the OpenAI model that writes answers. Higher tiers usually cost more per answer. Changing this requires a new connection test.')) . '</span>' . $this->renderModelTable();
        echo '<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel('model_id', 'Model', '', true, $helpHtml) . '</th><td><select id="ncli-model_id" name="model_id">' . $modelOptions . '</select></td></tr>';

        $efforts = ['default' => 'Provider default (not sent)'];
        foreach (['none', 'low', 'medium', 'high', 'xhigh', 'max'] as $effort) {
            $efforts[$effort] = ucfirst($effort === 'xhigh' ? 'extra high' : $effort);
        }
        $this->select('model_reasoning_effort', 'Reasoning effort', $efforts, $model['reasoning_effort'], 'How much thinking the model does before answering. Higher effort can improve difficult answers but uses more output tokens, costs more and takes longer. Only values valid for the chosen model are accepted, and models without reasoning control ignore it. Changing this requires a new connection test.');

        $depths = [];
        foreach (ModelCatalogue::RETRIEVAL_DEPTHS as $depth) {
            $depths[(string) $depth] = (string) $depth;
        }
        $this->select('model_retrieval_depth', 'Retrieval depth', $depths, (string) $model['retrieval_depth'], 'How many passages are retrieved from the vector store for each question. More passages can help broad questions but increase the amount of text the model reads, so cost and response time rise. Changing this requires a new connection test.');

        $this->number('model_max_output_tokens', 'Maximum answer length (tokens)', $model['max_output_tokens'], 'The most output tokens one answer may use, including any reasoning. Too low a limit can cut answers short, or leave a reasoning model with no visible answer. Changing this requires a new connection test.', ModelCatalogue::MIN_OUTPUT_TOKENS, ModelCatalogue::MAX_OUTPUT_TOKENS);

        $timeouts = [];
        foreach (ModelCatalogue::TIMEOUT_SECONDS as $seconds) {
            $timeouts[(string) $seconds] = sprintf($this->t('%d seconds'), $seconds);
        }
        $this->select('model_timeout_seconds', 'Request timeout', $timeouts, (string) $model['timeout_seconds'], 'How long Core waits for OpenAI before giving up. Higher reasoning effort may need longer. Your hosting limits may stop a request earlier. Core does not retry automatically. Changing this requires a new connection test.');
        $this->descriptionRow('See the Help Centre for guidance on how these choices affect cost. Set a usage limit in your OpenAI dashboard as well; Core\'s request limits do not replace it.');
    }

    private function renderModelTable(): string
    {
        $html = '<span class="ncli-admin__model-table-wrap"><table class="widefat striped ncli-admin__model-table"><thead><tr>'
            . '<th scope="col">' . $this->escapeHtml($this->t('Model')) . '</th>'
            . '<th scope="col">' . $this->escapeHtml($this->t('Tier')) . '</th>'
            . '<th scope="col">' . $this->escapeHtml($this->t('Best for')) . '</th>'
            . '<th scope="col">' . $this->escapeHtml($this->t('Relative cost')) . '</th>'
            . '<th scope="col">' . $this->escapeHtml($this->t('Reasoning effort')) . '</th>'
            . '</tr></thead><tbody>';
        foreach (ModelCatalogue::all() as $entry) {
            $efforts = $entry['efforts'] === [] ? $this->t('Not offered') : implode(', ', $entry['efforts']);
            $html .= '<tr><th scope="row"><code>' . $this->escapeHtml($entry['id']) . '</code></th>'
                . '<td>' . $this->escapeHtml($this->t($entry['tier'])) . '</td>'
                . '<td>' . $this->escapeHtml($this->t($entry['best_for'])) . '</td>'
                . '<td>' . $this->escapeHtml($this->t($entry['cost_note'])) . '</td>'
                . '<td>' . $this->escapeHtml($efforts) . '</td></tr>';
        }

        return $html . '</tbody></table></span>';
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

    private function checkbox(string $name, string $label, bool $checked, string $help): void
    {
        echo '<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel($name, $label, $help, false) . '</th><td><label><input type="checkbox" name="' . $this->escapeAttribute($name) . '" value="1"' . ($checked ? ' checked' : '') . '> ' . $this->escapeHtml($this->t('Enable public answers for the default Core profile.')) . '</label></td></tr>';
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

    private function number(string $name, string $label, int $value, string $help, int $min = 1, ?int $max = null): void
    {
        echo '<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel($name, $label, $help) . '</th><td><input id="ncli-' . $this->escapeAttribute($name) . '" type="number" min="' . $min . '"' . ($max !== null ? ' max="' . $max . '"' : '') . ' name="' . $this->escapeAttribute($name) . '" value="' . $value . '"></td></tr>';
    }

    private function descriptionRow(string $message): void
    {
        echo '<tr class="ncli-admin__field ncli-admin__field--description"><th scope="row"></th><td><p class="description">' . $this->escapeHtml($this->t($message)) . '</p></td></tr>';
    }

    /**
     * @param string|null $helpHtml Pre-escaped markup that replaces the plain-text help.
     */
    private function fieldLabel(string $name, string $label, string $help, bool $forControl = true, ?string $helpHtml = null): string
    {
        $id = 'ncli-help-' . $name;
        $labelMarkup = $forControl
            ? '<label for="ncli-' . $this->escapeAttribute($name) . '">' . $this->escapeHtml($this->t($label)) . '</label>'
            : '<span>' . $this->escapeHtml($this->t($label)) . '</span>';

        return '<span class="ncli-admin__field-label">' . $labelMarkup
            . '<button type="button" class="ncli-admin__help-toggle" aria-controls="' . $this->escapeAttribute($id) . '" aria-expanded="false" aria-label="' . $this->escapeAttribute($this->t('Help for ') . $this->t($label)) . '"><span aria-hidden="true">?</span><span class="screen-reader-text">' . $this->escapeHtml($this->t('Help for ') . $this->t($label)) . '</span></button>'
            . '<span id="' . $this->escapeAttribute($id) . '" class="ncli-admin__field-help' . ($helpHtml !== null ? ' ncli-admin__field-help--wide' : '') . '" role="note" hidden>' . ($helpHtml ?? $this->escapeHtml($this->t($help))) . '</span></span>';
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
            ['Approved-answer cache and conversation archive facilities', false, true],
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
