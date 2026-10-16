<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Api\V1\Contracts\ConversationArchiveV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ConversationGovernanceV1;
use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Api\V1\Value\ArchivedTurnV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Conversation\ConversationCriteriaStore;
use NicheCleverInteractive\Core\Conversation\ConversationPurge;
use NicheCleverInteractive\Core\Conversation\ConversationRecorder;
use NicheCleverInteractive\Core\Conversation\ConversationStore;
use NicheCleverInteractive\Core\Conversation\ConversationTurn;
use NicheCleverInteractive\Core\Conversation\GuardedConversationArchive;
use NicheCleverInteractive\Core\Conversation\WordPressConversationStore;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;
use NicheCleverInteractive\Core\Profile\ProfileTestRecords;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$throws = static function (callable $fn, string $class): bool {
    try {
        $fn();
    } catch (Throwable $e) {
        return $e instanceof $class;
    }
    return false;
};
$cid = static fn (int $n): string => str_pad(dechex($n), 32, '0', STR_PAD_LEFT);

// ---- ArchivedTurnV1 -----------------------------------------------------------------------------------
$turn = new ArchivedTurnV1(7, $cid(1), 'docs', '2026-10-01 10:00:00', 'Q?', 'A.');
$assert($turn->id() === 7 && $turn->profileId() === 'docs' && $turn->createdAt() === '2026-10-01 10:00:00' && $turn->question() === 'Q?', 'An archived turn carries its stored values.');
foreach ([[0, $cid(1), 'docs', '2026-10-01 10:00:00'], [1, 'not-hex', 'docs', '2026-10-01 10:00:00'], [1, $cid(1), 'Bad Profile', '2026-10-01 10:00:00'], [1, $cid(1), 'docs', 'yesterday']] as [$id, $conv, $profile, $at]) {
    $assert($throws(static fn () => new ArchivedTurnV1($id, $conv, $profile, $at, 'Q', 'A'), InvalidArgumentException::class), 'A malformed archived turn is rejected.');
}

/** In-memory log implementing both interfaces. */
$makeStore = static function (int $now) use ($cid): object {
    return new class($now, $cid) implements ConversationStore, ConversationCriteriaStore {
        /** @var list<array{id: int, turn: ConversationTurn}> */
        public array $rows = [];
        public int $nextId = 1;
        /** @var list<string> */
        public array $calls = [];
        /** @var callable */
        private $cid;
        public function __construct(int $now, callable $cid)
        {
            $this->cid = $cid;
        }
        public function add(string $profile, int $daysOld, int $now, string $question = 'Q?'): void
        {
            $at = gmdate('Y-m-d H:i:s', $now - $daysOld * 86400);
            $this->rows[] = ['id' => $this->nextId, 'turn' => new ConversationTurn(($this->cid)($this->nextId), $profile, $question, 'A.', '1.0.0', 'c', '', $at)];
            ++$this->nextId;
        }
        public function record(ConversationTurn $turn): void
        {
            $this->rows[] = ['id' => $this->nextId++, 'turn' => $turn];
        }
        public function listConversations(int $limit, int $offset): array { return []; }
        public function countConversations(): int { return 0; }
        public function turns(string $conversationId): array { return []; }
        public function deleteConversations(array $conversationIds): int { return 0; }
        public function deleteAll(): int { $n = count($this->rows); $this->rows = []; return $n; }
        public function purgeOlderThan(string $cutoffUtc, int $limit): int
        {
            $this->calls[] = 'purgeOlderThan';
            return $this->remove(static fn (array $r): bool => $r['turn']->createdAt() < $cutoffUtc, $limit);
        }
        public function countMatching(?string $profileId, ?string $fromUtc, ?string $toUtc): int
        {
            return count($this->match($profileId, $fromUtc, $toUtc));
        }
        public function pageMatching(?string $profileId, ?string $fromUtc, ?string $toUtc, int $afterId, int $limit): array
        {
            return array_slice(array_values(array_filter($this->match($profileId, $fromUtc, $toUtc), static fn (array $r): bool => $r['id'] > $afterId)), 0, $limit);
        }
        public function deleteMatching(string $profileId, ?string $fromUtc, ?string $toUtc, int $limit): int
        {
            $ids = array_column($this->match($profileId, $fromUtc, $toUtc), 'id');
            return $this->remove(static fn (array $r): bool => in_array($r['id'], $ids, true), $limit);
        }
        public function purgeProfileOlderThan(string $profileId, string $cutoffUtc, int $limit): int
        {
            $this->calls[] = 'purgeProfileOlderThan:' . $profileId;
            return $this->remove(static fn (array $r): bool => $r['turn']->profileId() === $profileId && $r['turn']->createdAt() < $cutoffUtc, $limit);
        }
        public function purgeOlderThanExcluding(array $profileIds, string $cutoffUtc, int $limit): int
        {
            $this->calls[] = 'purgeOlderThanExcluding:' . implode(',', $profileIds);
            return $this->remove(static fn (array $r): bool => !in_array($r['turn']->profileId(), $profileIds, true) && $r['turn']->createdAt() < $cutoffUtc, $limit);
        }
        /** @return list<array{id: int, turn: ConversationTurn}> */
        private function match(?string $profileId, ?string $from, ?string $to): array
        {
            return array_values(array_filter($this->rows, static function (array $r) use ($profileId, $from, $to): bool {
                $at = $r['turn']->createdAt();
                return ($profileId === null || $r['turn']->profileId() === $profileId) && ($from === null || $at >= $from) && ($to === null || $at <= $to);
            }));
        }
        private function remove(callable $test, int $limit): int
        {
            $removed = 0;
            foreach ($this->rows as $i => $row) {
                if ($removed < $limit && $test($row)) {
                    unset($this->rows[$i]);
                    ++$removed;
                }
            }
            $this->rows = array_values($this->rows);
            return $removed;
        }
    };
};

// ---- Guarded archive ------------------------------------------------------------------------------------
$now = 1_800_000_000;
$store = $makeStore($now);
$store->add('docs', 1, $now, 'Docs recent');
$store->add('docs', 40, $now, 'Docs old');
$store->add('default', 40, $now, 'Default old');
$can = true;
$archive = new GuardedConversationArchive($store, static function () use (&$can): bool {
    return $can;
});
$assert($archive instanceof ConversationArchiveV1 && $archive->count(null, null, null) === 3 && $archive->count('docs', null, null) === 2, 'The archive counts by profile.');
$day = static fn (int $daysAgo): string => gmdate('Y-m-d', $now - $daysAgo * 86400);
$assert($archive->count('docs', $day(5), null) === 1 && $archive->count('docs', null, $day(5)) === 1 && $archive->count('docs', $day(50), $day(30)) === 1, 'Date filters are inclusive and applied.');
$page = $archive->page('docs', null, null, 0, 10);
$assert(count($page) === 2 && $page[0] instanceof ArchivedTurnV1 && $page[0]->question() === 'Docs recent' && $page[0]->id() < $page[1]->id(), 'Pages come back oldest id first as archived turns.');
$assert(count($archive->page('docs', null, null, $page[0]->id(), 10)) === 1 && count($archive->page(null, null, null, 0, 2)) === 2, 'afterId and limit page through the log.');
$can = false;
foreach ([static fn () => $archive->count(null, null, null), static fn () => $archive->page(null, null, null, 0, 10), static fn () => $archive->delete('docs', null, null, 10)] as $call) {
    $assert($throws($call, RuntimeException::class), 'Without manage_options every archive call is refused.');
}
$assert(count($store->rows) === 3, 'A refused delete removes nothing.');
$can = true;
$bad = [
    static fn () => $archive->page(null, null, null, 0, 0), static fn () => $archive->page(null, null, null, 0, 501), static fn () => $archive->page(null, null, null, -1, 10),
    static fn () => $archive->count(null, '2026-13-01', null), static fn () => $archive->count(null, '10/01/2026', null), static fn () => $archive->count(null, null, '2026-02-30'),
    static fn () => $archive->count(null, '2026-10-05', '2026-10-01'), static fn () => $archive->count('Bad Profile', null, null),
    static fn () => $archive->delete('docs', null, null, 0), static fn () => $archive->delete('docs', null, null, 501), static fn () => $archive->delete('Bad Profile', null, null, 10),
];
foreach ($bad as $i => $call) {
    $assert($throws($call, InvalidArgumentException::class), "Out-of-bounds archive call #$i is rejected.");
}
$deleted = $archive->delete('docs', $day(50), $day(30), 10);
$assert($deleted === 1 && $archive->count('docs', null, null) === 1 && $archive->count('default', null, null) === 1, 'Erasure is scoped to the profile and date range.');
$assert($archive->delete('docs', null, null, 1) === 1 && $archive->count(null, null, null) === 1, 'Delete honours its limit and leaves other profiles.');

// ---- Governance policy through the directory ---------------------------------------------------------------
$governance = new class implements ConversationGovernanceV1 {
    public ?ConversationArchiveV1 $archive = null;
    public int $attached = 0;
    public bool $throwAttach = false;
    /** @var mixed */
    public $recording = null;
    /** @var mixed */
    public $retention = [];
    public bool $throwPolicy = false;
    public function attach(ConversationArchiveV1 $archive): void
    {
        ++$this->attached;
        if ($this->throwAttach) {
            throw new RuntimeException('attach failed');
        }
        $this->archive = $archive;
    }
    public function recordingFor(ProfileIdV1 $profileId): ?bool
    {
        if ($this->throwPolicy) {
            throw new RuntimeException('policy down');
        }
        return is_array($this->recording) ? ($this->recording[$profileId->value()] ?? null) : $this->recording;
    }
    public function retentionOverrides(): array
    {
        if ($this->throwPolicy) {
            throw new RuntimeException('policy down');
        }
        return $this->retention;
    }
};
$registry = new ExtensionRegistryV1();
$registry->register('conversation_governance', $governance);
$records = new class implements ProfileTestRecords {
    public function get(string $profileId): array { return ['status' => 'never', 'tested_at' => '', 'fingerprint' => '']; }
    public function put(string $profileId, string $status, string $fingerprint, string $testedAt): void {}
};
$directory = new ProfileDirectory($registry, $records);
$none = new ProfileDirectory(new ExtensionRegistryV1(), $records);
$directory->attachArchive($archive);
$assert($governance->attached === 1 && $governance->archive === $archive, 'The extension receives the guarded archive.');
$governance->throwAttach = true;
$directory->attachArchive($archive);
$none->attachArchive($archive);
$assert($governance->attached === 2, 'A failing attach is swallowed and a missing extension is ignored.');
$governance->recording = ['docs' => true, 'quiet' => false];
$assert($directory->recordingOverride('docs') === true && $directory->recordingOverride('quiet') === false && $directory->recordingOverride('other') === null && $none->recordingOverride('docs') === null, 'Recording overrides are true, false or null.');
$governance->recording = 'yes';
$assert($directory->recordingOverride('docs') === null, 'A non-boolean decision defers to Core.');
$governance->throwPolicy = true;
$assert($directory->recordingOverride('docs') === null && $directory->retentionOverrides() === [], 'A failing policy defers to Core entirely.');
$governance->throwPolicy = false;
$governance->retention = ['docs' => 30, 'keep' => 0, 'long' => 365];
$assert($directory->retentionOverrides() === ['docs' => 30, 'keep' => 0, 'long' => 365] && $none->retentionOverrides() === [], 'Valid retention overrides are returned.');
foreach ([['docs' => 45], ['docs' => '30'], ['Bad Id' => 30], [0 => 30], ['docs' => -1], 'text', ['docs' => 30, 'x' => 7], array_fill_keys(array_map(static fn (int $i): string => 'p' . $i, range(1, 101)), 30)] as $malformed) {
    $governance->retention = $malformed;
    $assert($directory->retentionOverrides() === [], 'Malformed retention ' . json_encode(is_array($malformed) ? array_slice($malformed, 0, 3) : $malformed) . ' yields none, never a partial list.');
}

// ---- Recording policy ------------------------------------------------------------------------------------------
$logStore = $makeStore($now);
$on = CoreSettings::fromArray(array_replace_recursive(CoreSettings::defaults(), ['conversation_log' => ['enabled' => true, 'retention_days' => 90]]));
$off = CoreSettings::fromArray(CoreSettings::defaults());
$recorder = new ConversationRecorder($logStore, static fn (): int => 1_800_000_000, $directory);
$governance->recording = ['docs' => true, 'quiet' => false];
$recorder->recordProviderAnswer($off, $cid(1), 'docs', 'Q?', 'A.', '');
$assert(count($logStore->rows) === 1, 'An explicit per-profile "record" works even when Core\'s global setting is off.');
$recorder->recordProviderAnswer($on, $cid(2), 'quiet', 'Q?', 'A.', '');
$assert(count($logStore->rows) === 1, 'An explicit per-profile "do not record" overrides Core\'s global setting.');
$recorder->recordProviderAnswer($on, $cid(3), 'other', 'Q?', 'A.', '');
$recorder->recordProviderAnswer($off, $cid(4), 'other', 'Q?', 'A.', '');
$assert(count($logStore->rows) === 2, 'With no override Core\'s setting decides.');
$governance->throwPolicy = true;
$recorder->recordProviderAnswer($off, $cid(5), 'docs', 'Q?', 'A.', '');
$assert(count($logStore->rows) === 2, 'A failing policy falls back to Core\'s setting (off records nothing).');
$governance->throwPolicy = false;
$plainRecorder = new ConversationRecorder($logStore, static fn (): int => 1_800_000_000);
$plainRecorder->recordProviderAnswer($off, $cid(6), 'docs', 'Q?', 'A.', '');
$assert(count($logStore->rows) === 2, 'Without a policy the recorder behaves exactly as before.');

// ---- Retention schedules -----------------------------------------------------------------------------------------
$build = static function () use ($makeStore, $now): object {
    $s = $makeStore($now);
    foreach (['default', 'docs', 'keep'] as $profile) {
        foreach ([5, 45, 120, 400] as $age) {
            $s->add($profile, $age, $now);
        }
    }
    return $s;
};
$settings90 = CoreSettings::fromArray(array_replace_recursive(CoreSettings::defaults(), ['conversation_log' => ['retention_days' => 90]]));
$settingsForever = CoreSettings::fromArray(array_replace_recursive(CoreSettings::defaults(), ['conversation_log' => ['retention_days' => 0]]));
$ages = static function (object $s, string $profile) use ($now): array {
    $out = [];
    foreach ($s->rows as $row) {
        if ($row['turn']->profileId() === $profile) {
            $out[] = (int) round(($now - strtotime($row['turn']->createdAt() . ' UTC')) / 86400);
        }
    }
    sort($out);
    return $out;
};
$clock = static fn (): int => 1_800_000_000;

$s = $build();
$removed = (new ConversationPurge($s, $clock))->run($settings90);
$assert($removed === 6 && $ages($s, 'default') === [5, 45] && $ages($s, 'docs') === [5, 45] && $ages($s, 'keep') === [5, 45], 'Without an extension everything follows Core\'s 90 days, exactly as before.');
$assert($s->calls === ['purgeOlderThan'], 'Without an extension Core uses its original purge call.');

$governance->retention = ['docs' => 30, 'keep' => 0];
$s = $build();
$removed = (new ConversationPurge($s, $clock, $directory))->run($settings90);
$assert($ages($s, 'docs') === [5] && $ages($s, 'keep') === [5, 45, 120, 400] && $ages($s, 'default') === [5, 45], 'A profile follows its own schedule; "0" keeps everything; the rest follow Core.');
$assert($removed === 3 + 2, 'The count adds the profile and global removals.');
$s = $build();
(new ConversationPurge($s, $clock, $directory))->run($settingsForever);
$assert($ages($s, 'docs') === [5] && $ages($s, 'default') === [5, 45, 120, 400] && $ages($s, 'keep') === [5, 45, 120, 400], 'With Core set to keep forever, only a profile with its own shorter schedule is purged.');
$governance->throwPolicy = true;
$s = $build();
(new ConversationPurge($s, $clock, $directory))->run($settings90);
$assert($ages($s, 'docs') === [5, 45] && $ages($s, 'keep') === [5, 45], 'If the policy fails, Core\'s schedule applies to every profile: records are never kept longer than Core says.');
$governance->throwPolicy = false;
$governance->retention = ['docs' => 365];
$s = $build();
(new ConversationPurge($s, $clock, $directory))->run($settings90);
$assert($ages($s, 'docs') === [5, 45, 120] && $ages($s, 'default') === [5, 45] && in_array('purgeOlderThanExcluding:docs', $s->calls, true), 'A longer profile schedule keeps its records past Core\'s days and excludes it from the global purge.');
$governance->retention = [];

// ---- SQL shape against a recording $wpdb -----------------------------------------------------------------------------------
$sqlLog = [];
$GLOBALS['wpdb'] = new class($sqlLog) {
    public string $prefix = 'wp_';
    /** @var list<string> */
    public array $log;
    public function __construct(array &$log)
    {
        $this->log = &$log;
    }
    public function prepare(string $query, ...$args): string
    {
        $i = 0;
        return (string) preg_replace_callback('/%[sd]/', function (array $m) use (&$i, $args): string {
            $value = $args[$i++];
            return $m[0] === '%d' ? (string) (int) $value : "'" . addslashes((string) $value) . "'";
        }, $query);
    }
    public function get_var(string $sql)
    {
        $this->log[] = $sql;
        return 3;
    }
    public function get_results(string $sql, $output = null)
    {
        $this->log[] = $sql;
        return [['id' => '9', 'conversation_id' => str_repeat('a', 32), 'profile_id' => 'docs', 'question' => 'Q?', 'answer' => 'A.', 'core_version' => '1', 'corpus_version' => 'c', 'policy_fingerprint' => '', 'created_at' => '2026-10-01 10:00:00'], ['id' => '10', 'conversation_id' => 'bad', 'profile_id' => 'docs', 'question' => 'Q?', 'answer' => 'A.', 'core_version' => '1', 'corpus_version' => 'c', 'policy_fingerprint' => '', 'created_at' => '2026-10-01 10:00:00']];
    }
    public function query(string $sql)
    {
        $this->log[] = $sql;
        return 4;
    }
};
define('ARRAY_A', 'ARRAY_A');
$sql = new WordPressConversationStore();
$wpdb = $GLOBALS['wpdb'];
$assert($sql->countMatching(null, null, null) === 3 && end($wpdb->log) === 'SELECT COUNT(*) FROM wp_ncli_core_conversation_turns WHERE 1 = 1', 'Counting without criteria uses no placeholders.');
$sql->countMatching('docs', '2026-10-01 00:00:00', '2026-10-31 23:59:59');
$assert(end($wpdb->log) === "SELECT COUNT(*) FROM wp_ncli_core_conversation_turns WHERE 1 = 1 AND profile_id = 'docs' AND created_at >= '2026-10-01 00:00:00' AND created_at <= '2026-10-31 23:59:59'", 'Counting binds profile and both dates.');
$rows = $sql->pageMatching('docs', null, '2026-10-31 23:59:59', 5, 200);
$assert(end($wpdb->log) === "SELECT * FROM wp_ncli_core_conversation_turns WHERE 1 = 1 AND profile_id = 'docs' AND created_at <= '2026-10-31 23:59:59' AND id > 5 ORDER BY id ASC LIMIT 200" && count($rows) === 1 && $rows[0]['id'] === 9, 'Paging binds criteria then the cursor and limit; rows that fail the turn contract are skipped.');
$assert($sql->deleteMatching('docs', null, null, 500) === 4 && end($wpdb->log) === "DELETE FROM wp_ncli_core_conversation_turns WHERE 1 = 1 AND profile_id = 'docs' ORDER BY id ASC LIMIT 500", 'Erasure always carries the profile and a limit.');
$sql->purgeProfileOlderThan('docs', '2026-09-01 00:00:00', 500);
$assert(end($wpdb->log) === "DELETE FROM wp_ncli_core_conversation_turns WHERE profile_id = 'docs' AND created_at < '2026-09-01 00:00:00' ORDER BY id ASC LIMIT 500", 'A profile purge is scoped to that profile.');
$sql->purgeOlderThanExcluding(['docs', 'keep'], '2026-09-01 00:00:00', 500);
$assert(end($wpdb->log) === "DELETE FROM wp_ncli_core_conversation_turns WHERE created_at < '2026-09-01 00:00:00' AND profile_id NOT IN ('docs', 'keep') ORDER BY id ASC LIMIT 500", 'The global purge excludes the profiles with their own schedule.');
$before = count($wpdb->log);
$sql->purgeProfileOlderThan('Bad Profile', '2026-09-01 00:00:00', 500);
$sql->purgeProfileOlderThan('docs', 'yesterday', 500);
$sql->purgeOlderThanExcluding(['docs'], 'yesterday', 500);
$assert(count($wpdb->log) === $before, 'Malformed arguments never reach the database.');
$sql->purgeOlderThanExcluding([], '2026-09-01 00:00:00', 500);
$assert(end($wpdb->log) === "DELETE FROM wp_ncli_core_conversation_turns WHERE created_at < '2026-09-01 00:00:00' ORDER BY id ASC LIMIT 500", 'With nothing to exclude the original purge statement is used.');

// ---- Wiring and capability --------------------------------------------------------------------------------------------------
$pluginSource = (string) file_get_contents(dirname(__DIR__) . '/src/Plugin.php');
$assert(strpos($pluginSource, "do_action('ncli_core_api_v1_ready'") < strpos($pluginSource, 'attachArchive(new GuardedConversationArchive'), 'Core attaches the archive only after extensions have registered.');
$assert(str_contains($pluginSource, 'new ConversationPurge($conversationStore, null, $profiles)') && str_contains($pluginSource, 'new ConversationRecorder($conversationStore, null, $profiles)'), 'The purge and the recorder consult the extension policy.');
$assert(in_array('conversation_governance', (new ExtensionRegistryV1())->capabilities(), true) && in_array('conversation_governance', (new ExtensionRegistryV1())->knownSeams(), true), 'The seam and capability are advertised.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS: CC-7 conversation governance checks verified\n";
