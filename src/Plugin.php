<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core;

use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Admin\ConversationsPage;
use NicheCleverInteractive\Core\Admin\ExtensionScreens;
use NicheCleverInteractive\Core\Admin\ProfileConnectionTest;
use NicheCleverInteractive\Core\Admin\ProfileHub;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;
use NicheCleverInteractive\Core\Admin\SettingsPage;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Conversation\ConversationPurge;
use NicheCleverInteractive\Core\Conversation\GuardedConversationArchive;
use NicheCleverInteractive\Core\Conversation\ConversationRecorder;
use NicheCleverInteractive\Core\Conversation\ConversationTable;
use NicheCleverInteractive\Core\Conversation\WordPressConversationStore;
use NicheCleverInteractive\Core\Diagnostics\OptionFailureLog;
use NicheCleverInteractive\Core\Frontend\InteractivePanel;
use NicheCleverInteractive\Core\AI\ChatService;
use NicheCleverInteractive\Core\AI\Transport\DeterministicMockTransport;
use NicheCleverInteractive\Core\AI\Transport\OpenAiResponsesFileSearchTransport;
use NicheCleverInteractive\Core\AI\Transport\ResponseTransport;
use NicheCleverInteractive\Core\AI\Transport\UnavailableTransport;
use NicheCleverInteractive\Core\Rest\ChatRoute;
use NicheCleverInteractive\Core\Budget\BudgetGate;
use NicheCleverInteractive\Core\Budget\WordPressBucketStore;
use NicheCleverInteractive\Core\AI\ConnectionTestService;
use NicheCleverInteractive\Core\Support\Translator;

final class Plugin
{
    private static ?self $instance = null;
    private ExtensionRegistryV1 $extensions;
    private SecretResolver $secrets;
    private SettingsPage $settingsPage;
    private InteractivePanel $interactivePanel;
    private ChatRoute $chatRoute;
    private ConversationsPage $conversationsPage;
    private ConversationPurge $conversationPurge;
    private ExtensionScreens $extensionScreens;
    private ProfileDirectory $profiles;
    private WordPressConversationStore $conversationStore;
    private ProfileConnectionTest $profileTest;
    private ProfileHub $profileHub;

    private function __construct()
    {
        $this->extensions = new ExtensionRegistryV1();
        $this->extensionScreens = new ExtensionScreens($this->extensions);
        $this->secrets = new SecretResolver();
        $profiles = new ProfileDirectory($this->extensions);
        $this->profiles = $profiles;
        $this->interactivePanel = new InteractivePanel($profiles);
        $transport = $this->transport();
        $budget = new BudgetGate(new WordPressBucketStore());
        $failureLog = new OptionFailureLog();
        $connectionTest = new ConnectionTestService($transport, $this->secrets, $budget, null, TransportMode::current(), $failureLog);
        $this->profileTest = new ProfileConnectionTest($profiles, $connectionTest);
        $this->profileHub = new ProfileHub($profiles, null, null, null, fn (): array => $this->extensionScreens->screenIds());
        $this->settingsPage = new SettingsPage(null, $connectionTest, null, $this->profileHub);
        $conversationStore = new WordPressConversationStore();
        $this->conversationsPage = new ConversationsPage(null, $conversationStore);
        $this->conversationPurge = new ConversationPurge($conversationStore, null, $profiles);
        $this->conversationStore = $conversationStore;
        $this->chatRoute = new ChatRoute(new ChatService($transport, $this->secrets, null, null, $budget, new ConversationRecorder($conversationStore, null, $profiles), $failureLog, $profiles));
    }

    public static function register(string $pluginFile): void
    {
        if (self::$instance !== null) {
            return;
        }

        self::$instance = new self();

        if (function_exists('register_activation_hook')) {
            register_activation_hook($pluginFile, [self::class, 'activate']);
            register_deactivation_hook($pluginFile, [self::class, 'deactivate']);
        }

        if (function_exists('add_action')) {
            add_action('plugins_loaded', [self::$instance, 'boot']);
        }
    }

    public function boot(): void
    {
        // Sites upgraded from an earlier candidate never re-run activation, so bring the schema current here.
        ConversationTable::ensureCurrent();
        ConversationPurge::ensureScheduled();

        $this->settingsPage->register();
        $this->conversationsPage->register();
        $this->extensionScreens->register();
        $this->profileTest->register();
        $this->profileHub->register();
        $this->conversationPurge->register();
        $this->interactivePanel->register();
        $this->chatRoute->register();

        if (function_exists('add_action')) {
            add_action('admin_init', [$this, 'registerPrivacyContent']);
        }
        if (function_exists('add_filter')) {
            add_filter('wp_privacy_personal_data_exporters', [$this, 'registerPrivacyExporter']);
            add_filter('wp_privacy_personal_data_erasers', [$this, 'registerPrivacyEraser']);
        }

        if (function_exists('do_action')) {
            do_action('ncli_core_api_v1_ready', $this->extensions);
        }

        // A governance extension (export, per-profile retention, erasure) gets Core's guarded archive, never the table.
        $this->profiles->attachArchive(new GuardedConversationArchive($this->conversationStore));
    }

    public static function activate(): void
    {
        if (!function_exists('get_option') || !function_exists('add_option')) {
            return;
        }

        if (get_option(CoreSettings::OPTION_NAME, null) === null) {
            add_option(CoreSettings::OPTION_NAME, CoreSettings::defaults(), '', false);
        }

        self::createRateBucketTable();
        // Advance the schema marker only once the table exists, so boot retries a failed creation.
        if (ConversationTable::create()) {
            update_option(CoreSettings::SCHEMA_OPTION, CoreSettings::SCHEMA_VERSION, true);
        }
        ConversationPurge::ensureScheduled();
    }

    public static function deactivate(): void
    {
        // Deliberately preserve NCI settings, rate data and any stored conversations for
        // recoverable deactivation; only the scheduled retention purge is stopped.
        ConversationPurge::unschedule();
    }

    /** The panel renderer wired with this site's profile directory; used by the block render file. */
    public static function panel(): InteractivePanel
    {
        return self::$instance !== null ? self::$instance->interactivePanel : new InteractivePanel();
    }

    public function extensions(): ExtensionRegistryV1
    {
        return $this->extensions;
    }

    public function readiness(): Readiness
    {
        $settings = CoreSettings::defaults();
        if (function_exists('get_option')) {
            $stored = get_option(CoreSettings::OPTION_NAME, $settings);
            if (is_array($stored)) {
                $settings = $stored;
            }
        }

        return Readiness::evaluate(CoreSettings::fromArray($settings), $this->secrets);
    }

    public function registerPrivacyContent(): void
    {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = '<p>' . $this->escapeHtml(Translator::text('Niche Clever Interactive sends each public visitor question to OpenAI when the site administrator has enabled the public panel.') . ' ' . $this->contextSentence() . ' ' . Translator::text('OpenAI File Search may use material in the administrator-selected vector store to generate an answer.')) . '</p>'
            . '<p>' . $this->escapeHtml(Translator::text('The plugin does not store the OpenAI API key in WordPress options. To enforce daily limits it stores a server-secret HMAC of the visitor network scope, rather than a raw IP address.')) . '</p>'
            . '<p>' . $this->escapeHtml(Translator::text('Conversation history is off by default. If the site owner turns it on, each successfully answered question and its answer are stored in the site database with a random conversation identifier, the time, and the plugin and corpus versions, until the owner deletes them or the chosen retention period ends. No IP address, account or browser identifier is stored with them. Visitors can type personal information into a question, so tell them if questions and answers are kept and for how long.')) . '</p>'
            . '<p>' . $this->escapeHtml(Translator::text('Site owners are responsible for their own OpenAI account, provider terms, billing, retention choices and privacy information.')) . '</p>';

        wp_add_privacy_policy_content(Translator::text('Niche Clever Interactive'), wp_kses_post($content));
    }

    /** The privacy-policy statement about earlier questions must match what Core actually sends. */
    private function contextSentence(): string
    {
        return $this->profiles->hasContextProvider()
            ? Translator::text('A limited number of earlier questions and answers from the same conversation may be sent with it, as set by the site administrator.')
            : Translator::text('Earlier questions in the same conversation are not sent with it.');
    }

    /**
     * @param array<string, mixed> $exporters
     * @return array<string, mixed>
     */
    public function registerPrivacyExporter($exporters)
    {
        if (!is_array($exporters)) {
            return $exporters;
        }

        $exporters['niche-clever-interactive'] = [
            'exporter_friendly_name' => Translator::text('Niche Clever Interactive'),
            'callback' => [$this, 'exportPersonalData'],
        ];

        return $exporters;
    }

    /**
     * @param array<string, mixed> $erasers
     * @return array<string, mixed>
     */
    public function registerPrivacyEraser($erasers)
    {
        if (!is_array($erasers)) {
            return $erasers;
        }

        $erasers['niche-clever-interactive'] = [
            'eraser_friendly_name' => Translator::text('Niche Clever Interactive'),
            'callback' => [$this, 'erasePersonalData'],
        ];

        return $erasers;
    }

    /**
     * Stored conversations carry no email address, account or other identifier, so
     * they cannot be matched to the person a request concerns.
     *
     * @return array{data: array<int, mixed>, done: bool}
     */
    public function exportPersonalData($emailAddress = '', $page = 1): array
    {
        return ['data' => [], 'done' => true];
    }

    /**
     * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
     */
    public function erasePersonalData($emailAddress = '', $page = 1): array
    {
        return [
            'items_removed' => false,
            'items_retained' => false,
            'messages' => [Translator::text('Niche Clever Interactive stores no email address, account or other identifier with a conversation, so records cannot be matched to this person automatically. A site owner can review and delete stored conversations from NC Interactive > Conversations.')],
            'done' => true,
        ];
    }

    private function transport(): ResponseTransport
    {
        if (TransportMode::current() === TransportMode::MOCK) {
            return new DeterministicMockTransport();
        }

        if (TransportMode::current() === TransportMode::LIVE) {
            return new OpenAiResponsesFileSearchTransport($this->secrets);
        }

        return new UnavailableTransport();
    }

    private static function createRateBucketTable(): void
    {
        global $wpdb;

        if (!function_exists('dbDelta') && defined('ABSPATH')) {
            $upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
            if (is_file($upgrade)) {
                require_once $upgrade;
            }
        }

        if (!isset($wpdb) || !is_object($wpdb) || !isset($wpdb->prefix) || !function_exists('dbDelta')) {
            return;
        }

        $table = $wpdb->prefix . 'ncli_core_rate_buckets';
        $collate = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            kind varchar(32) NOT NULL,
            scope_hash char(64) NOT NULL,
            period_start datetime NOT NULL,
            reserved_count bigint(20) unsigned NOT NULL DEFAULT 0,
            committed_count bigint(20) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY ncli_rate_scope_period (kind,scope_hash,period_start)
        ) {$collate} ENGINE=InnoDB;";

        dbDelta($sql);
    }

    private function escapeHtml(string $value): string
    {
        return function_exists('esc_html') ? esc_html($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
