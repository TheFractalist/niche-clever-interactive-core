<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

final class CachedAnswerV1
{
    private string $renderedAnswer;

    public function __construct(string $renderedAnswer)
    {
        if ($renderedAnswer === '') {
            throw new InvalidArgumentException('Cached answers must be non-empty.');
        }

        $this->renderedAnswer = $renderedAnswer;
    }
}
