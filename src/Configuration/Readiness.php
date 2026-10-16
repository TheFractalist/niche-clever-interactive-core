<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Configuration;

use NicheCleverInteractive\Core\Profile\ProfileRuntime;

final class Readiness
{
    public const READY = 'ready';
    public const DISABLED = 'disabled';
    public const MISSING_SECRET = 'missing_secret';
    public const MISSING_SCOPE_SECRET = 'missing_scope_secret';
    public const MISSING_VECTOR_STORE = 'missing_vector_store';
    public const MISSING_CORPUS_VERSION = 'missing_corpus_version';
    public const MISSING_CONNECTION_TEST = 'missing_connection_test';
    public const STALE_CONNECTION_TEST = 'stale_connection_test';

    private string $state;

    private function __construct(string $state)
    {
        $this->state = $state;
    }

    public static function evaluate(CoreSettings $settings, SecretResolver $secrets): self
    {
        if (!$settings->isEnabled()) {
            return new self(self::DISABLED);
        }

        return self::evaluateProfile(
            ProfileRuntime::fromSettings($settings),
            $secrets,
            [
                'status' => $settings->connectionTestStatus(),
                'tested_at' => $settings->connectionTestTime(),
                'fingerprint' => $settings->connectionTestFingerprint(),
            ]
        );
    }

    /**
     * Readiness of one resolved profile against its own Core-stored connection-test record.
     *
     * @param array{status: string, tested_at: string, fingerprint: string} $testRecord
     */
    public static function evaluateProfile(ProfileRuntime $profile, SecretResolver $secrets, array $testRecord): self
    {
        $mode = TransportMode::current();
        if ($mode === TransportMode::UNAVAILABLE || ($mode !== TransportMode::MOCK && !$secrets->isAvailable())) {
            return new self(self::MISSING_SECRET);
        }

        // Without a visitor-scope secret no allowance can be reserved. Report it as a
        // configuration fault rather than as a visitor having used their allowance.
        if (!(new \NicheCleverInteractive\Core\Budget\ClientScopeResolver())->isAvailable()) {
            return new self(self::MISSING_SCOPE_SECRET);
        }

        if ($profile->vectorStoreReference() === '') {
            return new self(self::MISSING_VECTOR_STORE);
        }

        if ($profile->corpusVersion() === '') {
            return new self(self::MISSING_CORPUS_VERSION);
        }

        $fingerprint = ConnectionFingerprint::forProfile($profile, $secrets, new \NicheCleverInteractive\Core\Security\PromptPolicy(), $mode);
        if ($testRecord['status'] !== 'passed') {
            return new self(self::MISSING_CONNECTION_TEST);
        }
        if ($fingerprint === null || !hash_equals($fingerprint, $testRecord['fingerprint'])) {
            return new self(self::STALE_CONNECTION_TEST);
        }

        return new self(self::READY);
    }

    public function state(): string
    {
        return $this->state;
    }

    public function isReady(): bool
    {
        return $this->state === self::READY;
    }
}
