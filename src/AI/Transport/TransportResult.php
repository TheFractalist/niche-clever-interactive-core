<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

final class TransportResult
{
    private string $answer;
    private bool $completedFileSearch;
    /** @var list<string> */
    private array $annotationTokens;
    private string $policyFingerprint;
    private string $corpusFingerprint;

    /**
     * @param list<string> $annotationTokens
     */
    public function __construct(string $answer, bool $completedFileSearch, array $annotationTokens, string $policyFingerprint, string $corpusFingerprint)
    {
        $this->answer = $answer;
        $this->completedFileSearch = $completedFileSearch;
        $this->annotationTokens = $annotationTokens;
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

    /** @return list<string> */
    public function annotationTokens(): array
    {
        return $this->annotationTokens;
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
