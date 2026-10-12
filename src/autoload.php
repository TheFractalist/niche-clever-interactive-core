<?php
/**
 * Minimal class loader for the non-production contract scaffold.
 *
 * @package NicheCleverInteractive\Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'NicheCleverInteractive\\Core\\';

        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

        if (is_file($path)) {
            require_once $path;
        }
    }
);
