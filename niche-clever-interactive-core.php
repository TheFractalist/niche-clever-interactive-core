<?php
/**
 * Plugin Name: Niche Clever Interactive Core
 * Description: A non-production Core-first development build for Niche Clever Interactive.
 * Version: 1.0.0-rc.4
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * Text Domain: niche-clever-interactive-core
 *
 * @package NicheCleverInteractive\Core
 */

defined('ABSPATH') || exit;

const NCLI_CORE_VERSION = '1.0.0-rc.4';
const NCLI_CORE_API_MAJOR = 1;

require_once __DIR__ . '/src/autoload.php';

use NicheCleverInteractive\Core\Plugin;

Plugin::register(__FILE__);
