<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Diagnostics;

/** Keeps only the most recent provider failure's closed reason, never its content. */
interface FailureLog
{
    /** @param string $source 'public' or 'connection_test' */
    public function record(string $source, string $reason, int $elapsedSeconds): void;

    /** @return array{source: string, reason: string, at: string, elapsed: int}|null */
    public function last(): ?array;
}
