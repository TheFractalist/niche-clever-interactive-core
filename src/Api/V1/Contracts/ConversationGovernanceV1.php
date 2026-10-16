<?php

namespace NicheCleverInteractive\Core\Api\V1\Contracts;

use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;

interface ConversationGovernanceV1
{
    /**
     * Called once per request after extensions are registered, so the extension can offer export and
     * erasure screens. The archive enforces the administrator capability itself.
     */
    public function attach(ConversationArchiveV1 $archive): void;

    /**
     * Whether answers for this profile are recorded in Core's history: true or false overrides
     * Core's own setting for that profile, null leaves Core's setting in charge. An exception or a
     * non-boolean result also leaves Core's setting in charge.
     */
    public function recordingFor(ProfileIdV1 $profileId): ?bool;

    /**
     * Retention for profiles whose schedule differs from Core's, as profile id => days: 30, 90, 365,
     * or 0 to keep until the owner deletes them. Core applies its own schedule to every profile not
     * listed, and to all profiles if this throws or returns anything malformed (never "keep forever").
     *
     * @return array<string, int>
     */
    public function retentionOverrides(): array;
}
