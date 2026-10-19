<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

/**
 * An HTTP client that can hand a successful response body to a callback as it arrives.
 */
interface StreamingResponsesHttpClient extends ResponsesHttpClient
{
    /**
     * Posts a request and passes each chunk of a 2xx response body to $onChunk as it arrives.
     * A non-2xx response is never passed to the callback; its status and body are returned instead.
     *
     * @param array<string, string> $headers
     * @param callable(string): void $onChunk
     */
    public function postStream(string $url, array $headers, string $body, int $timeout, callable $onChunk): ResponsesHttpResponse;
}
