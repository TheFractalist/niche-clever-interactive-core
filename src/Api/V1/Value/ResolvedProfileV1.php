<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

final class ResolvedProfileV1
{
    private ProfileIdV1 $id;
    private bool $enabled;
    private string $policyFingerprint;
    private string $corpusFingerprint;
    private string $responsePreset;

    public function __construct(
        ProfileIdV1 $id,
        bool $enabled,
        string $policyFingerprint,
        string $corpusFingerprint,
        string $responsePreset
    ) {
        $this->id = $id;
        $this->enabled = $enabled;
        $this->policyFingerprint = $policyFingerprint;
        $this->corpusFingerprint = $corpusFingerprint;
        $this->responsePreset = $responsePreset;
    }

    public function id(): ProfileIdV1
    {
        return $this->id;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function policyFingerprint(): string
    {
        return $this->policyFingerprint;
    }

    public function corpusFingerprint(): string
    {
        return $this->corpusFingerprint;
    }

    public function responsePreset(): string
    {
        return $this->responsePreset;
    }
}
