<?php

namespace NicheCleverInteractive\Core\Api\V1\Contracts;

use NicheCleverInteractive\Core\Api\V1\Value\ApprovedLinkV1;
use NicheCleverInteractive\Core\Api\V1\Value\EvidenceCitationV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;

interface EvidenceCitationsV1
{
    /**
     * Given the bounded descriptors of the sources a validated answer cited (at most 10, no text),
     * return the links the site owner has approved to show beneath that answer. Core accepts only
     * valid ApprovedLinkV1 values, keeps at most 5 with distinct addresses, and treats an exception,
     * a non-list or any wrong entry as no links. Nothing returned here can alter the answer.
     *
     * @param list<EvidenceCitationV1> $citations
     * @return list<ApprovedLinkV1>
     */
    public function approvedLinks(ProfileIdV1 $profileId, array $citations): array;
}
