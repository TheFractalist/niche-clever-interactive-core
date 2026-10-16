<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI;

use NicheCleverInteractive\Core\AI\Transport\ProviderFailure;
use NicheCleverInteractive\Core\AI\Transport\ResponseTransport;
use NicheCleverInteractive\Core\AI\Transport\TransportRequest;
use NicheCleverInteractive\Core\Budget\BudgetGate;
use NicheCleverInteractive\Core\Configuration\ConnectionFingerprint;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Diagnostics\FailureLog;
use NicheCleverInteractive\Core\Profile\ProfileRuntime;
use NicheCleverInteractive\Core\Security\PromptPolicy;

final class ConnectionTestService
{
    private const VERIFICATION_REQUEST = 'NCI administrator connection verification. Use File Search and return a short acknowledgement.';

    private ResponseTransport $transport;
    private SecretResolver $secrets;
    private BudgetGate $budget;
    private PromptPolicy $policy;
    private string $mode;
    private ?FailureLog $failures;

    public function __construct(ResponseTransport $transport, SecretResolver $secrets, BudgetGate $budget, ?PromptPolicy $policy = null, ?string $mode = null, ?FailureLog $failures = null)
    {
        $this->transport = $transport;
        $this->secrets = $secrets;
        $this->budget = $budget;
        $this->policy = $policy ?? new PromptPolicy();
        $this->mode = $mode ?? TransportMode::current();
        $this->failures = $failures;
    }

    public function test(CoreSettings $settings): ConnectionTestResult
    {
        return $this->testProfile(ProfileRuntime::fromSettings($settings), $settings);
    }

    /** Tests one profile; the global provider allowance still comes from Core's settings. */
    public function testProfile(ProfileRuntime $profile, CoreSettings $settings): ConnectionTestResult
    {
        $fingerprint = ConnectionFingerprint::forProfile($profile, $this->secrets, $this->policy, $this->mode);
        if ($fingerprint === null || $profile->vectorStoreReference() === '' || $profile->corpusVersion() === '') {
            return new ConnectionTestResult(ConnectionTestResult::FAILED, $this->fallbackFingerprint());
        }

        $reservation = $this->budget->reserveProviderTest($settings);
        if ($reservation === null) {
            return new ConnectionTestResult(ConnectionTestResult::FAILED, $fingerprint);
        }

        $dispatched = false;
        $started = microtime(true);
        try {
            $request = new TransportRequest(
                self::VERIFICATION_REQUEST,
                $this->policy->effectiveInstructions($profile->instructions(), $profile->responsePreset()),
                $profile->responsePreset(),
                $profile->policyFingerprint($this->policy),
                $profile->corpusFingerprint(),
                $profile->vectorStoreReference(),
                $profile->options()
            );
            $dispatched = true;
            $result = $this->transport->respond($request);
            $reservation->commit();

            return new ConnectionTestResult($result->completedFileSearch() ? ConnectionTestResult::PASSED : ConnectionTestResult::FAILED, $fingerprint);
        } catch (\Throwable $exception) {
            if ($this->failures !== null) {
                try {
                    $this->failures->record('connection_test', ProviderFailure::reasonFor($exception), (int) round(microtime(true) - $started));
                } catch (\Throwable $ignored) {
                    // Diagnostics must never change the test outcome.
                }
            }
            if ($dispatched) {
                $reservation->commit();
            } else {
                $reservation->release();
            }

            return new ConnectionTestResult(ConnectionTestResult::FAILED, $fingerprint);
        }
    }

    private function fallbackFingerprint(): string
    {
        return hash('sha256', 'ncli-connection-test-unavailable');
    }
}
