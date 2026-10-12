<?php

namespace NicheCleverInteractive\Core\Api\V1\Contracts;

use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\ResolvedProfileV1;

interface ProfileResolverV1
{
    public function resolve(ProfileIdV1 $profileId): ?ResolvedProfileV1;
}
