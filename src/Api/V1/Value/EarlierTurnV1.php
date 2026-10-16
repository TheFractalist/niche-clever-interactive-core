<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

/**
 * One earlier question and the answer it received, offered as conversation context. Bounded to the
 * same sizes Core accepts for a live question and answer, plain text only.
 */
final class EarlierTurnV1
{
    public const MAX_QUESTION = 2000;
    public const MAX_ANSWER = 5000;

    private string $question;
    private string $answer;

    public function __construct(string $question, string $answer)
    {
        // Checked before trimming, because trim() would silently strip a trailing NUL byte.
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $question . $answer) === 1) {
            throw new InvalidArgumentException('An earlier turn contains control characters.');
        }

        $question = trim($question);
        $answer = trim($answer);
        if ($question === '' || strlen($question) > self::MAX_QUESTION || $answer === '' || strlen($answer) > self::MAX_ANSWER) {
            throw new InvalidArgumentException('An earlier turn is empty or out of bounds.');
        }

        $this->question = $question;
        $this->answer = $answer;
    }

    public function question(): string
    {
        return $this->question;
    }

    public function answer(): string
    {
        return $this->answer;
    }
}
