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

    public function __construct(?AdminActionGuard $guard = null, ?ConnectionTestService $connectionTest = null, ?FailureLog $failures = null)
    {
        $this->guard = $guard ?? AdminActionGuard::forWordPress();
        $this->failures = $failures ?? new OptionFailureLog();
        $this->fields = new ProfileFieldRenderer();
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
            echo '<section class="ncli-admin__tab-panel"><h2>' . $this->escapeHtml($this->t('Placement')) . '</h2><p>' . $this->escapeHtml($this->t('Use the')) . ' <code>[ncli_interactive]</code> ' . $this->escapeHtml($this->t('shortcode or the NC Interactive block. Contextual and collection placement is available in NCI Premium.')) . '</p></section>';
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
        $this->fields->rows($tab, $values, $this->adminUrl(['page' => ConversationsPage::PAGE_SLUG]));
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

    private function renderHelp(): void
    {
        $section = $this->currentHelpSection();
        $sections = [
            'overview' => $this->t('Overview'),
            'faq' => $this->t('Frequently asked questions'),
            'premium' => $this->t('NCI Premium'),
        ];

        echo '<section class="ncli-admin__tab-panel ncli-admin__help"><h2>' . $this->escapeHtml($this->t('Help Centre')) . '</h2>';
        echo '<nav class="ncli-admin__subtabs" aria-label="' . $this->escapeAttribute($this->t('Help sections')) . '">';
        foreach ($sections as $id => $label) {
            $active = $id === $section;
            $url = $this->adminUrl(['page' => self::PAGE_SLUG, 'tab' => 'help', 'help' => $id]);
            echo '<a class="ncli-admin__subtab' . ($active ? ' is-active' : '') . '"' . ($active ? ' aria-current="page"' : '') . ' href="' . $this->escapeAttribute($url) . '">' . $this->escapeHtml($label) . '</a>';
        }
        echo '</nav>';

        if ($section === 'faq') {
            $this->renderHelpFaq();
        } elseif ($section === 'premium') {
            $this->renderHelpPremium();
        } else {
            $this->renderHelpOverview();
        }

        echo '</section>';
    }

    private function currentHelpSection(): string
    {
        $value = $_GET['help'] ?? 'overview';
        return is_string($value) && in_array($value, ['overview', 'faq', 'premium'], true) ? $value : 'overview';
    }

    private function renderHelpOverview(): void
    {
        $fileSearchUrl = 'https://platform.openai.com/docs/guides/tools-file-search';
        $vectorStoreUrl = 'https://platform.openai.com/docs/api-reference/vector-stores';
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
        echo $this->fields->renderModelTable();
        echo '<ul><li><strong>' . $this->escapeHtml($this->t('Model:')) . '</strong> ' . $this->escapeHtml($this->t('The largest cost lever. Economy models cost far less per answer than Premium models, and Premium models suit nuanced questions.')) . '</li>'
            . '<li><strong>' . $this->escapeHtml($this->t('Retrieval depth:')) . '</strong> ' . $this->escapeHtml($this->t('More retrieved passages give the model more to read, which raises cost and response time and can help broad questions.')) . '</li>'
            . '<li><strong>' . $this->escapeHtml($this->t('Maximum answer length:')) . '</strong> ' . $this->escapeHtml($this->t('Caps output, including reasoning. Pair it with the Concise, Balanced or Detailed response preset. A very low cap can truncate answers.')) . '</li>'
            . '<li><strong>' . $this->escapeHtml($this->t('Reasoning effort:')) . '</strong> ' . $this->escapeHtml($this->t('Higher effort can improve hard answers but uses more tokens and takes longer. Leave it at the provider default unless you have a reason to change it.')) . '</li>'
            . '<li><strong>' . $this->escapeHtml($this->t('Request timeout:')) . '</strong> ' . $this->escapeHtml($this->t('Longer limits suit slower, higher-effort requests. Your hosting limits may stop a request sooner. Core makes one attempt and does not retry.')) . '</li></ul>';
        echo '<p>' . $this->escapeHtml($this->t('Example profiles: for lowest cost, choose an Economy model with a small retrieval depth and a Concise preset. For best quality, choose a Premium model with a larger retrieval depth and a Detailed preset.')) . '</p>';
        echo '<p>' . $this->escapeHtml($this->t('Also set a usage limit in your OpenAI dashboard. Core\'s request limits help, but they are not a billing control.')) . ' <a href="https://openai.com/api/pricing/" target="_blank" rel="noopener">' . $this->escapeHtml($this->t('OpenAI API pricing')) . '</a></p>';
        echo '<h3>' . $this->escapeHtml($this->t('Safe public use')) . '</h3>';
        echo '<p>' . $this->escapeHtml($this->t('Each public question is sent to OpenAI to generate an answer; earlier questions in the same conversation are not sent with it by Core itself, although an installed extension such as NCI Premium can add a limited number of earlier turns. The plugin limits visitor and site-wide requests, but those safeguards do not replace monitoring your API account and spend. Do not use the panel as a substitute for human review.')) . '</p>';
        echo '<h3>' . $this->escapeHtml($this->t('What it does not do')) . '</h3>';
        echo '<p>' . $this->escapeHtml($this->t('Niche Clever Interactive does not create or manage your vector store, upload documents, store your API key in WordPress, or publish provider citations or source links. It keeps no record of visitor conversations unless you switch on the optional conversation history in the Conversation tab.')) . '</p>';
    }

    private function renderHelpFaq(): void
    {
        $groups = [
            'Setup and connection' => [
                ['What do I need before I start?', 'An OpenAI API account with billing controls and an API key, an OpenAI File Search vector store with your approved files indexed, a WordPress 6.5 or later site and PHP 8.1 or later.'],
                ['Where does the API key go?', 'Define NCLI_OPENAI_API_KEY_DEFAULT in wp-config.php or equivalent secure server configuration. Never add the key to the plugin settings, page content or a public repository. Defining the key is all the server setup the plugin needs; there is no separate switch to turn on live answers.'],
                ['Can I switch the provider off without removing the key?', 'Yes. Set Visitor availability to Unavailable in the Setup tab, or untick Enable the Core profile in the Profile tab. Server administrators can also define NCLI_CORE_LIVE_TRANSPORT as false in wp-config.php to stop all provider requests while the key stays in place.'],
                ['Does the plugin upload or index my documents?', 'No. It connects to an existing vector store that you manage through your own OpenAI account.'],
                ['What is the corpus version for?', 'It is a label you choose, such as v10, for the material in your vector store. Changing it requires a fresh connection test, so answers are never served against material you have not re-checked. Change it whenever you change the files in the store.'],
                ['What does Visitor availability do?', 'The panel answers only when it is enabled and its current configuration has passed the connection test. Otherwise visitors see your unavailable message instead.'],
                ['Why did my connection test fail?', 'Check the server-side key, transport configuration, vector store reference, corpus version and your provider account. Diagnostics shows the latest provider problem in plain words and never reveals provider payloads or credentials.'],
                ['Why is the public panel unavailable after changing settings?', 'A changed key, vector store, corpus version, response guidance, response preset, model, reasoning effort, retrieval depth, answer length or timeout requires a fresh successful connection test before public answers are available again.'],
            ],
            'Answers, models and cost' => [
                ['Who pays for the answers?', 'Each public question is billed by OpenAI to your own account. Core shows no prices, so check OpenAI pricing and set a usage limit in your OpenAI dashboard as well as Core\'s request limits.'],
                ['Which model and settings should I choose?', 'The Overview tab explains each setting. For the lowest cost use an Economy model, a small retrieval depth and the Concise preset. For the best quality use a Premium model, a larger retrieval depth and the Detailed preset.'],
                ['What do the response guidance and presets do?', 'They shape tone, format and level of detail only. Retrieval, safety, privacy and disclosure rules always take priority, and changing them requires a fresh connection test.'],
                ['Does it show sources or citations?', 'No. Core never publishes provider citations or source links. Approved evidence links that you choose yourself are available in NCI Premium.'],
                ['Why did the panel refuse my question?', 'Core applies fixed safety rules. A question that tries to override its instructions or reveal them is declined with a short refusal.'],
                ['Why does it say a verified answer is not available?', 'The answer did not come from a completed search of your corpus. Check the vector store reference and corpus version, then run the connection test again.'],
                ['Can visitors ask follow-up questions?', 'Yes, but on its own Core answers each question separately: earlier questions in the same conversation are not sent with it. NCI Premium can send a limited number of earlier turns as context so follow-ups make sense, with a setting for how many.'],
                ['Are answers cached?', 'Core itself does not cache: each question is a fresh request to OpenAI. NCI Premium can reuse answers you have approved to save cost and time.'],
                ['Can visitors ask in other languages?', 'They can ask in any language the model understands. The interface text is translation-ready, while wording you write in the Profile and Conversation tabs appears exactly as you wrote it.'],
            ],
            'Visitors, limits and privacy' => [
                ['What information is sent to OpenAI?', 'Only the visitor\'s current question, together with your instructions and the vector store reference, is sent when the enabled panel is used. Earlier questions in the same conversation are not sent with it by Core itself; if NCI Premium is installed it can add a limited number of earlier turns and any chronology records you have entered.'],
                ['Why does the panel say the question allowance has been reached?', 'Either one visitor has used their daily questions or the whole site has used its daily provider allowance. Both limits are set in Appearance & Limits, count by UTC day and start again at UTC midnight. Visitors are counted with a privacy-bounded code, not a stored address, and no extra server setup is needed for this.'],
                ['Does it keep a record of visitors\' conversations?', 'Not unless you switch on the optional conversation history in the Conversation tab. It is off by default. If you turn it on you choose how long records are kept and can review and delete them under NC Interactive > Conversations.'],
                ['How do I review or delete stored conversations?', 'Open NC Interactive > Conversations to view them and to delete one, several or all. Records are removed automatically after the retention period you choose. Deleted records can reappear if you restore an older database backup, so repeat the deletion after a restore.'],
                ['Can I respond to a privacy request about a visitor?', 'WordPress\'s personal-data export and erase tools are supported, but Core stores no name, address, account or browser identifier with a conversation, so a record cannot be matched to a person. Use the Conversations screen to remove records you need to remove.'],
                ['What happens to my data if I delete the plugin?', 'By default nothing is removed, so a reinstall can pick up your settings, request counters and stored conversations. If you turn on Delete data on uninstall in the Conversation tab, deleting the plugin removes all of them.'],
                ['What should I put in my privacy policy?', 'The plugin offers suggested wording in the WordPress privacy policy guide, describing the OpenAI service and the optional history. Review it against your own practices and your OpenAI account terms.'],
                ['What does Reset conversation do?', 'It clears the thread in that visitor\'s browser tab and starts a new conversation. Nothing is saved in the browser, and any history you have chosen to keep on the site is not affected.'],
            ],
            'Appearance and placement' => [
                ['How do I put the panel on a page?', 'Add the NC Interactive block to a page, or use the [ncli_interactive] shortcode. The Placement tab shows where Core has detected a published panel.'],
                ['Can I use more than one panel?', 'You can place the panel on as many pages as you like, but every placement uses the same single profile. Several knowledge profiles are available in NCI Premium.'],
                ['How do I change how it looks?', 'Choose the style, width and accent colour in Appearance & Limits. Edit the title, headline, introduction and unavailable message in Profile, and the placeholder and footer labels in Conversation. Finer colour control for questions and answers is available in NCI Premium.'],
                ['Do I have to show a credit link?', 'No. The optional Powered by NC Interactive link is off by default and appears only if you turn it on in Appearance & Limits.'],
                ['Is the panel accessible?', 'The panel and the settings screens are built to work with a keyboard and a screen reader, with labelled controls, visible focus and announcements when an answer arrives, and they respect reduced-motion settings.'],
            ],
            'Troubleshooting' => [
                ['The panel says the service is temporarily unavailable. What happened?', 'The provider did not return a usable answer in time. The most common cause is a timeout when the reasoning effort is high. Open Diagnostics: "Last provider problem" shows the reason, when it happened and what to try, such as lowering the reasoning effort or choosing a faster model. Run the connection test again after changing a model setting.'],
                ['The panel shows my unavailable message although I enabled it. Why?', 'Open Diagnostics and read the readiness state. It tells you whether the key, the vector store, the corpus version or a current connection test is missing.'],
                ['I changed the files in my vector store. What should I do?', 'Wait for OpenAI to finish indexing, change the corpus version in Profile, save and run the connection test again.'],
                ['What does Diagnostics show?', 'The readiness state, the connection test result and the latest provider problem. It never shows a credential, provider payload, file identifier or retrieved content.'],
            ],
            'Editions' => [
                ['What is NCI Premium?', 'NCI Premium is a separately supplied edition from Niche Clever that adds several knowledge profiles, approved evidence links, authored questions, conversation export and governance, answer reuse and finer colour control. The NCI Premium tab lists what each edition includes. This plugin, NCI Core, contains no Premium code, and nothing in Core is locked, limited or time-restricted.'],
            ],
        ];

        echo '<h3>' . $this->escapeHtml($this->t('Frequently asked questions')) . '</h3>';
        foreach ($groups as $group => $questions) {
            echo '<h4 class="ncli-admin__faq-group">' . $this->escapeHtml($this->t($group)) . '</h4>';
            foreach ($questions as $question) {
                echo '<details><summary>' . $this->escapeHtml($this->t($question[0])) . '</summary><p>' . $this->escapeHtml($this->t($question[1])) . '</p></details>';
            }
        }
    }

    private function renderHelpPremium(): void
    {
        echo '<h3>' . $this->escapeHtml($this->t('NCI Core and NCI Premium')) . '</h3>';
        echo '<p>' . $this->escapeHtml($this->t('NCI Core is complete for one focused question-and-answer panel. NCI Premium, supplied separately by Niche Clever, is for sites that need several knowledge profiles, curated evidence, authored questions, deeper conversation governance and cost-saving answer reuse. The table shows what each edition includes.')) . '</p>';
        $this->renderComparisonTable();
        echo '<p class="ncli-admin__comparison-note">' . $this->escapeHtml($this->t('NCI Premium is a separately supplied edition. NCI Core contains no Premium code, and nothing in Core is locked, limited or time-restricted. Core never publishes provider citations or source links; Premium\'s evidence links would be titles and addresses that you approve yourself.')) . '</p>';
    }

    private function renderComparisonTable(): void
    {
        $groups = [
            'Included in NCI Core' => [
                ['One knowledge profile and public question panel', 'Answers visitors\' questions from your own OpenAI File Search vector store, on as many pages as you like.', true, true],
                ['Guided setup and connection test', 'A step-by-step checklist, with a test that must pass again after any change that affects answers.', true, true],
                ['Model, reasoning and cost controls', 'Choose the model, reasoning effort, retrieval depth, answer length and timeout, with no prices shown.', true, true],
                ['Response guidance and presets', 'Your own bounded guidance plus Concise, Balanced or Detailed answers, inside fixed safety rules.', true, true],
                ['Conversation thread', 'Questions and answers build up as a thread with a Reset conversation control; nothing is kept in the browser.', true, true],
                ['Safe answer formatting', 'Headings, lists, emphasis, quotes and code display cleanly; provider text is never run as markup and no provider links are published.', true, true],
                ['Panel styles, width and accent colour', 'Three built-in styles, three widths and your own accent colour.', true, true],
                ['Editable panel wording', 'Title, headline, introduction, placeholder, footer labels and unavailable message.', true, true],
                ['Daily request limits', 'A per-visitor and a site-wide daily allowance, with no stored addresses and no extra setup.', true, true],
                ['Block and shortcode placement', 'The NC Interactive block or the [ncli_interactive] shortcode, with placement detection.', true, true],
                ['Optional conversation history', 'Off by default; when on, you can view and delete records and set how long they are kept.', true, true],
                ['Privacy tools', 'Suggested privacy-policy wording, WordPress export and erase support, and an opt-in to delete data on uninstall.', true, true],
                ['Diagnostics', 'Readiness, connection-test result and the latest provider problem in plain words.', true, true],
                ['Accessible, translation-ready interface', 'Labelled controls, keyboard operation and translatable text across the panel and the settings screens.', true, true],
            ],
            'Added in NCI Premium: knowledge and access' => [
                ['Multiple knowledge profiles', 'Separate profiles, each with its own vector store, wording, style and request limits.', false, true],
                ['Protected profiles', 'Limit a profile to visitors who have been granted access, with an editable access-required message.', false, true],
                ['Contextual placement and reusable content routes', 'Place questions in context and route visitors to them from other content.', false, true],
            ],
            'Added in NCI Premium: answers and evidence' => [
                ['Approved public evidence links', 'Titles and links you have approved, shown under answers; never raw provider citations.', false, true],
                ['Chronology records', 'Verified, dated summaries so answers about history stay within what your records support.', false, true],
                ['Earlier turns sent as context for follow-up questions', 'Lets follow-ups build on the conversation so far, with a setting for how many earlier turns to include; Core answers each question on its own.', false, true],
                ['Approved-answer cache', 'Reuses an approved answer to a repeated first question to save cost and time, and does not use a live allowance when it does.', false, true],
            ],
            'Added in NCI Premium: authored questions' => [
                ['Authored questions and shareable links', 'A catalogue of reusable questions with stable keys, shortcodes and links to share.', false, true],
                ['Ordered question collections', 'Group authored questions into a sequence and place it where it fits.', false, true],
            ],
            'Added in NCI Premium: conversation governance' => [
                ['Conversation export', 'Export one or more conversations for review, filtered by profile.', false, true],
                ['Governance controls', 'Per-profile scope, retention schedules and audited bulk erasure on top of Core\'s history.', false, true],
            ],
            'Added in NCI Premium: presentation' => [
                ['Response colour controls', 'Set the colours of question and answer blocks, not only the accent colour.', false, true],
            ],
        ];

        echo '<table class="widefat ncli-admin__comparison-table"><caption class="screen-reader-text">' . $this->escapeHtml($this->t('NCI Core compared with NCI Premium')) . '</caption>';
        echo '<thead><tr><th scope="col">' . $this->escapeHtml($this->t('Feature')) . '</th><th scope="col">' . $this->escapeHtml($this->t('What it does')) . '</th><th scope="col">' . $this->escapeHtml($this->t('NCI Core')) . '</th><th scope="col">' . $this->escapeHtml($this->t('NCI Premium')) . '</th></tr></thead><tbody>';
        foreach ($groups as $group => $rows) {
            echo '<tr class="ncli-admin__comparison-group"><th scope="rowgroup" colspan="4">' . $this->escapeHtml($this->t($group)) . '</th></tr>';
            foreach ($rows as $row) {
                echo '<tr><th scope="row">' . $this->escapeHtml($this->t($row[0])) . '</th><td class="ncli-admin__comparison-what">' . $this->escapeHtml($this->t($row[1])) . '</td>';
                foreach ([$row[2], $row[3]] as $included) {
                    echo '<td class="' . ($included ? 'ncli-admin__comparison-yes' : 'ncli-admin__comparison-no') . '"><span aria-hidden="true">' . ($included ? '&#10003;' : '&#10005;') . '</span><span class="screen-reader-text">' . $this->escapeHtml($included ? $this->t('Included') : $this->t('Not included')) . '</span></td>';
                }
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
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
        echo '<p>' . $this->escapeHtml($this->t('Niche Clever Interactive serves one carefully bounded public profile. It does not expose provider details or add Premium-only workspace controls.')) . '</p>';
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

    private function currentTab(): string
    {
        return $this->safeTab($_GET['tab'] ?? 'setup');
    }

    private function safeTab($value): string
    {
        $valid = ['setup', 'profile', 'ai-corpus', 'conversation', 'appearance-limits', 'placement', 'diagnostics', 'help'];
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
