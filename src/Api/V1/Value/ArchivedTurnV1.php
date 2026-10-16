<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

/**
 * One stored turn as an administrator-only extension may read it for export. It carries the text
 * as stored and its place in the log, and nothing about the visitor: Core stores no address,
 * account or cookie with a conversation.
 */
final class ArchivedTurnV1
{
    private int $id;
    private string $conversationId;
    private string $profileId;
    private string $createdAt;
    private string $question;
    private string $answer;

    public function __construct(int $id, string $conversationId, string $profileId, string $createdAt, string $question, string $answer)
    {
        if ($id < 1 || preg_match('/^[a-f0-9]{32}$/D', $conversationId) !== 1 || preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $profileId) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $createdAt) !== 1) {
            throw new InvalidArgumentException('An archived turn identifier or time is malformed.');
        }

        $this->id = $id;
        $this->conversationId = $conversationId;
        $this->profileId = $profileId;
        $this->createdAt = $createdAt;
        $this->question = $question;
        $this->answer = $answer;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function conversationId(): string
    {
        return $this->conversationId;
    }

    public function profileId(): string
    {
        return $this->profileId;
    }

    /** UTC, `Y-m-d H:i:s`. */
    public function createdAt(): string
    {
        return $this->createdAt;
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
