<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Security;

final class PolicyFingerprint
{
    /** @param list<\NicheCleverInteractive\Core\Api\V1\Value\ChronologyFactV1> $chronology */
    public static function create(PromptPolicy $policy, string $ownerInstructions, string $preset, array $chronology = [], bool $earlierTurns = false): string
    {
        $payload = [
            'effective_instructions' => $policy->effectiveInstructions($ownerInstructions, $preset, $chronology, $earlierTurns),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
