<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Budget;

/** A bucket store that can delete counters for daily periods that no longer apply. */
interface PrunableBucketStore
{
    /** @return int Number of counter rows removed, at most $limit. */
    public function pruneBefore(string $periodStart, int $limit): int;
}
