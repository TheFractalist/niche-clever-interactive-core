<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Budget;

use NicheCleverInteractive\Core\Configuration\CoreSettings;

final class BudgetGate
{
    private BucketStore $store;
    private ClientScopeResolver $scopes;

    public function __construct(BucketStore $store, ?ClientScopeResolver $scopes = null)
    {
        $this->store = $store;
        $this->scopes = $scopes ?? new ClientScopeResolver();
    }

    /** @param array<string, mixed> $server */
    public function reserve(CoreSettings $settings, array $server): ?Reservation
    {
        $scopeHash = $this->scopes->hash($server);
        if ($scopeHash === null) {
            return null;
        }
        $period = gmdate('Y-m-d 00:00:00');
        if (!$this->store->reserve('visitor', $scopeHash, $period, $settings->visitorRequestsPerDay())) {
            return null;
        }
        $providerHash = hash('sha256', 'provider:core');
        if (!$this->store->reserve('provider', $providerHash, $period, $settings->providerRequestsPerDay())) {
            $this->store->release('visitor', $scopeHash, $period);
            return null;
        }
        return new Reservation($this->store, $scopeHash, $period);
    }

    public function reserveProviderTest(CoreSettings $settings): ?ProviderReservation
    {
        $period = gmdate('Y-m-d 00:00:00');
        $providerHash = hash('sha256', 'provider:core');
        if (!$this->store->reserve('provider', $providerHash, $period, $settings->providerRequestsPerDay())) {
            return null;
        }

        return new ProviderReservation($this->store, $period);
    }
}
