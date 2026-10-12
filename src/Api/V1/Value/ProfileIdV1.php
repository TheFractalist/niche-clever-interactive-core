<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

final class ProfileIdV1
{
    private string $value;

    public function __construct(string $value)
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $value) !== 1) {
            throw new InvalidArgumentException('Profile IDs must be explicit, lower-case and bounded.');
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }
}
