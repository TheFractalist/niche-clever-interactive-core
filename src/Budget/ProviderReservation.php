<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Budget;

final class ProviderReservation
{
    private BucketStore $store;
    private string $providerHash;
    private string $periodStart;
    private bool $settled = false;

    public function __construct(BucketStore $store, string $periodStart)
    {
        $this->store = $store;
        $this->providerHash = hash('sha256', 'provider:core');
        $this->periodStart = $periodStart;
    }

    public function commit(): void
    {
        if ($this->settled) {
            return;
        }
        $this->store->commit('provider', $this->providerHash, $this->periodStart);
        $this->settled = true;
    }

    public function release(): void
    {
        if ($this->settled) {
            return;
        }
        $this->store->release('provider', $this->providerHash, $this->periodStart);
        $this->settled = true;
    }
}
