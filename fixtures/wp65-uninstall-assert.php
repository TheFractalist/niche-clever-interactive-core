<?php

$expect = $args[0] ?? '';
if ($expect !== 'retained' && $expect !== 'deleted') {
    throw new RuntimeException('Unknown uninstall assertion.');
}
global $wpdb;
$rateTable = $wpdb->prefix . 'ncli_core_rate_buckets';
$conversationTable = $wpdb->prefix . 'ncli_core_conversation_turns';
$settings = get_option('ncli_core_settings', null);
$rateExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $rateTable)) === $rateTable;
$conversationExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $conversationTable)) === $conversationTable;
if ($expect === 'retained' && (!is_array($settings) || !$rateExists || !$conversationExists)) {
    throw new RuntimeException('The opt-out uninstall did not retain Core data.');
}
if ($expect === 'deleted' && ($settings !== null || $rateExists || $conversationExists)) {
    throw new RuntimeException('The opt-in uninstall did not remove only Core data.');
}
echo wp_json_encode(['uninstall' => $expect, 'asserted' => true]) . PHP_EOL;
