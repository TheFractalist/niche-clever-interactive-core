<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

use RuntimeException;

final class DeterministicMockTransport implements StreamingResponseTransport
{
    private const ANSWER = 'Niche Clever Interactive is a Core-first development programme.';

    public function respond(TransportRequest $request): TransportResult
    {
        if ($request->question() === 'Simulate provider timeout') {
            throw new RuntimeException('Mock timeout.');
        }

        if ($request->question() === 'Return incomplete retrieval') {
            return new TransportResult('This answer is deliberately incomplete.', false, $request->policyFingerprint(), $request->corpusFingerprint());
        }

        return new TransportResult(
            self::ANSWER,
            true,
            $request->policyFingerprint(),
            $request->corpusFingerprint()
        );
    }

    /**
     * The deterministic fixture is also a streaming transport so a disposable WordPress site can
     * exercise Core's SSE route without a provider request. It reports only a result that has
     * already passed this mock's completed-retrieval boundary, then returns the ordinary result
     * unchanged. The optional delay is intentionally read only by this mock and is defined by the
     * disposable fixture; production and live transports do not inspect it.
     *
     * @param callable(string): void $onText
     */
    public function respondStreaming(TransportRequest $request, callable $onText): TransportResult
    {
        $result = $this->respond($request);
        if (!$result->completedFileSearch()) {
            return $result;
        }

        $delay = $this->fixtureDelayMicroseconds();
        foreach (str_split($result->answer(), 20) as $chunk) {
            if ($delay > 0) {
                usleep($delay);
            }
            $onText($chunk);
        }

        return $result;
    }

    /** Bounded mock-only pause used to make the pending state visible in fixture browser evidence. */
    private function fixtureDelayMicroseconds(): int
    {
        if (!defined('NCLI_CORE_FIXTURE_STREAM_DELAY_MS') || !is_int(NCLI_CORE_FIXTURE_STREAM_DELAY_MS)) {
            return 0;
        }

        $milliseconds = NCLI_CORE_FIXTURE_STREAM_DELAY_MS;
        if ($milliseconds < 1 || $milliseconds > 1000) {
            return 0;
        }

        return $milliseconds * 1000;
    }
}
