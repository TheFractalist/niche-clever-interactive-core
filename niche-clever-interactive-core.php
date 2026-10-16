<?php
/**
 * Plugin Name: Niche Clever Interactive
 * Description: A configurable question-and-answer panel grounded in your prepared OpenAI File Search material.
 * Version: 1.0.0-rc.10
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * Author: Niche Clever
 * Author URI: https://nicheclever.co.uk/
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: niche-clever-interactive
 *
 * @package NicheCleverInteractive\Core
 */

defined('ABSPATH') || exit;

const NCLI_CORE_VERSION = '1.0.0-rc.10';
const NCLI_CORE_API_MAJOR = 1;
const NCLI_CORE_API_MINOR = 1;

require_once __DIR__ . '/src/autoload.php';

use NicheCleverInteractive\Core\Plugin;

Plugin::register(__FILE__);
