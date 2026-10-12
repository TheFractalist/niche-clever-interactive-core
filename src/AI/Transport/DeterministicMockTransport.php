<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

use RuntimeException;

final class DeterministicMockTransport implements ResponseTransport
{
    public function respond(TransportRequest $request): TransportResult
    {
        if ($request->question() === 'Simulate provider timeout') {
            throw new RuntimeException('Mock timeout.');
        }

        if ($request->question() === 'Return incomplete retrieval') {
            return new TransportResult('This answer is deliberately incomplete.', false, $request->policyFingerprint(), $request->corpusFingerprint());
        }

        return new TransportResult(
            'Niche Clever Interactive is a Core-first development programme.',
            true,
            $request->policyFingerprint(),
            $request->corpusFingerprint()
        );
    }
}
