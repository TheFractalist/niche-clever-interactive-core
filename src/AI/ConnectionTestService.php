<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI;

use NicheCleverInteractive\Core\AI\Transport\ResponseTransport;
use NicheCleverInteractive\Core\AI\Transport\TransportRequest;
use NicheCleverInteractive\Core\Budget\BudgetGate;
use NicheCleverInteractive\Core\Configuration\ConnectionFingerprint;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Security\PolicyFingerprint;
use NicheCleverInteractive\Core\Security\PromptPolicy;

final class ConnectionTestService
{
    private const VERIFICATION_REQUEST = 'NCI administrator connection verification. Use File Search and return a short acknowledgement.';

    private ResponseTransport $transport;
    private SecretResolver $secrets;
    private BudgetGate $budget;
    private PromptPolicy $policy;
    private string $mode;

    public function __construct(ResponseTransport $transport, SecretResolver $secrets, BudgetGate $budget, ?PromptPolicy $policy = null, ?string $mode = null)
    {
        $this->transport = $transport;
        $this->secrets = $secrets;
        $this->budget = $budget;
        $this->policy = $policy ?? new PromptPolicy();
        $this->mode = $mode ?? TransportMode::current();
    }

    public function test(CoreSettings $settings): ConnectionTestResult
    {
        $fingerprint = ConnectionFingerprint::forSettings($settings, $this->secrets, $this->policy, $this->mode);
        if ($fingerprint === null || $settings->vectorStoreReference() === '' || $settings->corpusVersion() === '') {
            return new ConnectionTestResult(ConnectionTestResult::FAILED, $this->fallbackFingerprint());
        }

        $reservation = $this->budget->reserveProviderTest($settings);
        if ($reservation === null) {
            return new ConnectionTestResult(ConnectionTestResult::FAILED, $fingerprint);
        }

        $dispatched = false;
        try {
            $request = new TransportRequest(
                self::VERIFICATION_REQUEST,
                $this->policy->fixedInstructions(),
                $settings->responsePreset(),
                PolicyFingerprint::create($this->policy, $settings->instructions(), $settings->responsePreset()),
                hash('sha256', $settings->corpusVersion()),
                $settings->vectorStoreReference()
            );
            $dispatched = true;
            $result = $this->transport->respond($request);
            $reservation->commit();

            return new ConnectionTestResult($result->completedFileSearch() ? ConnectionTestResult::PASSED : ConnectionTestResult::FAILED, $fingerprint);
        } catch (\Throwable $exception) {
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
