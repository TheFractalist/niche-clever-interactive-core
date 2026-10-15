<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Conversation;

use RuntimeException;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The conversation log is an administrator-managed NCI table; reads and deletes must reflect current rows and cannot use the object cache.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Every value is passed through $wpdb->prepare(); the table identifier is derived solely from $wpdb->prefix and a constant suffix.
final class WordPressConversationStore implements ConversationStore
{
    public function record(ConversationTurn $turn): void
    {
        [$wpdb, $table] = $this->database();
        $result = $wpdb->insert(
            $table,
            [
                'conversation_id' => $turn->conversationId(),
                'profile_id' => $turn->profileId(),
                'question' => $turn->question(),
                'answer' => $turn->answer(),
                'core_version' => $turn->coreVersion(),
                'corpus_version' => $turn->corpusVersion(),
                'policy_fingerprint' => $turn->policyFingerprint(),
                'created_at' => $turn->createdAt(),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        if ($result === false) {
            throw new RuntimeException('The conversation turn could not be stored.');
        }
    }

    public function listConversations(int $limit, int $offset): array
    {
        [$wpdb, $table] = $this->database();
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT conversation_id, COUNT(*) AS turn_count, MIN(created_at) AS started_at, MAX(created_at) AS last_at, MIN(id) AS first_id FROM {$table} GROUP BY conversation_id ORDER BY last_at DESC, first_id DESC LIMIT %d OFFSET %d",
                max(1, $limit),
                max(0, $offset)
            ),
            ARRAY_A
        );
        if (!is_array($rows)) {
            return [];
        }

        $conversations = [];
        foreach ($rows as $row) {
            $question = $wpdb->get_var($wpdb->prepare("SELECT question FROM {$table} WHERE id = %d", (int) $row['first_id']));
            $conversations[] = [
                'conversation_id' => (string) $row['conversation_id'],
                'turn_count' => (int) $row['turn_count'],
                'started_at' => (string) $row['started_at'],
                'last_at' => (string) $row['last_at'],
                'first_question' => is_string($question) ? $question : '',
            ];
        }

        return $conversations;
    }

    public function countConversations(): int
    {
        [$wpdb, $table] = $this->database();

        return (int) $wpdb->get_var("SELECT COUNT(DISTINCT conversation_id) FROM {$table}");
    }

    public function turns(string $conversationId): array
    {
        if (!ConversationId::isValid($conversationId)) {
            return [];
        }

        [$wpdb, $table] = $this->database();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE conversation_id = %s ORDER BY id ASC", $conversationId), ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $turns = [];
        foreach ($rows as $row) {
            try {
                $turns[] = new ConversationTurn(
                    (string) $row['conversation_id'],
                    (string) $row['profile_id'],
                    (string) $row['question'],
                    (string) $row['answer'],
                    (string) $row['core_version'],
                    (string) $row['corpus_version'],
                    (string) $row['policy_fingerprint'],
                    (string) $row['created_at']
                );
            } catch (\InvalidArgumentException $exception) {
                // A row that no longer satisfies the turn contract is skipped, not rendered.
                continue;
            }
        }

        return $turns;
    }

    public function deleteConversations(array $conversationIds): int
    {
        $valid = array_values(array_unique(array_filter($conversationIds, [ConversationId::class, 'isValid'])));
        if ($valid === []) {
            return 0;
        }

        [$wpdb, $table] = $this->database();
        $removed = 0;
        foreach (array_chunk($valid, 50) as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), '%s'));
            $result = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE conversation_id IN ({$placeholders})", ...$chunk));
            if ($result === false) {
                throw new RuntimeException('Conversation deletion failed.');
            }
            $removed += (int) $result;
        }

        return $removed;
    }

    public function deleteAll(): int
    {
        [$wpdb, $table] = $this->database();
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($wpdb->query("DELETE FROM {$table}") === false) {
            throw new RuntimeException('Conversation deletion failed.');
        }

        return $count;
    }

    public function purgeOlderThan(string $cutoffUtc, int $limit): int
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $cutoffUtc) !== 1) {
            return 0;
        }

        [$wpdb, $table] = $this->database();
        $result = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s ORDER BY id ASC LIMIT %d", $cutoffUtc, max(1, $limit)));
        if ($result === false) {
            throw new RuntimeException('Conversation purge failed.');
        }

        return (int) $result;
    }

    /** @return array{0: object, 1: string} */
    private function database(): array
    {
        global $wpdb;
        $table = ConversationTable::name();
        if ($table === null || !isset($wpdb) || !is_object($wpdb)) {
            throw new RuntimeException('Conversation storage is unavailable.');
        }

        return [$wpdb, $table];
    }
}
