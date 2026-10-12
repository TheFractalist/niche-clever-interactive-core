<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core;

use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Admin\SettingsPage;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
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

final class Plugin
{
    private static ?self $instance = null;
    private ExtensionRegistryV1 $extensions;
    private SecretResolver $secrets;
    private SettingsPage $settingsPage;
    private InteractivePanel $interactivePanel;
    private ChatRoute $chatRoute;

    private function __construct()
    {
        $this->extensions = new ExtensionRegistryV1();
        $this->secrets = new SecretResolver();
        $this->interactivePanel = new InteractivePanel();
        $transport = $this->transport();
        $budget = new BudgetGate(new WordPressBucketStore());
        $this->settingsPage = new SettingsPage(null, new ConnectionTestService($transport, $this->secrets, $budget, null, TransportMode::current()));
        $this->chatRoute = new ChatRoute(new ChatService($transport, $this->secrets, null, null, null, $budget));
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
        $this->settingsPage->register();
        $this->interactivePanel->register();
        $this->chatRoute->register();

        if (function_exists('do_action')) {
            do_action('ncli_core_api_v1_ready', $this->extensions);
        }
    }

    public static function activate(): void
    {
        if (!function_exists('get_option') || !function_exists('add_option')) {
            return;
        }

        if (get_option(CoreSettings::OPTION_NAME, null) === null) {
            add_option(CoreSettings::OPTION_NAME, CoreSettings::defaults(), '', false);
        }

        update_option(CoreSettings::SCHEMA_OPTION, CoreSettings::SCHEMA_VERSION, false);
        self::createRateBucketTable();
    }

    public static function deactivate(): void
    {
        // Deliberately preserve NCI settings and rate data for recoverable deactivation.
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
}
