<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

final class WorkspaceContextV1
{
    private bool $coreAuthorised;
    private string $actionNonce;
    private string $screenId;
    private string $nonceAction;

    public function __construct(bool $coreAuthorised, string $actionNonce, string $screenId = '', string $nonceAction = '')
    {
        if (!$coreAuthorised || $actionNonce === '') {
            throw new InvalidArgumentException('Core must authorise the workspace action and issue its nonce first.');
        }

        if ($screenId !== '') {
            // Reuses the permitted-identifier rule; throws for anything else.
            new WorkspaceScreenV1($screenId);
        }

        if ($nonceAction !== '' && preg_match('/^[a-z0-9_]{3,100}$/', $nonceAction) !== 1) {
            throw new InvalidArgumentException('Workspace nonce actions must be bounded lower-case identifiers.');
        }

        $this->coreAuthorised = $coreAuthorised;
        $this->actionNonce = $actionNonce;
        $this->screenId = $screenId;
        $this->nonceAction = $nonceAction;
    }

    public function isCoreAuthorised(): bool
    {
        return $this->coreAuthorised;
    }

    /** Nonce value Core issued for this screen. An extension form must submit it back. */
    public function nonce(): string
    {
        return $this->actionNonce;
    }

    /** Action name the nonce was created for; an extension handler verifies against it. */
    public function nonceAction(): string
    {
        return $this->nonceAction;
    }

    public function screenId(): string
    {
        return $this->screenId;
    }
}
