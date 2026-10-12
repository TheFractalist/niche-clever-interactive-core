<?php

namespace NicheCleverInteractive\Core\Api\V1\Contracts;

use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceContextV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceScreenV1;

interface WorkspaceExtensionV1
{
    /**
     * @return list<WorkspaceScreenV1>
     */
    public function screens(): array;

    public function render(string $screenId, WorkspaceContextV1 $context): void;
}
