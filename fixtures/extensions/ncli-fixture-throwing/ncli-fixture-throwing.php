<?php
/**
 * Plugin Name: NCI Fixture Throwing Extension
 * Description: Disposable fixture only. It throws while Core hosts a registered screen.
 * Version: 0.0.1
 */

defined('ABSPATH') || exit;

use NicheCleverInteractive\Core\Api\V1\Contracts\WorkspaceExtensionV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceContextV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceScreenV1;

add_action('ncli_core_api_v1_ready', static function ($registry): void {
    // See the malformed fixture: this declaration waits until Core has registered its autoloader.
    if (!class_exists('NcliFixtureThrowingWorkspace')) {
        class NcliFixtureThrowingWorkspace implements WorkspaceExtensionV1
        {
            public function screens(): array
            {
                return [new WorkspaceScreenV1('settings')];
            }

            public function render(string $screenId, WorkspaceContextV1 $context): void
            {
                throw new RuntimeException('Fixture extension rendering failure.');
            }
        }
    }
    if (is_object($registry) && method_exists($registry, 'register')) {
        $registry->register('workspace_extension', new NcliFixtureThrowingWorkspace());
    }
});
