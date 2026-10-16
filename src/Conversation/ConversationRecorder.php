<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Conversation;

use NicheCleverInteractive\Core\Api\V1\Support\CompletedTurnEligibilityV1;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;

/**
 * Records a successfully validated provider answer when the owner has opted in.
 * Storage is best-effort: a failure here must never change the visitor response.
 */
final class ConversationRecorder
{
    private ConversationStore $store;
    private ?ProfileDirectory $policy;
    /** @var callable */
    private $clock;

    public function __construct(ConversationStore $store, ?callable $clock = null, ?ProfileDirectory $policy = null)
    {
        $this->policy = $policy;
        $this->store = $store;
        $this->clock = $clock ?? 'time';
    }

    /** @param mixed $conversationId */
    public function recordCachedAnswer(
        CoreSettings $settings,
        $conversationId,
        string $profileId,
        string $question,
        string $answer,
        string $policyFingerprint,
        ?string $corpusVersion = null
    ): bool {
        return $this->recordAnswer('cache', $settings, $conversationId, $profileId, $question, $answer, $policyFingerprint, $corpusVersion);
    }

    /** @param mixed $conversationId */
    public function recordProviderAnswer(
        CoreSettings $settings,
        $conversationId,
        string $profileId,
        string $question,
        string $answer,
        string $policyFingerprint,
        ?string $corpusVersion = null
    ): bool {
        return $this->recordAnswer('provider', $settings, $conversationId, $profileId, $question, $answer, $policyFingerprint, $corpusVersion);
    }

    /** @param mixed $conversationId */
    private function recordAnswer(
        string $outcome,
        CoreSettings $settings,
        $conversationId,
        string $profileId,
        string $question,
        string $answer,
        string $policyFingerprint,
        ?string $corpusVersion
    ): bool {
        // An extension's explicit per-profile decision wins over Core's setting; otherwise Core's setting decides.
        $override = $this->policy !== null ? $this->policy->recordingOverride($profileId) : null;
        if (!($override ?? $settings->conversationLogEnabled()) || !CompletedTurnEligibilityV1::shouldRecord($outcome)) {
            return false;
        }

        try {
            $this->store->record(new ConversationTurn(
                ConversationId::isValid($conversationId) ? $conversationId : ConversationId::generate(),
                $profileId,
                $question,
                $answer,
                defined('NCLI_CORE_VERSION') ? NCLI_CORE_VERSION : 'development',
                $corpusVersion ?? $settings->corpusVersion(),
                $policyFingerprint,
                gmdate('Y-m-d H:i:s', (int) call_user_func($this->clock))
            ));
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }
}
