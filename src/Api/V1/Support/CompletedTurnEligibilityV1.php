<?php

namespace NicheCleverInteractive\Core\Api\V1\Support;

final class CompletedTurnEligibilityV1
{
    /**
     * This expresses Core's future invocation boundary only; it performs no storage.
     */
    public static function shouldRecord(string $outcome): bool
    {
        return in_array($outcome, ['cache', 'provider'], true);
    }
}
