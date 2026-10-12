<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Configuration;

final class Readiness
{
    public const READY = 'ready';
    public const DISABLED = 'disabled';
    public const MISSING_SECRET = 'missing_secret';
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

        $mode = TransportMode::current();
        if ($mode === TransportMode::UNAVAILABLE || ($mode !== TransportMode::MOCK && !$secrets->isAvailable())) {
            return new self(self::MISSING_SECRET);
        }

        if ($settings->vectorStoreReference() === '') {
            return new self(self::MISSING_VECTOR_STORE);
        }

        if ($settings->corpusVersion() === '') {
            return new self(self::MISSING_CORPUS_VERSION);
        }

        $fingerprint = ConnectionFingerprint::forSettings($settings, $secrets, new \NicheCleverInteractive\Core\Security\PromptPolicy(), $mode);
        if ($settings->connectionTestStatus() !== 'passed') {
            return new self(self::MISSING_CONNECTION_TEST);
        }
        if ($fingerprint === null || !hash_equals($fingerprint, $settings->connectionTestFingerprint())) {
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
