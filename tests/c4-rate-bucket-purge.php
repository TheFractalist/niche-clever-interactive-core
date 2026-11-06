<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Budget\PrunableBucketStore;
use NicheCleverInteractive\Core\Budget\RateBucketPurge;
use NicheCleverInteractive\Core\Budget\WordPressBucketStore;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

// An in-memory store holding one counter row per period.
$store = new class implements PrunableBucketStore {
    /** @var list<string> */
    public array $periods = [];
    /** @var list<array{string, int}> */
    public array $calls = [];
    public bool $fail = false;

    public function pruneBefore(string $periodStart, int $limit): int
    {
        if ($this->fail) {
            throw new RuntimeException('database unavailable');
        }
        $this->calls[] = [$periodStart, $limit];
        $removed = 0;
        foreach ($this->periods as $index => $period) {
            if ($removed < $limit && $period < $periodStart) {
                unset($this->periods[$index]);
                ++$removed;
            }
        }
        $this->periods = array_values($this->periods);

        return $removed;
    }
};

$now = strtotime('2026-11-06 15:30:00 UTC');
$clock = static fn (): int => $now;
foreach (['2026-10-01', '2026-11-04', '2026-11-05', '2026-11-06'] as $day) {
    $store->periods[] = $day . ' 00:00:00';
}

$removed = (new RateBucketPurge($store, $clock))->run();
$assert($removed === 2, 'Counters for days before yesterday must be removed.');
$assert($store->periods === ['2026-11-05 00:00:00', '2026-11-06 00:00:00'], 'Today\'s and yesterday\'s counters must be kept.');
$assert($store->calls[0][0] === '2026-11-05 00:00:00', 'The cutoff must be the start of the previous UTC day.');

// Large backlogs are removed in bounded batches, and the run stops at a short batch.
$store->periods = array_fill(0, 1200, '2026-01-01 00:00:00');
$store->calls = [];
$removed = (new RateBucketPurge($store, $clock))->run();
$assert($removed === 1200 && count($store->calls) === 3, 'A backlog must be pruned in 500-row batches until a short batch.');
$assert(array_unique(array_column($store->calls, 1)) === [500], 'Every batch must be bounded.');

// The batch ceiling stops a runaway table from holding the daily event open indefinitely.
$store->periods = array_fill(0, 20000, '2026-01-01 00:00:00');
$store->calls = [];
$removed = (new RateBucketPurge($store, $clock))->run();
$assert($removed === 10000 && count($store->calls) === 20, 'One run must remove at most 20 batches.');

// A failing store must not break the daily event.
$GLOBALS['ncliPurgeActions'] = [];
if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback): void
    {
        $GLOBALS['ncliPurgeActions'][$hook][] = $callback;
    }
}
$store->fail = true;
(new RateBucketPurge($store, $clock))->register();
$callbacks = $GLOBALS['ncliPurgeActions']['ncli_core_purge_conversations'] ?? [];
$assert(count($callbacks) === 1, 'Pruning must run on Core\'s existing daily purge event.');
$threw = false;
try {
    $callbacks[0]();
} catch (Throwable $exception) {
    $threw = true;
}
$assert(!$threw, 'A database error during pruning must be contained.');

// The WordPress store issues one bounded, prepared DELETE against its own table only.
$GLOBALS['wpdb'] = new class {
    public string $prefix = 'wp_';
    /** @var list<string> */
    public array $queries = [];

    public function prepare(string $sql, ...$args): string
    {
        foreach ($args as $arg) {
            $sql = preg_replace('/%[sd]/', is_int($arg) ? (string) $arg : "'" . addslashes((string) $arg) . "'", $sql, 1);
        }

        return $sql;
    }

    /** @return int|false */
    public function query(string $sql)
    {
        $this->queries[] = $sql;

        return 7;
    }
};
$deleted = (new WordPressBucketStore())->pruneBefore('2026-11-05 00:00:00', 500);
$query = $GLOBALS['wpdb']->queries[0] ?? '';
$assert($deleted === 7, 'The store must report the rows the database removed.');
$assert($query === "DELETE FROM wp_ncli_core_rate_buckets WHERE period_start < '2026-11-05 00:00:00' LIMIT 500", 'The prune must be a single bounded DELETE on Core\'s own counter table.');
$assert(count($GLOBALS['wpdb']->queries) === 1, 'Pruning must not open a transaction or touch another table.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "PASS: rate-limit counters are pruned after their day\n";
