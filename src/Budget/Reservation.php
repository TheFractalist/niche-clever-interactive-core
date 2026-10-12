<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Budget;

final class Reservation
{
    private BucketStore $store;
    private string $visitorHash;
    private string $providerHash;
    private string $periodStart;
    private bool $settled = false;

    public function __construct(BucketStore $store, string $visitorHash, string $periodStart)
    {
        $this->store = $store;
        $this->visitorHash = $visitorHash;
        $this->providerHash = hash('sha256', 'provider:core');
        $this->periodStart = $periodStart;
    }

    public function commit(): void
    {
        if ($this->settled) {
            return;
        }
        $this->store->commit('visitor', $this->visitorHash, $this->periodStart);
        $this->store->commit('provider', $this->providerHash, $this->periodStart);
        $this->settled = true;
    }

    public function release(): void
    {
        if ($this->settled) {
            return;
        }
        $this->store->release('visitor', $this->visitorHash, $this->periodStart);
        $this->store->release('provider', $this->providerHash, $this->periodStart);
        $this->settled = true;
    }
}
