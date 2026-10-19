<?php

declare(strict_types=1);

/**
 * Exercises uninstall.php in isolated child processes with a recording stand-in
 * for the WordPress functions and $wpdb it uses.
 */

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$source = (string) file_get_contents($root . '/uninstall.php');
$assert(str_contains($source, "defined( 'WP_UNINSTALL_PLUGIN' )"), 'uninstall.php must refuse direct access.');
$assert(stripos($source, 'wpip') === false && stripos($source, 'askfolio') === false, 'uninstall.php must never reference legacy storage.');
$assert(!preg_match('/DELETE\s+FROM|TRUNCATE|LIKE\s/i', $source), 'uninstall.php must not use wildcard or bulk row deletion.');

$run = static function (array $settings, bool $multisite = false, array $siteSettings = []) use ($root): array {
    $harness = <<<'PHP'
<?php
define('ABSPATH', '/tmp/');
define('WP_UNINSTALL_PLUGIN', 'niche-clever-interactive/niche-clever-interactive-core.php');
$GLOBALS['log'] = [];
$GLOBALS['site'] = 1;
$GLOBALS['settingsBySite'] = json_decode(getenv('NCLI_SETTINGS'), true);
$GLOBALS['multisite'] = getenv('NCLI_MULTISITE') === '1';
function is_multisite() { return $GLOBALS['multisite']; }
function get_sites($args) { return array_map('intval', array_keys($GLOBALS['settingsBySite'])); }
function switch_to_blog($id) { $GLOBALS['site'] = $id; $GLOBALS['wpdb']->prefix = $id === 1 ? 'wp_' : 'wp_' . $id . '_'; }
function restore_current_blog() { $GLOBALS['site'] = 1; $GLOBALS['wpdb']->prefix = 'wp_'; }
function get_option($name, $default = false) {
    if ($name !== 'ncli_core_settings') { return $default; }
    $all = $GLOBALS['settingsBySite'];
    return array_key_exists((string) $GLOBALS['site'], $all) ? $all[(string) $GLOBALS['site']] : $default;
}
function delete_option($name) { $GLOBALS['log'][] = 'delete_option:' . $GLOBALS['site'] . ':' . $name; return true; }
function wp_clear_scheduled_hook($hook) { $GLOBALS['log'][] = 'clear_cron:' . $GLOBALS['site'] . ':' . $hook; }
function esc_sql($value) { return $value; }
$GLOBALS['wpdb'] = new class { public $prefix = 'wp_'; public function query($sql) { $GLOBALS['log'][] = 'sql:' . $sql; return 1; } };
require getenv('NCLI_UNINSTALL');
echo json_encode($GLOBALS['log']);
PHP;
    $file = tempnam(sys_get_temp_dir(), 'ncli-uninstall');
    file_put_contents($file, $harness);
    // putenv keeps the child process portable across POSIX and Windows shells.
    putenv('NCLI_UNINSTALL=' . $root . '/uninstall.php');
    putenv('NCLI_MULTISITE=' . ($multisite ? '1' : '0'));
    putenv('NCLI_SETTINGS=' . json_encode($multisite ? $siteSettings : ['1' => $settings]));
    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1');
    unlink($file);
    $decoded = json_decode((string) $output, true);
    return is_array($decoded) ? $decoded : ['unparsed:' . $output];
};

// Default: nothing is removed.
$assert($run([]) === [], 'With no stored settings uninstall must remove nothing.');
$assert($run(['lifecycle' => ['delete_on_uninstall' => false]]) === [], 'With delete-on-uninstall off, nothing may be removed.');
$assert($run(['lifecycle' => ['delete_on_uninstall' => 'yes']]) === [], 'Only the boolean true may enable deletion.');
$assert($run(['lifecycle' => ['delete_on_uninstall' => 1]]) === [], 'A truthy non-boolean must not enable deletion.');

// Opt-in: exactly the plugin-owned set.
$log = $run(['lifecycle' => ['delete_on_uninstall' => true]]);
sort($log);
$expected = [
    'clear_cron:1:ncli_core_purge_conversations',
    'delete_option:1:ncli_core_last_failure',
    'delete_option:1:ncli_core_schema_version',
    'delete_option:1:ncli_core_settings',
    'sql:DROP TABLE IF EXISTS wp_ncli_core_conversation_turns',
    'sql:DROP TABLE IF EXISTS wp_ncli_core_rate_buckets',
];
sort($expected);
$assert($log === $expected, 'An opted-in uninstall must remove exactly the plugin-owned items. Got: ' . json_encode($log));

// Multisite: each site follows its own choice.
$log = $run([], true, [
    '1' => ['lifecycle' => ['delete_on_uninstall' => true]],
    '2' => ['lifecycle' => ['delete_on_uninstall' => false]],
]);
$assert(in_array('sql:DROP TABLE IF EXISTS wp_ncli_core_conversation_turns', $log, true), 'An opted-in site must be cleaned.');
$assert(array_filter($log, static fn (string $entry): bool => str_contains($entry, 'wp_2_') || str_contains($entry, ':2:')) === [], 'A site that did not opt in must be left untouched.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C6 uninstall checks verified\n";
