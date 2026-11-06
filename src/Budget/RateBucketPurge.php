<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Budget;

use NicheCleverInteractive\Core\Conversation\ConversationPurge;

/**
 * Deletes daily request-allowance counters once their day is over. Limits only ever read the
 * current UTC day's row; the previous day's row is kept so a request that spans midnight can
 * still settle against it. Runs on Core's existing daily purge event.
 */
final class RateBucketPurge
{
    private const BATCH = 500;
    private const MAX_BATCHES = 20;

    private PrunableBucketStore $store;
    /** @var callable */
    private $clock;

    public function __construct(PrunableBucketStore $store, ?callable $clock = null)
    {
        $this->store = $store;
        $this->clock = $clock ?? 'time';
    }

    /** @return int Number of counter rows removed. */
    public function run(): int
    {
        $now = (int) call_user_func($this->clock);
        $cutoff = gmdate('Y-m-d 00:00:00', $now - 86400);
        $removed = 0;
        for ($batch = 0; $batch < self::MAX_BATCHES; ++$batch) {
            $count = $this->store->pruneBefore($cutoff, self::BATCH);
            $removed += $count;
            if ($count < self::BATCH) {
                break;
            }
        }

        return $removed;
    }

    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action(ConversationPurge::HOOK, function (): void {
            try {
                $this->run();
            } catch (\Throwable $exception) {
                // A database error leaves the counters in place until the next daily run.
            }
        });
    }
}
