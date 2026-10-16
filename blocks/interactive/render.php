<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

use NicheCleverInteractive\Core\Plugin;

return Plugin::panel()->renderInteractive(is_array($attributes ?? null) ? $attributes : []);
