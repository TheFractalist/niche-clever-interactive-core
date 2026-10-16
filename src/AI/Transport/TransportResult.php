<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

final class TransportResult
{
    private string $answer;
    private bool $completedFileSearch;
    private string $policyFingerprint;
    private string $corpusFingerprint;
    /** @var list<\NicheCleverInteractive\Core\Api\V1\Value\EvidenceCitationV1> */
    private array $citations;

    public function __construct(string $answer, bool $completedFileSearch, string $policyFingerprint, string $corpusFingerprint, array $citations = [])
    {
        $this->citations = $citations;
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

    /**
     * Bounded descriptors of the sources the answer cited. They stay inside Core and an installed
     * extension; they never reach a browser.
     *
     * @return list<\NicheCleverInteractive\Core\Api\V1\Value\EvidenceCitationV1>
     */
    public function citations(): array
    {
        return $this->citations;
    }

    public function corpusFingerprint(): string
    {
        return $this->corpusFingerprint;
    }
}
