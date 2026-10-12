<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

final class CacheKeyV1
{
    private string $questionDigest;
    private ProfileIdV1 $profileId;
    private string $policyFingerprint;
    private string $corpusFingerprint;
    private string $accessStateFingerprint;
    private string $responsePreset;

    public function __construct(
        string $questionDigest,
        ProfileIdV1 $profileId,
        string $policyFingerprint,
        string $corpusFingerprint,
        string $accessStateFingerprint,
        string $responsePreset
    ) {
        if (preg_match('/^[a-f0-9]{64}$/', $questionDigest) !== 1) {
            throw new InvalidArgumentException('Question digest must be a SHA-256 hexadecimal digest.');
        }

        foreach ([$policyFingerprint, $corpusFingerprint, $accessStateFingerprint, $responsePreset] as $value) {
            if ($value === '') {
                throw new InvalidArgumentException('Cache key fingerprints and preset must be explicit.');
            }
        }

        $this->questionDigest = $questionDigest;
        $this->profileId = $profileId;
        $this->policyFingerprint = $policyFingerprint;
        $this->corpusFingerprint = $corpusFingerprint;
        $this->accessStateFingerprint = $accessStateFingerprint;
        $this->responsePreset = $responsePreset;
    }

    public function identity(): string
    {
        return hash(
            'sha256',
            implode(
                "\n",
                [
                    $this->questionDigest,
                    $this->profileId->value(),
                    $this->policyFingerprint,
                    $this->corpusFingerprint,
                    $this->accessStateFingerprint,
                    $this->responsePreset,
                ]
            )
        );
    }
}
