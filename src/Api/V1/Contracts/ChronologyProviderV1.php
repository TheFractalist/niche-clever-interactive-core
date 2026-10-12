<?php

namespace NicheCleverInteractive\Core\Api\V1\Contracts;

use NicheCleverInteractive\Core\Api\V1\Value\ChronologyFactV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;

interface ChronologyProviderV1
{
    /**
     * @return list<ChronologyFactV1>
     */
    public function factsFor(ProfileIdV1 $profileId): array;
}
