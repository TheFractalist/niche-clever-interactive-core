<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

/**
 * Up to six optional #rrggbb colours for the question and answer blocks of a panel. Nothing here
 * is free-form CSS: a value is either a six-digit hexadecimal colour or absent.
 */
final class PanelColoursV1
{
    public const KEYS = [
        'question_background',
        'question_text',
        'question_border',
        'answer_background',
        'answer_text',
        'answer_border',
    ];

    /** @var array<string, string> */
    private array $colours;

    /**
     * @param array<string, string> $colours Any subset of KEYS mapped to #rrggbb.
     */
    public function __construct(array $colours)
    {
        $clean = [];
        foreach ($colours as $key => $value) {
            if (!is_string($key) || !in_array($key, self::KEYS, true) || !is_string($value) || preg_match('/^#[0-9a-fA-F]{6}$/D', $value) !== 1) {
                throw new InvalidArgumentException('Panel colours must be #rrggbb values for the six known blocks.');
            }
            $clean[$key] = strtolower($value);
        }

        $this->colours = $clean;
    }

    public function colour(string $key): ?string
    {
        return $this->colours[$key] ?? null;
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return $this->colours;
    }

    public function isEmpty(): bool
    {
        return $this->colours === [];
    }
}
