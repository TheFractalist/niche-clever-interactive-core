<?php

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use NicheCleverInteractive\Core\Plugin;

return Plugin::panel()->renderInteractive(is_array($attributes ?? null) ? $attributes : []);
