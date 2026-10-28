<?php

use NicheCleverInteractive\Core\Configuration\ConnectionFingerprint;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Security\PromptPolicy;

if (!defined('NCLI_CORE_MOCK_TRANSPORT') || NCLI_CORE_MOCK_TRANSPORT !== true) {
    throw new RuntimeException('Fixture bootstrap requires the deterministic mock transport.');
}

$values = CoreSettings::defaults();
$values['enabled'] = true;
$values['profile'] = [
    'id' => CoreSettings::DEFAULT_PROFILE_ID,
    'corpus_version' => 'fixture-corpus-1',
    'vector_store_reference' => 'fixture-vector-store',
];
$values['limits'] = [
    'visitor_requests_per_day' => 2,
    'provider_requests_per_day' => 2,
];
$values['conversation_log'] = [
    'enabled' => true,
    'retention_days' => 90,
];
$values['streaming'] = ['progressive' => true];

$settings = CoreSettings::fromArray($values);
$fingerprint = ConnectionFingerprint::forSettings($settings, new SecretResolver(), new PromptPolicy(), TransportMode::MOCK);
if (!is_string($fingerprint)) {
    throw new RuntimeException('The deterministic fixture connection fingerprint was not created.');
}
$settings = $settings->withConnectionTest('passed', $fingerprint, '2026-10-27T12:00:00Z');
update_option(CoreSettings::OPTION_NAME, $settings->toArray(), false);

$existing = get_page_by_path('ncli-fixture-panel', OBJECT, 'page');
if (!$existing instanceof WP_Post) {
    $pageId = wp_insert_post([
        'post_title' => 'NCI fixture panel',
        'post_name' => 'ncli-fixture-panel',
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => '[ncli_interactive]',
    ], true);
    if (is_wp_error($pageId)) {
        throw new RuntimeException('The fixture public page could not be created.');
    }
}

update_option('permalink_structure', '/%postname%/');
flush_rewrite_rules(false);

echo wp_json_encode([
    'mock_transport' => true,
    'core_enabled' => $settings->isEnabled(),
    'progressive' => $settings->progressiveStreaming(),
    'public_page' => home_url('/ncli-fixture-panel/'),
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
