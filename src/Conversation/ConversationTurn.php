<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Conversation;

use InvalidArgumentException;

/**
 * One stored question-and-answer turn. Holds only the displayed text and
 * non-secret provenance; never an address, identity, payload or retrieved text.
 */
final class ConversationTurn
{
    public const MAX_QUESTION_BYTES = 2000;
    public const MAX_ANSWER_BYTES = 5000;

    private string $conversationId;
    private string $profileId;
    private string $question;
    private string $answer;
    private string $coreVersion;
    private string $corpusVersion;
    private string $policyFingerprint;
    private string $createdAt;

    public function __construct(
        string $conversationId,
        string $profileId,
        string $question,
        string $answer,
        string $coreVersion,
        string $corpusVersion,
        string $policyFingerprint,
        string $createdAt
    ) {
        if (!ConversationId::isValid($conversationId)) {
            throw new InvalidArgumentException('A conversation turn needs an opaque conversation identifier.');
        }
        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $profileId) !== 1) {
            throw new InvalidArgumentException('A conversation turn needs an explicit profile identifier.');
        }
        if ($question === '' || strlen($question) > self::MAX_QUESTION_BYTES || $answer === '' || strlen($answer) > self::MAX_ANSWER_BYTES) {
            throw new InvalidArgumentException('A conversation turn text is empty or out of bounds.');
        }
        if (strlen($coreVersion) > 32 || strlen($corpusVersion) > 128 || preg_match('/^([a-f0-9]{64})?$/D', $policyFingerprint) !== 1) {
            throw new InvalidArgumentException('A conversation turn provenance value is malformed.');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $createdAt) !== 1) {
            throw new InvalidArgumentException('A conversation turn needs a UTC timestamp.');
        }

        $this->conversationId = $conversationId;
        $this->profileId = $profileId;
        $this->question = $question;
        $this->answer = $answer;
        $this->coreVersion = $coreVersion;
        $this->corpusVersion = $corpusVersion;
        $this->policyFingerprint = $policyFingerprint;
        $this->createdAt = $createdAt;
    }

    public function conversationId(): string
    {
        return $this->conversationId;
    }

    public function profileId(): string
    {
        return $this->profileId;
    }

    public function question(): string
    {
        return $this->question;
    }

    public function answer(): string
    {
        return $this->answer;
    }

    public function coreVersion(): string
    {
        return $this->coreVersion;
    }

    public function corpusVersion(): string
    {
        return $this->corpusVersion;
    }

    public function policyFingerprint(): string
    {
        return $this->policyFingerprint;
    }

    /** UTC, `Y-m-d H:i:s`. */
    public function createdAt(): string
    {
        return $this->createdAt;
    }
}
