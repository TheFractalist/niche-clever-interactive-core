<?php
/**
 * Plugin Name: NCI Fixture Malformed Extension
 * Description: Disposable fixture only. It gives Core hostile hosted markup to sanitise.
 * Version: 0.0.1
 */

defined('ABSPATH') || exit;

use NicheCleverInteractive\Core\Api\V1\Contracts\WorkspaceExtensionV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceContextV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceScreenV1;

add_action('ncli_core_api_v1_ready', static function ($registry): void {
    // The Core plugin's autoloader becomes available only once its readiness action runs; an
    // extension loaded alphabetically before Core must therefore not declare this contract early.
    if (!class_exists('NcliFixtureMalformedWorkspace')) {
        class NcliFixtureMalformedWorkspace implements WorkspaceExtensionV1
        {
            public function screens(): array
            {
                return [new WorkspaceScreenV1('settings')];
            }

            public function render(string $screenId, WorkspaceContextV1 $context): void
            {
                echo '<script>window.ncliFixtureUnsafe=true;</script><a href="javascript:alert(1)" onclick="alert(1)">Unsafe</a><button type="button" data-fixture="safe">Safe fixture control</button>';
            }
        }
    }
    if (is_object($registry) && method_exists($registry, 'register')) {
        $registry->register('workspace_extension', new NcliFixtureMalformedWorkspace());
    }
});
