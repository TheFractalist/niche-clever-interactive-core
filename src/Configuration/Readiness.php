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

        // The mock transport is an explicit, server-side disposable-fixture mode.
        // It never accepts a credential or a vector-store reference and cannot be
        // enabled from a setting or request.
        if (defined('NCLI_CORE_MOCK_TRANSPORT') && NCLI_CORE_MOCK_TRANSPORT === true) {
            return $settings->corpusVersion() === ''
                ? new self(self::MISSING_CORPUS_VERSION)
                : new self(self::READY);
        }

        if (!$secrets->isAvailable()) {
            return new self(self::MISSING_SECRET);
        }

        if ($settings->vectorStoreReference() === '') {
            return new self(self::MISSING_VECTOR_STORE);
        }

        if ($settings->corpusVersion() === '') {
            return new self(self::MISSING_CORPUS_VERSION);
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
