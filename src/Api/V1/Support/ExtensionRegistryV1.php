<?php

namespace NicheCleverInteractive\Core\Api\V1\Support;

use InvalidArgumentException;
use LogicException;
use NicheCleverInteractive\Core\Api\V1\Contracts\AnswerCacheV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ChronologyProviderV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\CompletedTurnRecorderV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ConversationContextV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\EvidenceCitationsV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ProfileResolverV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ViewerAccessV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\WorkspaceExtensionV1;

final class ExtensionRegistryV1
{
    /**
     * @var array<string, class-string>
     */
    private const CONTRACTS = [
        'profile_resolution' => ProfileResolverV1::class,
        'supplementary_chronology' => ChronologyProviderV1::class,
        'eligible_answer_cache' => AnswerCacheV1::class,
        'completed_public_turn' => CompletedTurnRecorderV1::class,
        'workspace_extension' => WorkspaceExtensionV1::class,
        'viewer_access' => ViewerAccessV1::class,
        'conversation_context' => ConversationContextV1::class,
        'evidence_citations' => EvidenceCitationsV1::class,
    ];

    /**
     * Extension capabilities this Core build actually offers. An extension reads this
     * list to decide whether it may register; a capability is added here only in the
     * Core change that implements it, so the list is never aspirational.
     *
     * @var list<string>
     */
    private const CAPABILITIES = ['handshake', 'workspace_hosting', 'profile_resolution', 'viewer_access', 'supplementary_chronology', 'eligible_answer_cache', 'completed_public_turn', 'conversation_context', 'evidence_citations', 'evidence_screen'];

    /**
     * @var array<string, object>
     */
    private array $implementations = [];

    /**
     * @return list<string>
     */
    public function capabilities(): array
    {
        return self::CAPABILITIES;
    }

    /**
     * @return list<string>
     */
    public function knownSeams(): array
    {
        return array_keys(self::CONTRACTS);
    }

    public function register(string $seam, object $implementation): void
    {
        if (!isset(self::CONTRACTS[$seam])) {
            throw new InvalidArgumentException('Unknown Core API seam.');
        }

        if (isset($this->implementations[$seam])) {
            throw new LogicException('A Core API seam may be registered only once.');
        }

        $contract = self::CONTRACTS[$seam];
        if (!$implementation instanceof $contract) {
            throw new InvalidArgumentException('Implementation does not satisfy the seam contract.');
        }

        $this->implementations[$seam] = $implementation;
    }

    public function has(string $seam): bool
    {
        return isset($this->implementations[$seam]);
    }

    /**
     * For Core's own invocation of a registered seam. Extensions have no reason to call it.
     * Returns null for an unknown or unregistered seam so callers fail closed.
     */
    public function implementation(string $seam): ?object
    {
        return $this->implementations[$seam] ?? null;
    }
}
