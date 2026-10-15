<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Conversation;

use NicheCleverInteractive\Core\Configuration\CoreSettings;

/**
 * Owns the prefixed NCI conversation table definition and its idempotent
 * creation. The table name is derived solely from $wpdb->prefix.
 */
final class ConversationTable
{
    public const SUFFIX = 'ncli_core_conversation_turns';

    public static function name(): ?string
    {
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb) || !isset($wpdb->prefix) || !is_string($wpdb->prefix)) {
            return null;
        }

        return $wpdb->prefix . self::SUFFIX;
    }

    public static function create(): bool
    {
        global $wpdb;

        if (!function_exists('dbDelta') && defined('ABSPATH')) {
            $upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
            if (is_file($upgrade)) {
                require_once $upgrade;
            }
        }

        $table = self::name();
        if ($table === null || !function_exists('dbDelta')) {
            return false;
        }

        $collate = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id char(32) NOT NULL,
            profile_id varchar(64) NOT NULL,
            question text NOT NULL,
            answer mediumtext NOT NULL,
            core_version varchar(32) NOT NULL DEFAULT '',
            corpus_version varchar(128) NOT NULL DEFAULT '',
            policy_fingerprint char(64) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY ncli_conversation (conversation_id),
            KEY ncli_created (created_at)
        ) {$collate} ENGINE=InnoDB;";

        dbDelta($sql);

        return true;
    }

    /**
     * Create the table when the stored schema marker is behind, then advance it.
     * Safe to call on every request; it does nothing once current.
     */
    public static function ensureCurrent(): void
    {
        if (!function_exists('get_option') || !function_exists('update_option')) {
            return;
        }

        $stored = get_option(CoreSettings::SCHEMA_OPTION, 0);
        if ((int) $stored >= CoreSettings::SCHEMA_VERSION) {
            return;
        }

        if (self::create()) {
            // Autoloaded so the per-request version check costs no extra query once current.
            update_option(CoreSettings::SCHEMA_OPTION, CoreSettings::SCHEMA_VERSION, true);
        }
    }
}
