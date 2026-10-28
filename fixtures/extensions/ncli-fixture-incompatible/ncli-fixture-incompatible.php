<?php
/**
 * Plugin Name: NCI Fixture Incompatible Extension
 * Description: Disposable fixture only. It asks Core to reject a non-contract extension object.
 * Version: 0.0.1
 */

defined('ABSPATH') || exit;

add_action('ncli_core_api_v1_ready', static function ($registry): void {
    if (!is_object($registry) || !method_exists($registry, 'register')) {
        return;
    }
    try {
        $registry->register('workspace_extension', new stdClass());
    } catch (Throwable $exception) {
        // Core's rejection is the expected fixture result.
    }
});
