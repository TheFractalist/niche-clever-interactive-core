<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

final class WorkspaceContextV1
{
    private bool $coreAuthorised;
    private string $actionNonce;

    public function __construct(bool $coreAuthorised, string $actionNonce)
    {
        if (!$coreAuthorised || $actionNonce === '') {
            throw new InvalidArgumentException('Core must authorise the workspace action and issue its nonce first.');
        }

        $this->coreAuthorised = $coreAuthorised;
        $this->actionNonce = $actionNonce;
    }
}
