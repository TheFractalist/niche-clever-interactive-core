<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Profile;

use NicheCleverInteractive\Core\Api\V1\Contracts\AnswerCacheV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ChronologyProviderV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\CompletedTurnRecorderV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ConversationContextV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\EvidenceCitationsV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\PanelColoursProviderV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ProfileResolverV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ViewerAccessV1;
use NicheCleverInteractive\Core\Api\V1\Value\AccessDecisionV1;
use NicheCleverInteractive\Core\Api\V1\Value\ApprovedLinkV1;
use NicheCleverInteractive\Core\Api\V1\Value\EvidenceCitationV1;
use NicheCleverInteractive\Core\Api\V1\Value\CacheKeyV1;
use NicheCleverInteractive\Core\Api\V1\Value\CachedAnswerV1;
use NicheCleverInteractive\Core\Api\V1\Value\ChronologyFactV1;
use NicheCleverInteractive\Core\Api\V1\Value\PanelColoursV1;
use NicheCleverInteractive\Core\Api\V1\Value\CompletedTurnV1;
use NicheCleverInteractive\Core\Api\V1\Value\EarlierTurnV1;
use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\ResolvedProfileV1;
use NicheCleverInteractive\Core\Configuration\CoreSettings;

/**
 * The single place Core turns a profile id into a profile it will answer from. The
 * default profile always comes from Core's own settings and never from an extension.
 * Any other id is resolved only through a registered ProfileResolverV1, and every
 * doubt (no resolver, exception, wrong type, wrong id, disabled, no configuration,
 * invalid configuration) resolves to null: never a fallback to the default corpus.
 */
final class ProfileDirectory
{
    public const SEAM = 'profile_resolution';

    private ?ExtensionRegistryV1 $registry;
    private ProfileTestRecords $records;

    public function __construct(?ExtensionRegistryV1 $registry = null, ?ProfileTestRecords $records = null)
    {
        $this->registry = $registry;
        $this->records = $records ?? new OptionProfileTestRecords();
    }

    /** Whether a named profile could ever resolve; without one Core accepts only the default id. */
    public function hasResolver(): bool
    {
        return $this->registry !== null && $this->registry->implementation(self::SEAM) instanceof ProfileResolverV1;
    }

    public function resolve(string $profileId, CoreSettings $settings): ?ProfileRuntime
    {
        if ($profileId === CoreSettings::DEFAULT_PROFILE_ID) {
            return $settings->isEnabled() ? ProfileRuntime::fromSettings($settings) : null;
        }

        $resolver = $this->registry !== null ? $this->registry->implementation(self::SEAM) : null;
        if (!$resolver instanceof ProfileResolverV1) {
            return null;
        }

        try {
            $resolved = $resolver->resolve(new ProfileIdV1($profileId));
            if (!$resolved instanceof ResolvedProfileV1 || !$resolved->isEnabled() || $resolved->id()->value() !== $profileId) {
                return null;
            }

            return ProfileRuntime::fromResolved($resolved, $settings);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Whether the current viewer may use a resolved profile. The default profile is always open
     * and never reaches an extension. With no access seam registered a named profile is open,
     * as it was before the seam existed; once a seam is registered, any failure, wrong type or
     * invalid decision is a denial, never a silent grant.
     */
    public function access(ProfileRuntime $profile): AccessDecisionV1
    {
        if ($profile->isDefault()) {
            return AccessDecisionV1::open();
        }

        $seam = $this->registry !== null ? $this->registry->implementation('viewer_access') : null;
        if (!$seam instanceof ViewerAccessV1) {
            return AccessDecisionV1::open();
        }

        try {
            $decision = $seam->decide(new ProfileIdV1($profile->id()));

            return $decision instanceof AccessDecisionV1 ? $decision : AccessDecisionV1::denied();
        } catch (\Throwable $exception) {
            return AccessDecisionV1::denied();
        }
    }

    public const MAX_CHRONOLOGY_FACTS = 20;
    public const MAX_EARLIER_TURNS = 10;
    public const MAX_LINKS = 5;

    /**
     * Owner colours for a panel's question and answer blocks, for the default or a named profile.
     * Any failure or wrong type means no colours, so the style's own are used.
     */
    public function colours(ProfileRuntime $profile): ?PanelColoursV1
    {
        $provider = $this->registry !== null ? $this->registry->implementation('panel_colours') : null;
        if (!$provider instanceof PanelColoursProviderV1) {
            return null;
        }

        try {
            $colours = $provider->coloursFor(new ProfileIdV1($profile->id()));

            return $colours instanceof PanelColoursV1 && !$colours->isEmpty() ? $colours : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * Owner-approved links to show beneath a validated provider answer. Only distinct, valid
     * public https links are kept (at most 5); an exception, a non-list or any wrong entry
     * yields none, so a faulty extension can only remove links, never add anything unsafe.
     *
     * @param list<EvidenceCitationV1> $citations
     * @return list<ApprovedLinkV1>
     */
    public function approvedLinks(ProfileRuntime $profile, array $citations): array
    {
        $provider = $this->registry !== null ? $this->registry->implementation('evidence_citations') : null;
        if (!$provider instanceof EvidenceCitationsV1 || $citations === []) {
            return [];
        }

        try {
            $links = $provider->approvedLinks(new ProfileIdV1($profile->id()), array_values($citations));
            if (!is_array($links)) {
                return [];
            }
            $kept = [];
            foreach ($links as $link) {
                if (!$link instanceof ApprovedLinkV1 || !ApprovedLinkV1::isPublicHttpsUrl($link->url())) {
                    return [];
                }
                $kept[$link->url()] = $kept[$link->url()] ?? $link;
            }

            return array_slice(array_values($kept), 0, self::MAX_LINKS);
        } catch (\Throwable $exception) {
            return [];
        }
    }

    /** Combined bytes of earlier questions and answers Core will send as context. */
    public const MAX_CONTEXT_BYTES = 16000;

    /** Whether an extension supplies earlier turns, which changes what visitors' questions are sent with. */
    public function hasContextProvider(): bool
    {
        return $this->registry !== null && $this->registry->implementation('conversation_context') instanceof ConversationContextV1;
    }

    /**
     * Earlier turns for a follow-up, bounded by Core: only with a conversation identifier, at most
     * 10 turns (the most recent), at most 16,000 bytes (oldest dropped first). An exception, a
     * non-list or any wrong entry yields no context rather than a partly trusted one.
     *
     * @return list<EarlierTurnV1>
     */
    public function earlierTurns(ProfileRuntime $profile, ?string $conversationId): array
    {
        $provider = $this->registry !== null ? $this->registry->implementation('conversation_context') : null;
        if (!$provider instanceof ConversationContextV1 || $conversationId === null || preg_match('/^[a-f0-9]{32}$/D', $conversationId) !== 1) {
            return [];
        }

        try {
            $turns = $provider->earlierTurns(new ProfileIdV1($profile->id()), $conversationId);
            if (!is_array($turns)) {
                return [];
            }
            foreach ($turns as $turn) {
                if (!$turn instanceof EarlierTurnV1) {
                    return [];
                }
            }
            $turns = array_slice(array_values($turns), -self::MAX_EARLIER_TURNS);
            $size = static fn (array $list): int => array_sum(array_map(static fn (EarlierTurnV1 $t): int => strlen($t->question()) + strlen($t->answer()), $list));
            while ($turns !== [] && $size($turns) > self::MAX_CONTEXT_BYTES) {
                array_shift($turns);
            }

            return $turns;
        } catch (\Throwable $exception) {
            return [];
        }
    }

    /** Offers a completed, validated turn to the recorder seam; a failure never reaches the visitor. */
    public function recordCompletedTurn(CompletedTurnV1 $turn): void
    {
        $recorder = $this->registry !== null ? $this->registry->implementation('completed_public_turn') : null;
        if (!$recorder instanceof CompletedTurnRecorderV1) {
            return;
        }

        try {
            $recorder->record($turn);
        } catch (\Throwable $exception) {
            // Recording is best-effort and never changes the answer.
        }
    }

    /**
     * Owner-verified chronology facts for a profile. Supplementary only: any failure, a non-list,
     * a wrong entry type or more than 20 facts yields none, so the answer falls back to the
     * corpus alone rather than to a partly trusted list.
     *
     * @return list<ChronologyFactV1>
     */
    public function chronology(ProfileRuntime $profile): array
    {
        $provider = $this->registry !== null ? $this->registry->implementation('supplementary_chronology') : null;
        if (!$provider instanceof ChronologyProviderV1) {
            return [];
        }

        try {
            $facts = $provider->factsFor(new ProfileIdV1($profile->id()));
            if (!is_array($facts) || count($facts) > self::MAX_CHRONOLOGY_FACTS) {
                return [];
            }
            foreach ($facts as $fact) {
                if (!$fact instanceof ChronologyFactV1) {
                    return [];
                }
            }

            return array_values($facts);
        } catch (\Throwable $exception) {
            return [];
        }
    }

    /**
     * A previously approved answer for this key, or null. Core re-validates what it receives: an
     * exception, a wrong type or an empty, oversized or control-character answer is a miss.
     */
    public function cachedAnswer(CacheKeyV1 $key): ?string
    {
        $cache = $this->registry !== null ? $this->registry->implementation('eligible_answer_cache') : null;
        if (!$cache instanceof AnswerCacheV1) {
            return null;
        }

        try {
            $hit = $cache->lookup($key);
            if (!$hit instanceof CachedAnswerV1) {
                return null;
            }
            $answer = trim($hit->renderedAnswer());

            return $answer !== '' && strlen($answer) <= 5000 && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $answer) !== 1 ? $answer : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /** Offers a validated provider answer to the cache; a failure here never reaches the visitor. */
    public function offerAnswer(CacheKeyV1 $key, string $answer, string $question): void
    {
        $cache = $this->registry !== null ? $this->registry->implementation('eligible_answer_cache') : null;
        if (!$cache instanceof AnswerCacheV1) {
            return;
        }

        try {
            $cache->store($key, new CachedAnswerV1($answer), $question);
        } catch (\Throwable $exception) {
            // The cache is an optimisation; the answer has already been validated and is returned regardless.
        }
    }

    /** @return array{status: string, tested_at: string, fingerprint: string} */
    public function testRecord(ProfileRuntime $profile, CoreSettings $settings): array
    {
        if ($profile->isDefault()) {
            return [
                'status' => $settings->connectionTestStatus(),
                'tested_at' => $settings->connectionTestTime(),
                'fingerprint' => $settings->connectionTestFingerprint(),
            ];
        }

        return $this->records->get($profile->id());
    }

    public function recordTest(ProfileRuntime $profile, string $status, string $fingerprint, string $testedAt): void
    {
        if (!$profile->isDefault()) {
            $this->records->put($profile->id(), $status, $fingerprint, $testedAt);
        }
    }
}
