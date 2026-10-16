<?php

namespace NicheCleverInteractive\Core\Api\V1\Contracts;

use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileSectionV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceContextV1;

/**
 * Lets an extension add blocks to a profile's tabs in Core's workspace. Core draws the page, the
 * tab bar and the panel, checks the administrator capability and issues the nonce (action
 * `ncli_ext_<section key>`); the extension supplies the section list and each body. A failing or
 * invalid extension loses its sections and one body is replaced by a generic notice; the rest of
 * the workspace is unaffected. It applies to the default profile too: Core's own settings stay
 * Core's, and the extension's sections appear beneath them.
 */
interface ProfileWorkspaceV1
{
    /** @return list<ProfileSectionV1> The sections to show for this profile, in order within each tab. */
    public function sections(ProfileIdV1 $id): array;

    /** Echoes one section's body for this profile. */
    public function render(string $sectionKey, ProfileIdV1 $id, WorkspaceContextV1 $context): void;
}
