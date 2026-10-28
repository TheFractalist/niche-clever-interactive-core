<?php

use NicheCleverInteractive\Core\Admin\ExtensionScreens;
use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Profile\ProfileRuntime;
use NicheCleverInteractive\Premium\Admin\GovernanceScreen;
use NicheCleverInteractive\Premium\Admin\SettingsScreen;
use NicheCleverInteractive\Premium\Admin\Workspace;
use NicheCleverInteractive\Premium\Governance\AuditLog;
use NicheCleverInteractive\Premium\Profiles\OptionProfileStore;
use NicheCleverInteractive\Premium\Profiles\Profile;
use NicheCleverInteractive\Premium\Profiles\ProfileResolver;
use NicheCleverInteractive\Premium\Support\PremiumSettings;

if (!class_exists('NicheCleverInteractive\\Premium\\Plugin') || !NicheCleverInteractive\Premium\Plugin::isCompatible()) {
    throw new RuntimeException('The archived Premium plugin did not complete its Core v1 handshake.');
}

$defaults = CoreSettings::fromArray((array) get_option(CoreSettings::OPTION_NAME, []));
$store = new OptionProfileStore();
$base = [
    'enabled' => true,
    'vector_store_reference' => 'fixture-premium-vector-store',
    'corpus_version' => 'fixture-premium-corpus-1',
    'guidance' => '',
    'preset' => 'balanced',
    'panel_style' => 'classic',
    'visitor_requests_per_day' => 100,
];
$inherited = new Profile('fixture-inherited', 'Fixture inherited', true, $base['vector_store_reference'], $base['corpus_version'], '', 'balanced', [], 'classic', 100);
$overridden = new Profile('fixture-overridden', 'Fixture overridden', true, $base['vector_store_reference'], $base['corpus_version'], '', 'balanced', ['processing_message' => 'Premium fixture is preparing an answer…'], 'classic', 100);
$store->save($inherited);
$store->save($overridden);
$resolver = new ProfileResolver($store);
$inheritedResolved = $resolver->resolve(new ProfileIdV1('fixture-inherited'));
$overriddenResolved = $resolver->resolve(new ProfileIdV1('fixture-overridden'));
if ($inheritedResolved === null || $overriddenResolved === null) {
    throw new RuntimeException('The archived Premium profile resolver did not provide its named profiles.');
}
$inheritedRuntime = ProfileRuntime::fromResolved($inheritedResolved, $defaults);
$overriddenRuntime = ProfileRuntime::fromResolved($overriddenResolved, $defaults);
if ($inheritedRuntime === null || $overriddenRuntime === null) {
    throw new RuntimeException('The archived Premium profile resolver did not provide a usable named profile.');
}
if ($inheritedRuntime->processingMessage() !== $defaults->processingMessage() || $overriddenRuntime->processingMessage() !== 'Premium fixture is preparing an answer…') {
    throw new RuntimeException('Named-profile processing-message inheritance or override failed.');
}

$registry = new ExtensionRegistryV1();
$registry->register('workspace_extension', new Workspace([
    new SettingsScreen(new PremiumSettings(), []),
    new GovernanceScreen(new AuditLog(), ['ncip_view' => 'exports']),
]));
$screens = new ExtensionScreens($registry);
$settingsHtml = $screens->bodyHtml('settings');
$governanceHtml = $screens->bodyHtml('archive');
if (!str_contains($settingsHtml, 'Delete Premium data on uninstall') || !str_contains($governanceHtml, 'Export all profiles')) {
    throw new RuntimeException('The archived Premium hosted settings or governance form did not render.');
}
if (str_contains($settingsHtml, '<script') || str_contains($governanceHtml, '<script')) {
    throw new RuntimeException('Core did not retain hosted-markup ownership for Premium screens.');
}

echo wp_json_encode([
    'handshake' => true,
    'profile_hub_contract' => true,
    'hosted_settings' => true,
    'hosted_governance' => true,
    'processing_inheritance' => true,
    'processing_override' => true,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
