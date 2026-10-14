<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Configuration;

/**
 * Curated, owner-selectable provider models. This is the single source for
 * validation, the administrator dropdown/help table and the transport. It
 * carries qualitative guidance only; no prices are stored.
 */
final class ModelCatalogue
{
    public const DEFAULT_MODEL = 'gpt-6-astra';
    public const DEFAULT_EFFORT = 'default';

    public const RETRIEVAL_DEPTHS = [3, 5, 8, 10];
    public const TIMEOUT_SECONDS = [30, 60, 90, 120];
    public const DEFAULT_RETRIEVAL_DEPTH = 5;
    public const DEFAULT_TIMEOUT_SECONDS = 60;
    public const MIN_OUTPUT_TOKENS = 1000;
    public const MAX_OUTPUT_TOKENS = 8000;
    public const DEFAULT_OUTPUT_TOKENS = 4000;

    /**
     * Ordered Economy, Balanced, Premium, then previous default.
     *
     * @return array<string, array{id: string, label: string, tier: string, best_for: string, cost_note: string, efforts: list<string>}>
     */
    public static function all(): array
    {
        return [
            'gpt-6-luna' => [
                'id' => 'gpt-6-luna',
                'label' => 'GPT-6 Luna',
                'tier' => 'Economy',
                'best_for' => 'High-volume, straightforward questions',
                'cost_note' => 'Lowest cost',
                'efforts' => ['none', 'low', 'medium', 'high', 'xhigh', 'max'],
            ],
            'gpt-6.1-sol' => [
                'id' => 'gpt-6.1-sol',
                'label' => 'GPT-6.1 Sol',
                'tier' => 'Balanced',
                'best_for' => 'General questions about a knowledge base',
                'cost_note' => 'Middle cost',
                'efforts' => ['low', 'medium', 'high', 'xhigh', 'max'],
            ],
            'gpt-6-astra' => [
                'id' => 'gpt-6-astra',
                'label' => 'GPT-6 Astra',
                'tier' => 'Premium',
                'best_for' => 'Nuanced or high-stakes answers',
                'cost_note' => 'Highest cost',
                'efforts' => ['low', 'medium', 'high', 'xhigh', 'max'],
            ],
            'gpt-5.6' => [
                'id' => 'gpt-5.6',
                'label' => 'GPT-5.6',
                'tier' => 'Previous default',
                'best_for' => 'Sites already tuned on this model',
                'cost_note' => 'Not on the provider\'s current model list; check provider pricing',
                'efforts' => [],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $id): bool
    {
        return isset(self::all()[$id]);
    }

    /**
     * @return list<string>
     */
    public static function effortsFor(string $id): array
    {
        return self::all()[$id]['efforts'] ?? [];
    }

    public static function supportsReasoning(string $id): bool
    {
        return self::effortsFor($id) !== [];
    }

    public static function validEffort(string $id, string $effort): bool
    {
        return $effort === self::DEFAULT_EFFORT || in_array($effort, self::effortsFor($id), true);
    }
}
