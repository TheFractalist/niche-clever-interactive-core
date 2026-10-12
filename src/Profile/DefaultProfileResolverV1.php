<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Profile;

use NicheCleverInteractive\Core\Api\V1\Contracts\ProfileResolverV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\ResolvedProfileV1;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Security\PolicyFingerprint;
use NicheCleverInteractive\Core\Security\PromptPolicy;

final class DefaultProfileResolverV1 implements ProfileResolverV1
{
    private CoreSettings $settings;
    private PromptPolicy $policy;

    public function __construct(CoreSettings $settings, PromptPolicy $policy)
    {
        $this->settings = $settings;
        $this->policy = $policy;
    }

    public function resolve(ProfileIdV1 $profileId): ?ResolvedProfileV1
    {
        if ($profileId->value() !== CoreSettings::DEFAULT_PROFILE_ID || !$this->settings->isEnabled()) {
            return null;
        }

        return new ResolvedProfileV1(
            $profileId,
            true,
            PolicyFingerprint::create($this->policy, $this->settings->instructions(), $this->settings->responsePreset()),
            hash('sha256', $this->settings->corpusVersion()),
            hash('sha256', json_encode($this->settings->approvedEvidence(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            $this->settings->responsePreset()
        );
    }
}
