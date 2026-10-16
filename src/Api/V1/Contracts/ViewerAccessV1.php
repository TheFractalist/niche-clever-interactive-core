<?php

namespace NicheCleverInteractive\Core\Api\V1\Contracts;

use NicheCleverInteractive\Core\Api\V1\Value\AccessDecisionV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;

interface ViewerAccessV1
{
    /**
     * Decide whether the current viewer may use a non-default profile. Core never calls this
     * for its own default profile. An exception, a wrong return type or an invalid decision
     * is treated by Core as denied.
     */
    public function decide(ProfileIdV1 $profileId): AccessDecisionV1;
}
