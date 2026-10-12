<?php

namespace NicheCleverInteractive\Core\Api\V1\Contracts;

use NicheCleverInteractive\Core\Api\V1\Value\CompletedTurnV1;

interface CompletedTurnRecorderV1
{
    public function record(CompletedTurnV1 $turn): void;
}
