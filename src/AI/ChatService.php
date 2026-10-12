<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI;

use NicheCleverInteractive\Core\AI\Transport\ResponseTransport;
use NicheCleverInteractive\Core\AI\Transport\TransportRequest;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Profile\DefaultProfileResolverV1;
use NicheCleverInteractive\Core\Security\ChatRequestValidator;
use NicheCleverInteractive\Core\Security\PromptPolicy;

final class ChatService
{
    private ResponseTransport $transport;
    private SecretResolver $secrets;
    private PromptPolicy $policy;
    private ChatRequestValidator $requests;
    private EvidenceValidator $evidence;

    public function __construct(
        ResponseTransport $transport,
        ?SecretResolver $secrets = null,
        ?PromptPolicy $policy = null,
        ?ChatRequestValidator $requests = null,
        ?EvidenceValidator $evidence = null
    ) {
        $this->transport = $transport;
        $this->secrets = $secrets ?? new SecretResolver();
        $this->policy = $policy ?? new PromptPolicy();
        $this->requests = $requests ?? new ChatRequestValidator();
        $this->evidence = $evidence ?? new EvidenceValidator();
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

        try {
            $result = $this->transport->respond(new TransportRequest(
                $validated['question'],
                $this->policy->fixedInstructions(),
                $validated['response_preset'],
                $profile->policyFingerprint(),
                $profile->corpusFingerprint()
            ));
        } catch (\Throwable $exception) {
            return ChatOutcome::failure('provider_failure');
        }

        $answer = trim($result->answer());
        if ($answer === '' || strlen($answer) > 5000) {
            return ChatOutcome::failure('provider_failure');
        }

        $evidence = $this->evidence->validate($result, $profile, $settings);
        if ($evidence === null) {
            return ChatOutcome::failure('evidence_failure');
        }

        return ChatOutcome::success($answer, $evidence);
    }
}
