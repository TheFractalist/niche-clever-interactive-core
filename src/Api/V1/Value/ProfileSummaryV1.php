<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

/**
 * One line of the Profiles page: who the profile is and whether it is switched on. Whether it is
 * ready to answer is decided by Core from its own connection-test record, never by the extension.
 */
final class ProfileSummaryV1
{
    private ProfileIdV1 $id;
    private string $label;
    private bool $enabled;
    private bool $protected;

    public function __construct(ProfileIdV1 $id, string $label, bool $enabled, bool $protected)
    {
        $label = trim($label);
        if ($label === '' || strlen($label) > 80 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $label) === 1) {
            throw new InvalidArgumentException('A profile needs a short plain-text label.');
        }

        $this->id = $id;
        $this->label = $label;
        $this->enabled = $enabled;
        $this->protected = $protected;
    }

    public function id(): ProfileIdV1
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /** Whether the profile limits who may use it. */
    public function isProtected(): bool
    {
        return $this->protected;
    }
}
