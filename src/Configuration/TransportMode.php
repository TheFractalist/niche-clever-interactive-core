<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Configuration;

final class TransportMode
{
    public const MOCK = 'mock';
    public const LIVE = 'live';
    public const UNAVAILABLE = 'unavailable';

    /**
     * Live mode follows from the API key being defined, so a normal site needs no extra
     * setting. NCLI_CORE_MOCK_TRANSPORT (true) selects the deterministic test transport, and
     * NCLI_CORE_LIVE_TRANSPORT is an optional explicit override: true forces live mode and
     * false switches the provider off even when a key is defined.
     */
    public static function current(?SecretResolver $secrets = null): string
    {
        if (defined('NCLI_CORE_MOCK_TRANSPORT') && NCLI_CORE_MOCK_TRANSPORT === true) {
            return self::MOCK;
        }

        if (defined('NCLI_CORE_LIVE_TRANSPORT')) {
            return NCLI_CORE_LIVE_TRANSPORT === true ? self::LIVE : self::UNAVAILABLE;
        }

        return ($secrets ?? new SecretResolver())->isAvailable() ? self::LIVE : self::UNAVAILABLE;
    }
}
