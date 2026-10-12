<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

final class TransportRequest
{
    private string $question;
    private string $fixedInstructions;
    private string $responsePreset;
    private string $policyFingerprint;
    private string $corpusFingerprint;

    public function __construct(string $question, string $fixedInstructions, string $responsePreset, string $policyFingerprint, string $corpusFingerprint)
    {
        $this->question = $question;
        $this->fixedInstructions = $fixedInstructions;
        $this->responsePreset = $responsePreset;
        $this->policyFingerprint = $policyFingerprint;
        $this->corpusFingerprint = $corpusFingerprint;
    }

    public function question(): string
    {
        return $this->question;
    }

    public function fixedInstructions(): string
    {
        return $this->fixedInstructions;
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
}
