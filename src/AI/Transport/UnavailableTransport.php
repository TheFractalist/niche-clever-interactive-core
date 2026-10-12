<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

use RuntimeException;

final class UnavailableTransport implements ResponseTransport
{
    public function respond(TransportRequest $request): TransportResult
    {
        throw new RuntimeException('No Core transport is configured.');
    }
}
