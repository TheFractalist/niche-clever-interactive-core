<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI;

use NicheCleverInteractive\Core\Api\V1\Value\CacheKeyV1;
use NicheCleverInteractive\Core\Api\V1\Value\CompletedTurnV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\RequestContextV1;
use NicheCleverInteractive\Core\Budget\BudgetGate;
use NicheCleverInteractive\Core\Budget\ClientScopeResolver;
use NicheCleverInteractive\Core\AI\Transport\RequestOptions;
use NicheCleverInteractive\Core\AI\Transport\ResponseTransport;
use NicheCleverInteractive\Core\AI\Transport\TransportRequest;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\AI\Transport\ProviderFailure;
use NicheCleverInteractive\Core\Conversation\ConversationRecorder;
use NicheCleverInteractive\Core\Diagnostics\FailureLog;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;
use NicheCleverInteractive\Core\Profile\ProfileRuntime;
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
    private ?FailureLog $failures;
    private ProfileDirectory $profiles;

    public function __construct(
        ResponseTransport $transport,
        ?SecretResolver $secrets = null,
        ?PromptPolicy $policy = null,
        ?ChatRequestValidator $requests = null,
        ?BudgetGate $budgets = null,
        ?ConversationRecorder $conversations = null,
        ?FailureLog $failures = null,
        ?ProfileDirectory $profiles = null
    ) {
        $this->transport = $transport;
        $this->secrets = $secrets ?? new SecretResolver();
        $this->policy = $policy ?? new PromptPolicy();
        $this->requests = $requests ?? new ChatRequestValidator();
        $this->budgets = $budgets;
        $this->conversations = $conversations;
        $this->failures = $failures;
        $this->profiles = $profiles ?? new ProfileDirectory();
    }

    /**
     * @param mixed $request
     * @param array<string, mixed> $server Request environment; used only to give an installed extension in-memory request details.
     */
    public function handle($request, CoreSettings $settings, array $server = []): ChatOutcome
    {
        if (is_array($request) && is_string($request['question'] ?? null) && $this->policy->isRefused($request['question'])) {
            return ChatOutcome::failure('policy_refusal');
        }

        $validated = $this->requests->validate($request, $settings, $this->profiles->hasResolver());
        if ($validated === null) {
            return ChatOutcome::failure('invalid_request');
        }

        // Only Core's own directory turns an id into a profile; an unknown, disabled or
        // misconfigured id is unavailable and never falls back to the default corpus.
        $profile = $this->profiles->resolve($validated['profile_id'], $settings);
        if ($profile === null) {
            return ChatOutcome::failure('profile_unavailable');
        }
        // Access is decided before readiness so a viewer who may not use the profile learns nothing about its setup.
        $access = $this->profiles->access($profile);
        if (!$access->isAllowed()) {
            return ChatOutcome::accessRequired($access->message());
        }
        if (!Readiness::evaluateProfile($profile, $this->secrets, $this->profiles->testRecord($profile, $settings))->isReady()) {
            return ChatOutcome::failure('profile_unavailable');
        }
        if ($validated['response_preset'] !== null && $validated['response_preset'] !== $profile->responsePreset()) {
            return ChatOutcome::failure('invalid_request');
        }
        $facts = $this->profiles->chronology($profile);
        // Earlier turns come only from the server-side seam, never from the browser, and only
        // for a valid conversation identifier. A request that carries them is never cache-eligible.
        $earlier = $this->profiles->earlierTurns($profile, $validated['conversation_id']);
        $withContext = $earlier !== [];
        $policyFingerprint = $profile->policyFingerprint($this->policy, $facts, $withContext);
        $corpusFingerprint = $profile->corpusFingerprint();

        // An approved answer may be reused only when the request carries no earlier-turn context
        // (Core sends none yet) and the profile is not access-controlled. A hit uses no allowance
        // and no provider call; it is validated by Core again and recorded like any answer.
        $cacheKey = null;
        if (!$access->isRestricted() && !$withContext) {
            $cacheKey = new CacheKeyV1(
                self::questionDigest($validated['question']),
                new ProfileIdV1($profile->id()),
                $policyFingerprint,
                $profile->answerSourceFingerprint(),
                hash('sha256', 'access:open'),
                $profile->responsePreset()
            );
            $cached = $this->profiles->cachedAnswer($cacheKey);
            if ($cached !== null) {
                if ($this->conversations !== null) {
                    $this->conversations->recordCachedAnswer($settings, $validated['conversation_id'], $profile->id(), $validated['question'], $cached, $policyFingerprint, $profile->corpusVersion());
                }
                $this->completeTurn('cache', $validated['conversation_id'], $profile, $validated['question'], $cached, $policyFingerprint, $corpusFingerprint, $this->requestContext($validated['visitor_id'], $server));

                return ChatOutcome::success($cached, [], true);
            }
        }

        $reservation = null;
        if ($this->budgets !== null) {
            try {
                $reservation = $this->budgets->reserve($settings, is_array($_SERVER ?? null) ? $_SERVER : [], $profile->visitorRequestsPerDay(), $profile->id());
            } catch (\Throwable $exception) {
                return ChatOutcome::failure('provider_failure');
            }
            if ($reservation === null) {
                return ChatOutcome::failure('allowance_exceeded');
            }
        }

        $started = microtime(true);
        try {
            $result = $this->transport->respond(new TransportRequest(
                $validated['question'],
                $this->policy->effectiveInstructions($profile->instructions(), $profile->responsePreset(), $facts, $withContext),
                $profile->responsePreset(),
                $policyFingerprint,
                $corpusFingerprint,
                $profile->vectorStoreReference(),
                $profile->options(),
                $earlier
            ));
        } catch (\Throwable $exception) {
            // A request may already have reached a provider; retain the reservation
            // and do not retry automatically when that outcome cannot be proved.
            $this->noteFailure(ProviderFailure::reasonFor($exception), $started);
            if (!$this->commitReservation($reservation)) {
                return ChatOutcome::failure('provider_failure');
            }
            return ChatOutcome::failure('provider_failure');
        }

        $answer = trim($result->answer());
        if ($answer === '' || strlen($answer) > 20000) {
            $this->noteFailure($answer === '' ? ProviderFailure::EMPTY_ANSWER : ProviderFailure::ANSWER_TOO_LONG, $started);
            if (!$this->commitReservation($reservation)) {
                return ChatOutcome::failure('provider_failure');
            }
            return ChatOutcome::failure('provider_failure');
        }

        if (!$result->completedFileSearch()
            || !hash_equals($policyFingerprint, $result->policyFingerprint())
            || !hash_equals($corpusFingerprint, $result->corpusFingerprint())) {
            $this->noteFailure(ProviderFailure::EVIDENCE_MISMATCH, $started);
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
                $profile->id(),
                $validated['question'],
                $answer,
                $policyFingerprint,
                $profile->corpusVersion()
            );
        }

        if ($cacheKey !== null) {
            $this->profiles->offerAnswer($cacheKey, $answer, $validated['question']);
        }
        $this->completeTurn('provider', $validated['conversation_id'], $profile, $validated['question'], $answer, $policyFingerprint, $corpusFingerprint, $this->requestContext($validated['visitor_id'], $server));

        // Links come only from the owner's approvals via the seam; raw provider citations never leave Core.
        return ChatOutcome::success($answer, $this->profiles->approvedLinks($profile, $result->citations()));
    }

    /**
     * Tells the recorder seam about a validated turn so an extension can offer it back as context
     * for a follow-up. Only turns with a conversation identifier are reported.
     *
     * @param mixed $conversationId
     */
    private function completeTurn(string $source, $conversationId, ProfileRuntime $profile, string $question, string $answer, string $policyFingerprint, string $corpusFingerprint, ?RequestContextV1 $context = null): void
    {
        if (!is_string($conversationId)) {
            return;
        }

        try {
            $this->profiles->recordCompletedTurn(new CompletedTurnV1(
                $conversationId,
                new ProfileIdV1($profile->id()),
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                $question,
                $answer,
                defined('NCLI_CORE_VERSION') ? NCLI_CORE_VERSION : 'development',
                null,
                $profile->corpusVersion(),
                hash('sha256', $answer),
                hash('sha256', $policyFingerprint . '|' . $corpusFingerprint),
                $source,
                $context
            ));
        } catch (\Throwable $exception) {
            // An invalid value must never change the response.
        }
    }

    /**
     * Request details handed to the recorder seam in memory only. Core stores none of them; an
     * extension that keeps them must disclose that in its own privacy notice.
     *
     * @param mixed $visitorId
     * @param array<string, mixed> $server
     */
    private function requestContext($visitorId, array $server): ?RequestContextV1
    {
        try {
            $address = (new ClientScopeResolver())->clientAddress($server);
            $agent = is_string($server['HTTP_USER_AGENT'] ?? null) ? substr(trim($server['HTTP_USER_AGENT']), 0, 512) : null;

            return new RequestContextV1(is_string($visitorId) ? $visitorId : null, $address, $agent === '' ? null : $agent);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /** Case, spacing and surrounding whitespace do not make a repeated question a different one. */
    private static function questionDigest(string $question): string
    {
        $flat = trim((string) preg_replace('/\s+/u', ' ', $question));

        return hash('sha256', function_exists('mb_strtolower') ? mb_strtolower($flat, 'UTF-8') : strtolower($flat));
    }

    private function noteFailure(string $reason, float $started): void
    {
        if ($this->failures === null) {
            return;
        }
        try {
            $this->failures->record('public', $reason, (int) round(microtime(true) - $started));
        } catch (\Throwable $exception) {
            // Diagnostics must never change the visitor's response.
        }
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
