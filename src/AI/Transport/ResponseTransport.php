<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

interface ResponseTransport
{
    public function respond(TransportRequest $request): TransportResult;
}
