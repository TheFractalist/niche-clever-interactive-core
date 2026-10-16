<?php

namespace NicheCleverInteractive\Core\Api\V1\Contracts;

use NicheCleverInteractive\Core\Api\V1\Value\ProfileEditV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileSummaryV1;

/**
 * Lets Core show, create, edit and delete the profiles an extension stores, in Core's own
 * Profiles page and profile workspace. Core renders every form and validates every value
 * (ProfileEditV1) before it calls this contract; the extension only stores. The default profile
 * is Core's and never passes through here. Any failure, wrong type or invalid value is treated by
 * Core as "this profile is not editable right now", never as a reason to touch the default profile.
 */
interface ProfileEditorV1
{
    /** @return list<ProfileSummaryV1> In the order they should be listed. */
    public function summaries(): array;

    /** The stored settings of a profile (without its vector-store reference), or null if it does not exist. */
    public function values(ProfileIdV1 $id): ?ProfileEditV1;

    /**
     * @throws \LogicException when the id is taken or the extension's profile limit is reached
     */
    public function create(ProfileIdV1 $id, ProfileEditV1 $edit): void;

    /** @throws \LogicException when the profile does not exist */
    public function save(ProfileIdV1 $id, ProfileEditV1 $edit): void;

    /** Whether a profile was removed. The extension removes the data it keeps for that profile. */
    public function delete(ProfileIdV1 $id): bool;

    /** The most profiles the extension can store. */
    public function limit(): int;
}
