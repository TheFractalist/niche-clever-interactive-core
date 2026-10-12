<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

interface ResponsesHttpClient
{
    /** @param array<string, string> $headers */
    public function post(string $url, array $headers, string $body, int $timeout): ResponsesHttpResponse;
}
