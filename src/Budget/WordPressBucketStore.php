<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Budget;

use RuntimeException;

final class WordPressBucketStore implements BucketStore
{
    public function reserve(string $kind, string $scopeHash, string $periodStart, int $limit): bool
    {
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb) || !isset($wpdb->prefix)) {
            throw new RuntimeException('Rate storage is unavailable.');
        }
        $table = $wpdb->prefix . 'ncli_core_rate_buckets';
        // Establish the unique row in autocommit mode. Holding an insert-intention
        // lock while taking a row lock caused avoidable first-use deadlocks.
        $insert = $wpdb->prepare("INSERT IGNORE INTO {$table} (kind, scope_hash, period_start) VALUES (%s, %s, %s)", $kind, $scopeHash, $periodStart);
        if ($wpdb->query($insert) === false) {
            throw new RuntimeException('Rate reservation setup failed.');
        }

        return $this->transaction(function ($wpdb, string $table) use ($kind, $scopeHash, $periodStart, $limit): bool {
            $row = $wpdb->get_row($wpdb->prepare("SELECT reserved_count, committed_count FROM {$table} WHERE kind = %s AND scope_hash = %s AND period_start = %s FOR UPDATE", $kind, $scopeHash, $periodStart), ARRAY_A);
            if (!is_array($row)) {
                throw new RuntimeException('Rate reservation lock failed.');
            }
            if ((int) $row['reserved_count'] + (int) $row['committed_count'] >= $limit) {
                return false;
            }
            if ($wpdb->query($wpdb->prepare("UPDATE {$table} SET reserved_count = reserved_count + 1 WHERE kind = %s AND scope_hash = %s AND period_start = %s", $kind, $scopeHash, $periodStart)) === false) {
                throw new RuntimeException('Rate reservation update failed.');
            }
            return true;
        });
    }

    public function commit(string $kind, string $scopeHash, string $periodStart): void
    {
        $this->settle($kind, $scopeHash, $periodStart, 'committed_count = committed_count + 1');
    }

    public function release(string $kind, string $scopeHash, string $periodStart): void
    {
        $this->settle($kind, $scopeHash, $periodStart, 'committed_count = committed_count');
    }

    private function settle(string $kind, string $scopeHash, string $periodStart, string $commitExpression): void
    {
        $this->transaction(function ($wpdb, string $table) use ($kind, $scopeHash, $periodStart, $commitExpression): bool {
            $sql = "UPDATE {$table} SET reserved_count = CASE WHEN reserved_count > 0 THEN reserved_count - 1 ELSE 0 END, {$commitExpression} WHERE kind = %s AND scope_hash = %s AND period_start = %s";
            if ($wpdb->query($wpdb->prepare($sql, $kind, $scopeHash, $periodStart)) === false) {
                throw new RuntimeException('Rate reservation settlement failed.');
            }
            return true;
        });
    }

    /** @return mixed */
    private function transaction(callable $operation)
    {
        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb) || !isset($wpdb->prefix)) {
            throw new RuntimeException('Rate storage is unavailable.');
        }
        $table = $wpdb->prefix . 'ncli_core_rate_buckets';
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('Rate transaction could not start.');
        }
        try {
            $result = $operation($wpdb, $table);
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('Rate transaction could not commit.');
            }
            return $result;
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }
}
