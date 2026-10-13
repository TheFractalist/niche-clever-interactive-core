<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Security;

final class PolicyFingerprint
{
    public static function create(PromptPolicy $policy, string $ownerInstructions, string $preset): string
    {
        $payload = [
            'effective_instructions' => $policy->effectiveInstructions($ownerInstructions, $preset),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
