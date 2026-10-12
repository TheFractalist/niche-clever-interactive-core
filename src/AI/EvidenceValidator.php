<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI;

use NicheCleverInteractive\Core\AI\Transport\TransportResult;
use NicheCleverInteractive\Core\Api\V1\Value\ApprovedEvidenceV1;
use NicheCleverInteractive\Core\Api\V1\Value\ResolvedProfileV1;
use NicheCleverInteractive\Core\Configuration\CoreSettings;

final class EvidenceValidator
{
    /**
     * @return list<ApprovedEvidenceV1>|null
     */
    public function validate(TransportResult $result, ResolvedProfileV1 $profile, CoreSettings $settings): ?array
    {
        if (!$result->completedFileSearch()
            || !hash_equals($profile->policyFingerprint(), $result->policyFingerprint())
            || !hash_equals($profile->corpusFingerprint(), $result->corpusFingerprint())) {
            return null;
        }

        $approved = $settings->approvedEvidence();
        $tokens = $result->annotationTokens();
        if ($tokens === [] || count($tokens) > 20) {
            return null;
        }

        $evidence = [];
        foreach (array_values(array_unique($tokens)) as $token) {
            if (!is_string($token) || !isset($approved[$token])) {
                return null;
            }
            $record = $approved[$token];
            $evidence[] = new ApprovedEvidenceV1($record['title'], $record['url'], $profile->approvalMapRevision());
        }

        return $evidence;
    }
}
