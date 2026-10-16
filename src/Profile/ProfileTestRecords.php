<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Profile;

/**
 * Core-owned connection-test records for extension profiles. An extension can neither
 * write nor forge a record: only Core's own test handler stores one.
 */
interface ProfileTestRecords
{
    /** @return array{status: string, tested_at: string, fingerprint: string} */
    public function get(string $profileId): array;

    public function put(string $profileId, string $status, string $fingerprint, string $testedAt): void;
}
