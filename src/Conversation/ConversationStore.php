<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Conversation;

interface ConversationStore
{
    public function record(ConversationTurn $turn): void;

    /**
     * Newest conversation first.
     *
     * @return list<array{conversation_id: string, turn_count: int, started_at: string, last_at: string, first_question: string, corpus_version?: string}>
     */
    public function listConversations(int $limit, int $offset): array;

    public function countConversations(): int;

    /** @return list<ConversationTurn> Chronological order. */
    public function turns(string $conversationId): array;

    /**
     * @param list<string> $conversationIds
     * @return int Number of turns removed.
     */
    public function deleteConversations(array $conversationIds): int;

    /** @return int Number of turns removed. */
    public function deleteAll(): int;

    /**
     * Remove up to $limit turns created before the UTC cutoff (`Y-m-d H:i:s`).
     *
     * @return int Number of turns removed.
     */
    public function purgeOlderThan(string $cutoffUtc, int $limit): int;
}
