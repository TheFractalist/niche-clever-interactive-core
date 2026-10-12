<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

use NicheCleverInteractive\Core\Frontend\InteractivePanel;

return (new InteractivePanel())->renderInteractive(is_array($attributes ?? null) ? $attributes : []);
