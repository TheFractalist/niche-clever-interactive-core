<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Configuration;

use InvalidArgumentException;

/**
 * The single definition of a valid model block (model, reasoning effort, retrieval depth, answer
 * length, timeout), used for Core's own settings and for any profile an extension supplies.
 */
final class ModelSettings
{
    public const KEYS = ['id', 'reasoning_effort', 'retrieval_depth', 'max_output_tokens', 'timeout_seconds'];

    /**
     * @param array<string, mixed> $input Any subset of the keys; a missing key takes its default.
     * @param array<string, mixed> $defaults A complete valid block.
     * @return array{id: string, reasoning_effort: string, retrieval_depth: int, max_output_tokens: int, timeout_seconds: int}
     * @throws InvalidArgumentException
     */
    public static function validate(array $input, array $defaults): array
    {
        $id = self::text($input['id'] ?? $defaults['id'], 128);
        if (!ModelCatalogue::has($id)) {
            throw new InvalidArgumentException('Unknown Core model.');
        }

        $effort = self::text($input['reasoning_effort'] ?? $defaults['reasoning_effort'], 16);
        if (!ModelCatalogue::validEffort($id, $effort)) {
            throw new InvalidArgumentException('Unknown Core reasoning effort for the selected model.');
        }

        $depth = self::whole($input['retrieval_depth'] ?? $defaults['retrieval_depth'], 1, 50);
        if (!in_array($depth, ModelCatalogue::RETRIEVAL_DEPTHS, true)) {
            throw new InvalidArgumentException('Unknown Core retrieval depth.');
        }

        $timeout = self::whole($input['timeout_seconds'] ?? $defaults['timeout_seconds'], 1, 600);
        if (!in_array($timeout, ModelCatalogue::TIMEOUT_SECONDS, true)) {
            throw new InvalidArgumentException('Unknown Core timeout.');
        }

        return [
            'id' => $id,
            'reasoning_effort' => $effort,
            'retrieval_depth' => $depth,
            'max_output_tokens' => self::whole($input['max_output_tokens'] ?? $defaults['max_output_tokens'], ModelCatalogue::MIN_OUTPUT_TOKENS, ModelCatalogue::MAX_OUTPUT_TOKENS),
            'timeout_seconds' => $timeout,
        ];
    }

    /** @param mixed $value */
    private static function text($value, int $maximum): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('A Core setting must be a string.');
        }

        $value = trim($value);
        if (strlen($value) > $maximum || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) === 1) {
            throw new InvalidArgumentException('A Core setting is malformed.');
        }

        return $value;
    }

    /** @param mixed $value */
    private static function whole($value, int $minimum, int $maximum): int
    {
        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException('A Core numeric setting is malformed.');
        }

        return $value;
    }
}
