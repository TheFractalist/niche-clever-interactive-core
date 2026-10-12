<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

final class WorkspaceScreenV1
{
    /**
     * @var list<string>
     */
    private const PERMITTED_IDS = ['profiles', 'chronology', 'questions', 'collections', 'cache', 'archive'];

    private string $id;

    public function __construct(string $id)
    {
        if (!in_array($id, self::PERMITTED_IDS, true)) {
            throw new InvalidArgumentException('Workspace screens must use a Core-permitted identifier.');
        }

        $this->id = $id;
    }

    public function id(): string
    {
        return $this->id;
    }
}
