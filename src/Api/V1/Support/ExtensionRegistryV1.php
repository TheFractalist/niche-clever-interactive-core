<?php

namespace NicheCleverInteractive\Core\Api\V1\Support;

use InvalidArgumentException;
use LogicException;
use NicheCleverInteractive\Core\Api\V1\Contracts\AnswerCacheV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ChronologyProviderV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\CompletedTurnRecorderV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ProfileResolverV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\WorkspaceExtensionV1;

final class ExtensionRegistryV1
{
    /**
     * @var array<string, class-string>
     */
    private const CONTRACTS = [
        'profile_resolution' => ProfileResolverV1::class,
        'supplementary_chronology' => ChronologyProviderV1::class,
        'eligible_answer_cache' => AnswerCacheV1::class,
        'completed_public_turn' => CompletedTurnRecorderV1::class,
        'workspace_extension' => WorkspaceExtensionV1::class,
    ];

    /**
     * @var array<string, object>
     */
    private array $implementations = [];

    /**
     * @return list<string>
     */
    public function knownSeams(): array
    {
        return array_keys(self::CONTRACTS);
    }

    public function register(string $seam, object $implementation): void
    {
        if (!isset(self::CONTRACTS[$seam])) {
            throw new InvalidArgumentException('Unknown Core API seam.');
        }

        if (isset($this->implementations[$seam])) {
            throw new LogicException('A Core API seam may be registered only once.');
        }

        $contract = self::CONTRACTS[$seam];
        if (!$implementation instanceof $contract) {
            throw new InvalidArgumentException('Implementation does not satisfy the seam contract.');
        }

        $this->implementations[$seam] = $implementation;
    }

    public function has(string $seam): bool
    {
        return isset($this->implementations[$seam]);
    }
}
