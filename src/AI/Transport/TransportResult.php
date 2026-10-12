<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

final class TransportResult
{
    private string $answer;
    private bool $completedFileSearch;
    private string $policyFingerprint;
    private string $corpusFingerprint;

    public function __construct(string $answer, bool $completedFileSearch, string $policyFingerprint, string $corpusFingerprint)
    {
        $this->answer = $answer;
        $this->completedFileSearch = $completedFileSearch;
        $this->policyFingerprint = $policyFingerprint;
        $this->corpusFingerprint = $corpusFingerprint;
    }

    public function answer(): string
    {
        return $this->answer;
    }

    public function completedFileSearch(): bool
    {
        return $this->completedFileSearch;
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
