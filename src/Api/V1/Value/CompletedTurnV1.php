<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use DateTimeImmutable;
use InvalidArgumentException;

final class CompletedTurnV1
{
    private string $conversationId;
    private ProfileIdV1 $profileId;
    private DateTimeImmutable $completedAt;
    private string $question;
    private string $answer;
    private string $coreVersion;
    private ?string $proVersion;
    private string $corpusVersion;
    private string $answerFingerprint;
    private string $evidenceFingerprint;
    private string $sourceKind;

    public function __construct(
        string $conversationId,
        ProfileIdV1 $profileId,
        DateTimeImmutable $completedAt,
        string $question,
        string $answer,
        string $coreVersion,
        ?string $proVersion,
        string $corpusVersion,
        string $answerFingerprint,
        string $evidenceFingerprint,
        string $sourceKind
    ) {
        foreach ([$conversationId, $question, $answer, $coreVersion, $corpusVersion, $answerFingerprint, $evidenceFingerprint] as $value) {
            if ($value === '') {
                throw new InvalidArgumentException('Completed-turn contract values must be explicit.');
            }
        }

        if ($proVersion !== null && $proVersion === '') {
            throw new InvalidArgumentException('Pro version is either absent or explicit.');
        }

        if (!in_array($sourceKind, ['cache', 'provider'], true)) {
            throw new InvalidArgumentException('Completed turns originate only from validated cache or provider outcomes.');
        }

        $this->conversationId = $conversationId;
        $this->profileId = $profileId;
        $this->completedAt = $completedAt;
        $this->question = $question;
        $this->answer = $answer;
        $this->coreVersion = $coreVersion;
        $this->proVersion = $proVersion;
        $this->corpusVersion = $corpusVersion;
        $this->answerFingerprint = $answerFingerprint;
        $this->evidenceFingerprint = $evidenceFingerprint;
        $this->sourceKind = $sourceKind;
    }

    public function conversationId(): string
    {
        return $this->conversationId;
    }

    public function profileId(): ProfileIdV1
    {
        return $this->profileId;
    }

    public function completedAt(): DateTimeImmutable
    {
        return $this->completedAt;
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

    public function proVersion(): ?string
    {
        return $this->proVersion;
    }

    public function corpusVersion(): string
    {
        return $this->corpusVersion;
    }

    public function answerFingerprint(): string
    {
        return $this->answerFingerprint;
    }

    public function evidenceFingerprint(): string
    {
        return $this->evidenceFingerprint;
    }

    /** Either `cache` or `provider`. */
    public function sourceKind(): string
    {
        return $this->sourceKind;
    }
}
