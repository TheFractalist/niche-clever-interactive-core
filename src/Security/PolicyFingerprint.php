<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Security;

final class PolicyFingerprint
{
    public static function create(PromptPolicy $policy, string $ownerInstructions, string $preset): string
    {
        $payload = [
            'fixed' => $policy->fixedInstructions(),
            'owner' => $ownerInstructions,
            'preset' => $preset,
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
