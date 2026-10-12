<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Configuration;

final class TransportMode
{
    public const MOCK = 'mock';
    public const LIVE = 'live';
    public const UNAVAILABLE = 'unavailable';

    public static function current(): string
    {
        if (defined('NCLI_CORE_MOCK_TRANSPORT') && NCLI_CORE_MOCK_TRANSPORT === true) {
            return self::MOCK;
        }

        if (defined('NCLI_CORE_LIVE_TRANSPORT') && NCLI_CORE_LIVE_TRANSPORT === true) {
            return self::LIVE;
        }

        return self::UNAVAILABLE;
    }
}
