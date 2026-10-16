<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

final class TransportRequest
{
    private string $question;
    private string $instructions;
    private string $responsePreset;
    private string $policyFingerprint;
    private string $corpusFingerprint;
    private string $vectorStoreReference;
    private RequestOptions $options;
    /** @var list<\NicheCleverInteractive\Core\Api\V1\Value\EarlierTurnV1> */
    private array $earlierTurns;

    public function __construct(string $question, string $instructions, string $responsePreset, string $policyFingerprint, string $corpusFingerprint, string $vectorStoreReference = '', ?RequestOptions $options = null, array $earlierTurns = [])
    {
        $this->options = $options ?? new RequestOptions();
        $this->earlierTurns = $earlierTurns;
        $this->question = $question;
        $this->instructions = $instructions;
        $this->responsePreset = $responsePreset;
        $this->policyFingerprint = $policyFingerprint;
        $this->corpusFingerprint = $corpusFingerprint;
        $this->vectorStoreReference = $vectorStoreReference;
    }

    public function question(): string
    {
        return $this->question;
    }

    public function instructions(): string
    {
        return $this->instructions;
    }

    public function responsePreset(): string
    {
        return $this->responsePreset;
    }

    public function policyFingerprint(): string
    {
        return $this->policyFingerprint;
    }

    public function corpusFingerprint(): string
    {
        return $this->corpusFingerprint;
    }

    public function vectorStoreReference(): string
    {
        return $this->vectorStoreReference;
    }

    /**
     * Earlier turns of the conversation, oldest first, already bounded by Core.
     *
     * @return list<\NicheCleverInteractive\Core\Api\V1\Value\EarlierTurnV1>
     */
    public function earlierTurns(): array
    {
        return $this->earlierTurns;
    }

    public function options(): RequestOptions
    {
        return $this->options;
    }
}
