<?php

use NicheCleverInteractive\Core\Admin\ExtensionScreens;
use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;

$mode = $args[0] ?? '';
$registry = new ExtensionRegistryV1();
if ($mode === 'incompatible') {
    try {
        $registry->register('workspace_extension', new stdClass());
        throw new RuntimeException('The incompatible extension registration was accepted.');
    } catch (InvalidArgumentException $exception) {
        // Expected: Core rejects a non-contract implementation before it can expose a route or UI.
    }
    if ($registry->implementation('workspace_extension') !== null) {
        throw new RuntimeException('An incompatible extension was retained by the Core registry.');
    }
} elseif ($mode === 'malformed') {
    if (!class_exists('NcliFixtureMalformedWorkspace')) {
        throw new RuntimeException('The malformed fixture plugin was not loaded.');
    }
    $registry->register('workspace_extension', new NcliFixtureMalformedWorkspace());
    $html = (new ExtensionScreens($registry))->bodyHtml('settings');
    if (str_contains($html, '<script') || stripos($html, 'javascript:') !== false || stripos($html, 'onclick=') !== false || !str_contains($html, 'Safe fixture control')) {
        throw new RuntimeException('Core did not fail closed at the hosted-markup boundary.');
    }
} elseif ($mode === 'throwing') {
    if (!class_exists('NcliFixtureThrowingWorkspace')) {
        throw new RuntimeException('The throwing fixture plugin was not loaded.');
    }
    $registry->register('workspace_extension', new NcliFixtureThrowingWorkspace());
    $html = (new ExtensionScreens($registry))->bodyHtml('settings');
    if (!str_contains($html, 'temporarily unavailable')) {
        throw new RuntimeException('A throwing extension did not receive Core\'s generic safe notice.');
    }
} elseif ($mode !== 'absent') {
    throw new RuntimeException('Unknown extension fixture mode.');
}

echo wp_json_encode(['mode' => $mode, 'route_owner' => 'core', 'provider_bypass' => false], JSON_UNESCAPED_SLASHES) . PHP_EOL;
