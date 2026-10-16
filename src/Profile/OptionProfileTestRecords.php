<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Profile;

/**
 * Stores only `never`/`passed`/`failed`, a UTC time and an opaque HMAC fingerprint per
 * profile id; never a secret, payload, vector-store reference or retrieved text.
 */
final class OptionProfileTestRecords implements ProfileTestRecords
{
    public const OPTION_NAME = 'ncli_core_profile_tests';
    private const MAX_PROFILES = 100;

    public function get(string $profileId): array
    {
        $none = ['status' => 'never', 'tested_at' => '', 'fingerprint' => ''];
        if (!function_exists('get_option')) {
            return $none;
        }

        $all = get_option(self::OPTION_NAME, []);
        $record = is_array($all) && is_array($all[$profileId] ?? null) ? $all[$profileId] : null;
        if ($record === null) {
            return $none;
        }

        $status = $record['status'] ?? '';
        $testedAt = $record['tested_at'] ?? '';
        $fingerprint = $record['fingerprint'] ?? '';
        if (!is_string($status) || !in_array($status, ['passed', 'failed'], true)
            || !is_string($testedAt) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $testedAt) !== 1
            || !is_string($fingerprint) || preg_match('/^[a-f0-9]{64}$/', $fingerprint) !== 1) {
            return $none;
        }

        return ['status' => $status, 'tested_at' => $testedAt, 'fingerprint' => $fingerprint];
    }

    public function put(string $profileId, string $status, string $fingerprint, string $testedAt): void
    {
        if (!function_exists('get_option') || !function_exists('update_option')
            || !in_array($status, ['passed', 'failed'], true)
            || preg_match('/^[a-f0-9]{64}$/', $fingerprint) !== 1
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $testedAt) !== 1) {
            return;
        }

        $all = get_option(self::OPTION_NAME, []);
        $all = is_array($all) ? $all : [];
        $all[$profileId] = ['status' => $status, 'tested_at' => $testedAt, 'fingerprint' => $fingerprint];
        if (count($all) > self::MAX_PROFILES) {
            $all = array_slice($all, -self::MAX_PROFILES, null, true);
        }
        update_option(self::OPTION_NAME, $all, false);
    }
}
