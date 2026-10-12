<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Budget;

interface BucketStore
{
    public function reserve(string $kind, string $scopeHash, string $periodStart, int $limit): bool;

    public function commit(string $kind, string $scopeHash, string $periodStart): void;

    public function release(string $kind, string $scopeHash, string $periodStart): void;
}
