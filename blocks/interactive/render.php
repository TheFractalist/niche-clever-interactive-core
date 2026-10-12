<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Frontend\InteractivePanel;

return (new InteractivePanel())->renderInteractive(is_array($attributes ?? null) ? $attributes : []);
