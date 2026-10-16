<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Configuration;

use NicheCleverInteractive\Core\Profile\ProfileRuntime;
use NicheCleverInteractive\Core\Security\PolicyFingerprint;
use NicheCleverInteractive\Core\Security\PromptPolicy;

final class ConnectionFingerprint
{
    public static function forSettings(CoreSettings $settings, SecretResolver $secrets, PromptPolicy $policy, string $mode): ?string
    {
        return self::forProfile(ProfileRuntime::fromSettings($settings), $secrets, $policy, $mode);
    }

    public static function forProfile(ProfileRuntime $profile, SecretResolver $secrets, PromptPolicy $policy, string $mode): ?string
    {
        $key = $mode === TransportMode::MOCK ? 'ncli-core-mock-connection-test' : $secrets->forProvider();
        if ($key === null || $mode === TransportMode::UNAVAILABLE) {
            return null;
        }

        $payload = [
            'mode' => $mode,
            'vector_store' => $profile->vectorStoreReference(),
            'corpus_version' => $profile->corpusVersion(),
            'policy' => PolicyFingerprint::create($policy, $profile->instructions(), $profile->responsePreset()),
            'model' => [
                'id' => $profile->options()->model(),
                'reasoning_effort' => $profile->options()->reasoningEffort(),
                'retrieval_depth' => $profile->options()->retrievalDepth(),
                'max_output_tokens' => $profile->options()->maxOutputTokens(),
                'timeout_seconds' => $profile->options()->timeoutSeconds(),
            ],
            'core_version' => defined('NCLI_CORE_VERSION') ? NCLI_CORE_VERSION : 'development',
        ];

        return hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $key);
    }
}
