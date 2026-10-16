<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

/**
 * Whether the current viewer may use a profile: open (no restriction), granted (restricted but
 * allowed) or denied, with an optional bounded plain-text message Core may show the viewer.
 */
final class AccessDecisionV1
{
    public const MAX_MESSAGE = 300;

    private const OPEN = 'open';
    private const GRANTED = 'granted';
    private const DENIED = 'denied';

    private string $state;
    private ?string $message;

    private function __construct(string $state, ?string $message)
    {
        $this->state = $state;
        $this->message = $message;
    }

    /** The profile has no access restriction. */
    public static function open(): self
    {
        return new self(self::OPEN, null);
    }

    /** The profile is restricted and this viewer has been granted access. */
    public static function granted(): self
    {
        return new self(self::GRANTED, null);
    }

    /** The viewer may not use the profile. A null or blank message means Core's own wording. */
    public static function denied(?string $message = null): self
    {
        if ($message !== null) {
            $message = trim($message);
            if (strlen($message) > self::MAX_MESSAGE || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $message) === 1) {
                throw new InvalidArgumentException('An access message must be short plain text.');
            }
            if ($message === '') {
                $message = null;
            }
        }

        return new self(self::DENIED, $message);
    }

    public function isAllowed(): bool
    {
        return $this->state !== self::DENIED;
    }

    /** Whether the profile is access-controlled at all; restricted pages must not be cached. */
    public function isRestricted(): bool
    {
        return $this->state !== self::OPEN;
    }

    public function message(): ?string
    {
        return $this->message;
    }
}
