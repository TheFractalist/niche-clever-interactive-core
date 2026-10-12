<?php

namespace NicheCleverInteractive\Core\Api\V1\Contracts;

use NicheCleverInteractive\Core\Api\V1\Value\ApprovedEvidenceV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;

interface EvidenceMetadataProviderV1
{
    /**
     * @param list<string> $annotationSourceRefs
     * @return array<string, ApprovedEvidenceV1>
     */
    public function approvedFor(ProfileIdV1 $profileId, array $annotationSourceRefs): array;
}
