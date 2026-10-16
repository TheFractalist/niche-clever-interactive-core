<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Conversation;

/**
 * Criteria-based reads and deletes over the conversation log, used by the guarded archive and the
 * per-profile retention purge. Kept separate from ConversationStore so existing implementations of
 * that interface are unaffected. Dates are UTC `Y-m-d H:i:s`.
 */
interface ConversationCriteriaStore
{
    public function countMatching(?string $profileId, ?string $fromUtc, ?string $toUtc): int;

    /** @return list<array{id: int, turn: ConversationTurn}> Oldest first, ids greater than $afterId. */
    public function pageMatching(?string $profileId, ?string $fromUtc, ?string $toUtc, int $afterId, int $limit): array;

    public function deleteMatching(string $profileId, ?string $fromUtc, ?string $toUtc, int $limit): int;

    public function purgeProfileOlderThan(string $profileId, string $cutoffUtc, int $limit): int;

    /** @param list<string> $profileIds Profiles to leave alone. */
    public function purgeOlderThanExcluding(array $profileIds, string $cutoffUtc, int $limit): int;
}
