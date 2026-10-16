<?php

namespace NicheCleverInteractive\Core\Api\V1\Contracts;

use NicheCleverInteractive\Core\Api\V1\Value\PanelColoursV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;

interface PanelColoursProviderV1
{
    /**
     * Optional colours for the question and answer blocks of one profile's panel, including the
     * default profile. Core validates every value, ignores any pair whose text would be hard to
     * read on its background, and emits only fixed CSS custom properties. Returning null, an
     * exception or a wrong type leaves the style's own colours in place.
     */
    public function coloursFor(ProfileIdV1 $profileId): ?PanelColoursV1;
}
