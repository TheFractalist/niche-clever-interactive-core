<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Conversation;

use RuntimeException;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The conversation log is an administrator-managed NCI table; reads and deletes must reflect current rows and cannot use the object cache.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Every value is passed through $wpdb->prepare(); the table identifier is derived solely from $wpdb->prefix and a constant suffix.
// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- Values are bound with $wpdb->prepare(); this plugin-owned table identifier is a fixed suffix joined to and SQL-escaped from WordPress's site prefix.
final class WordPressConversationStore implements ConversationStore, ConversationCriteriaStore
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
                "SELECT conversation_id, COUNT(*) AS turn_count, MIN(created_at) AS started_at, MAX(created_at) AS last_at, MIN(id) AS first_id, MAX(id) AS last_id FROM {$table} GROUP BY conversation_id ORDER BY last_at DESC, first_id DESC LIMIT %d OFFSET %d",
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
            $corpus = $wpdb->get_var($wpdb->prepare("SELECT corpus_version FROM {$table} WHERE id = %d", (int) $row['last_id']));
            $conversations[] = [
                'conversation_id' => (string) $row['conversation_id'],
                'turn_count' => (int) $row['turn_count'],
                'started_at' => (string) $row['started_at'],
                'last_at' => (string) $row['last_at'],
                'first_question' => is_string($question) ? $question : '',
                'corpus_version' => is_string($corpus) ? $corpus : '',
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
            $result = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE conversation_id IN ({$placeholders})", ...$chunk)); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- One %s per value is built above; the table name is this plugin's own.
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

    public function countMatching(?string $profileId, ?string $fromUtc, ?string $toUtc): int
    {
        [$wpdb, $table] = $this->database();
        [$where, $args] = $this->criteria($profileId, $fromUtc, $toUtc);
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";

        return (int) $wpdb->get_var($args === [] ? $sql : $wpdb->prepare($sql, ...$args));
    }

    public function pageMatching(?string $profileId, ?string $fromUtc, ?string $toUtc, int $afterId, int $limit): array
    {
        [$wpdb, $table] = $this->database();
        [$where, $args] = $this->criteria($profileId, $fromUtc, $toUtc);
        $args[] = max(0, $afterId);
        $args[] = max(1, $limit);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE {$where} AND id > %d ORDER BY id ASC LIMIT %d", ...$args), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where holds only placeholders built by criteria(); the arguments match them in order.
        if (!is_array($rows)) {
            return [];
        }

        $page = [];
        foreach ($rows as $row) {
            try {
                $page[] = [
                    'id' => (int) $row['id'],
                    'turn' => new ConversationTurn(
                        (string) $row['conversation_id'],
                        (string) $row['profile_id'],
                        (string) $row['question'],
                        (string) $row['answer'],
                        (string) $row['core_version'],
                        (string) $row['corpus_version'],
                        (string) $row['policy_fingerprint'],
                        (string) $row['created_at']
                    ),
                ];
            } catch (\InvalidArgumentException $exception) {
                continue;
            }
        }

        return $page;
    }

    public function deleteMatching(string $profileId, ?string $fromUtc, ?string $toUtc, int $limit): int
    {
        [$wpdb, $table] = $this->database();
        [$where, $args] = $this->criteria($profileId, $fromUtc, $toUtc);
        $args[] = max(1, $limit);
        $result = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE {$where} ORDER BY id ASC LIMIT %d", ...$args));
        if ($result === false) {
            throw new RuntimeException('Conversation deletion failed.');
        }

        return (int) $result;
    }

    public function purgeProfileOlderThan(string $profileId, string $cutoffUtc, int $limit): int
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $cutoffUtc) !== 1 || preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $profileId) !== 1) {
            return 0;
        }

        [$wpdb, $table] = $this->database();
        $result = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE profile_id = %s AND created_at < %s ORDER BY id ASC LIMIT %d", $profileId, $cutoffUtc, max(1, $limit)));
        if ($result === false) {
            throw new RuntimeException('Conversation purge failed.');
        }

        return (int) $result;
    }

    public function purgeOlderThanExcluding(array $profileIds, string $cutoffUtc, int $limit): int
    {
        $profiles = array_values(array_unique(array_filter($profileIds, static fn ($id): bool => is_string($id) && preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $id) === 1)));
        if ($profiles === [] || count($profiles) > 200) {
            return $profiles === [] ? $this->purgeOlderThan($cutoffUtc, $limit) : 0;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $cutoffUtc) !== 1) {
            return 0;
        }

        [$wpdb, $table] = $this->database();
        $placeholders = implode(', ', array_fill(0, count($profiles), '%s'));
        $result = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s AND profile_id NOT IN ({$placeholders}) ORDER BY id ASC LIMIT %d", $cutoffUtc, ...array_merge($profiles, [max(1, $limit)])));
        if ($result === false) {
            throw new RuntimeException('Conversation purge failed.');
        }

        return (int) $result;
    }

    /**
     * @return array{0: string, 1: list<string>} WHERE clause with placeholders, and its arguments
     */
    private function criteria(?string $profileId, ?string $fromUtc, ?string $toUtc): array
    {
        $clauses = ['1 = 1'];
        $args = [];
        if ($profileId !== null) {
            $clauses[] = 'profile_id = %s';
            $args[] = $profileId;
        }
        if ($fromUtc !== null) {
            $clauses[] = 'created_at >= %s';
            $args[] = $fromUtc;
        }
        if ($toUtc !== null) {
            $clauses[] = 'created_at <= %s';
            $args[] = $toUtc;
        }

        return [implode(' AND ', $clauses), $args];
    }

    /** @return array{0: object, 1: string} */
    private function database(): array
    {
        global $wpdb;
        $table = ConversationTable::name();
        if ($table === null || !isset($wpdb) || !is_object($wpdb)) {
            throw new RuntimeException('Conversation storage is unavailable.');
        }

        return [$wpdb, esc_sql($table)];
    }
}
