<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI;

use NicheCleverInteractive\Core\Budget\BudgetGate;
use NicheCleverInteractive\Core\AI\Transport\ResponseTransport;
use NicheCleverInteractive\Core\AI\Transport\TransportRequest;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Conversation\ConversationRecorder;
use NicheCleverInteractive\Core\Profile\DefaultProfileResolverV1;
use NicheCleverInteractive\Core\Security\ChatRequestValidator;
use NicheCleverInteractive\Core\Security\PromptPolicy;

final class ChatService
{
    private ResponseTransport $transport;
    private SecretResolver $secrets;
    private PromptPolicy $policy;
    private ChatRequestValidator $requests;
    private ?BudgetGate $budgets;
    private ?ConversationRecorder $conversations;

    public function __construct(
        ResponseTransport $transport,
        ?SecretResolver $secrets = null,
        ?PromptPolicy $policy = null,
        ?ChatRequestValidator $requests = null,
        ?BudgetGate $budgets = null,
        ?ConversationRecorder $conversations = null
    ) {
        $this->transport = $transport;
        $this->secrets = $secrets ?? new SecretResolver();
        $this->policy = $policy ?? new PromptPolicy();
        $this->requests = $requests ?? new ChatRequestValidator();
        $this->budgets = $budgets;
        $this->conversations = $conversations;
    }

    /** @param mixed $request */
    public function handle($request, CoreSettings $settings): ChatOutcome
    {
        if (is_array($request) && is_string($request['question'] ?? null) && $this->policy->isRefused($request['question'])) {
            return ChatOutcome::failure('policy_refusal');
        }

        $validated = $this->requests->validate($request, $settings);
        if ($validated === null) {
            return ChatOutcome::failure('invalid_request');
        }

        $profile = (new DefaultProfileResolverV1($settings, $this->policy))->resolve(new ProfileIdV1($validated['profile_id']));
        if ($profile === null || !Readiness::evaluate($settings, $this->secrets)->isReady()) {
            return ChatOutcome::failure('profile_unavailable');
        }

        $reservation = null;
        if ($this->budgets !== null) {
            try {
                $reservation = $this->budgets->reserve($settings, is_array($_SERVER ?? null) ? $_SERVER : []);
            } catch (\Throwable $exception) {
                return ChatOutcome::failure('provider_failure');
            }
            if ($reservation === null) {
                return ChatOutcome::failure('allowance_exceeded');
            }
        }

        try {
            $result = $this->transport->respond(new TransportRequest(
                $validated['question'],
                $this->policy->effectiveInstructions($settings->instructions(), $validated['response_preset']),
                $validated['response_preset'],
                $profile->policyFingerprint(),
                $profile->corpusFingerprint(),
                $settings->vectorStoreReference()
            ));
        } catch (\Throwable $exception) {
            // A request may already have reached a provider; retain the reservation
            // and do not retry automatically when that outcome cannot be proved.
            if (!$this->commitReservation($reservation)) {
                return ChatOutcome::failure('provider_failure');
            }
            return ChatOutcome::failure('provider_failure');
        }

        $answer = trim($result->answer());
        if ($answer === '' || strlen($answer) > 5000) {
            if (!$this->commitReservation($reservation)) {
                return ChatOutcome::failure('provider_failure');
            }
            return ChatOutcome::failure('provider_failure');
        }

        if (!$result->completedFileSearch()
            || !hash_equals($profile->policyFingerprint(), $result->policyFingerprint())
            || !hash_equals($profile->corpusFingerprint(), $result->corpusFingerprint())) {
            if (!$this->commitReservation($reservation)) {
                return ChatOutcome::failure('provider_failure');
            }
            return ChatOutcome::failure('evidence_failure');
        }

        if (!$this->commitReservation($reservation)) {
            return ChatOutcome::failure('provider_failure');
        }

        // Optional, opt-in and best-effort: only a fully validated answer is logged,
        // and a storage failure never alters the public response.
        if ($this->conversations !== null) {
            $this->conversations->recordProviderAnswer(
                $settings,
                $validated['conversation_id'],
                $profile->id()->value(),
                $validated['question'],
                $answer,
                $profile->policyFingerprint()
            );
        }

        return ChatOutcome::success($answer);
    }

    /** @param mixed $reservation */
    private function commitReservation($reservation): bool
    {
        if ($reservation === null) {
            return true;
        }
        try {
            $reservation->commit();
            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
