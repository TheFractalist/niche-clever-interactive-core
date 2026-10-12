<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI;

final class ConnectionTestResult
{
    public const PASSED = 'passed';
    public const FAILED = 'failed';

    private string $status;
    private string $fingerprint;

    public function __construct(string $status, string $fingerprint)
    {
        if (!in_array($status, [self::PASSED, self::FAILED], true)) {
            throw new \InvalidArgumentException('Unknown connection-test result.');
        }

        $this->status = $status;
        $this->fingerprint = $fingerprint;
    }

    public function passed(): bool
    {
        return $this->status === self::PASSED;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }
}
