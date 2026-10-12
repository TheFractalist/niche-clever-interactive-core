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
            return new TransportResult('This answer is deliberately incomplete.', false, ['fixture_source'], $request->policyFingerprint(), $request->corpusFingerprint());
        }

        if ($request->question() === 'Return unapproved evidence') {
            return new TransportResult('This answer has unapproved evidence.', true, ['not_approved'], $request->policyFingerprint(), $request->corpusFingerprint());
        }

        return new TransportResult(
            'Niche Clever Interactive is a Core-first development programme.',
            true,
            ['fixture_source'],
            $request->policyFingerprint(),
            $request->corpusFingerprint()
        );
    }
}
