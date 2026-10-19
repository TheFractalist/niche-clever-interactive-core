<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

/**
 * A transport that can report answer text while the provider is still writing it.
 *
 * The returned result is the same, complete and validated-by-Core result `respond()` would give;
 * the callback only lets a browser show text early. Text is passed on only after the provider has
 * reported that retrieval completed.
 */
interface StreamingResponseTransport extends ResponseTransport
{
    /** @param callable(string): void $onText */
    public function respondStreaming(TransportRequest $request, callable $onText): TransportResult;
}
