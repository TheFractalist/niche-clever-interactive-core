<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

final class CachedAnswerV1
{
    private string $renderedAnswer;

    /**
     * @var list<string>
     */
    private array $sourceReferenceTokens;

    /**
     * @param list<string> $sourceReferenceTokens
     */
    public function __construct(string $renderedAnswer, array $sourceReferenceTokens)
    {
        if ($renderedAnswer === '') {
            throw new InvalidArgumentException('Cached answers must be non-empty.');
        }

        foreach ($sourceReferenceTokens as $token) {
            if (!is_string($token) || $token === '') {
                throw new InvalidArgumentException('Cached evidence tokens must be non-empty strings.');
            }
        }

        $this->renderedAnswer = $renderedAnswer;
        $this->sourceReferenceTokens = array_values($sourceReferenceTokens);
    }
}
