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

    public function __construct(string $question, string $instructions, string $responsePreset, string $policyFingerprint, string $corpusFingerprint, string $vectorStoreReference = '', ?RequestOptions $options = null)
    {
        $this->options = $options ?? new RequestOptions();
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

    public function options(): RequestOptions
    {
        return $this->options;
    }
}
