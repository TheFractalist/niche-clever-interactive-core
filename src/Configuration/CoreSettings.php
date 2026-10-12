<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Configuration;

use InvalidArgumentException;

final class CoreSettings
{
    public const OPTION_NAME = 'ncli_core_settings';
    public const SCHEMA_OPTION = 'ncli_core_schema_version';
    public const SCHEMA_VERSION = 1;
    public const DEFAULT_PROFILE_ID = 'default';

    private const PRESETS = ['concise', 'balanced', 'detailed'];
    private const PANEL_STYLES = ['classic', 'minimal', 'editorial'];

    /**
     * @var array<string, mixed>
     */
    private array $values;

    /**
     * @param array<string, mixed> $values
     */
    private function __construct(array $values)
    {
        $this->values = $values;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'profile' => [
                'id' => self::DEFAULT_PROFILE_ID,
                'corpus_version' => '',
                'vector_store_reference' => '',
                'approved_evidence' => [],
            ],
            'instructions' => '',
            'response_preset' => 'balanced',
            'limits' => [
                'visitor_requests_per_day' => 10,
                'provider_requests_per_day' => 100,
            ],
            'appearance' => [
                'panel_style' => 'classic',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromArray(array $input): self
    {
        $defaults = self::defaults();
        $profile = is_array($input['profile'] ?? null) ? $input['profile'] : [];
        $limits = is_array($input['limits'] ?? null) ? $input['limits'] : [];
        $appearance = is_array($input['appearance'] ?? null) ? $input['appearance'] : [];

        $id = self::boundedString($profile['id'] ?? self::DEFAULT_PROFILE_ID, 64);
        if ($id !== self::DEFAULT_PROFILE_ID) {
            throw new InvalidArgumentException('Core supports only its default profile.');
        }

        $preset = self::boundedString($input['response_preset'] ?? $defaults['response_preset'], 16);
        if (!in_array($preset, self::PRESETS, true)) {
            throw new InvalidArgumentException('Unknown Core response preset.');
        }

        $style = self::boundedString($appearance['panel_style'] ?? $defaults['appearance']['panel_style'], 16);
        if (!in_array($style, self::PANEL_STYLES, true)) {
            throw new InvalidArgumentException('Unknown Core panel style.');
        }

        $evidence = self::normaliseEvidenceMap($profile['approved_evidence'] ?? []);

        return new self([
            'enabled' => self::boolValue($input['enabled'] ?? $defaults['enabled']),
            'profile' => [
                'id' => $id,
                'corpus_version' => self::boundedString($profile['corpus_version'] ?? '', 128),
                'vector_store_reference' => self::boundedString($profile['vector_store_reference'] ?? '', 256),
                'approved_evidence' => $evidence,
            ],
            'instructions' => self::boundedString($input['instructions'] ?? '', 4000),
            'response_preset' => $preset,
            'limits' => [
                'visitor_requests_per_day' => self::positiveInt($limits['visitor_requests_per_day'] ?? $defaults['limits']['visitor_requests_per_day'], 1, 10000),
                'provider_requests_per_day' => self::positiveInt($limits['provider_requests_per_day'] ?? $defaults['limits']['provider_requests_per_day'], 1, 100000),
            ],
            'appearance' => [
                'panel_style' => $style,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function isEnabled(): bool
    {
        return $this->values['enabled'];
    }

    public function vectorStoreReference(): string
    {
        return $this->values['profile']['vector_store_reference'];
    }

    public function corpusVersion(): string
    {
        return $this->values['profile']['corpus_version'];
    }

    /**
     * @return array<string, array{title: string, url: string}>
     */
    public function approvedEvidence(): array
    {
        return $this->values['profile']['approved_evidence'];
    }

    public function responsePreset(): string
    {
        return $this->values['response_preset'];
    }

    public function instructions(): string
    {
        return $this->values['instructions'];
    }

    /**
     * @param mixed $value
     */
    private static function boundedString($value, int $maximum): string
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

    /**
     * @param mixed $value
     */
    private static function boolValue($value): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException('A Core boolean setting is malformed.');
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    private static function positiveInt($value, int $minimum, int $maximum): int
    {
        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException('A Core numeric setting is malformed.');
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return array<string, array{title: string, url: string}>
     */
    private static function normaliseEvidenceMap($value): array
    {
        if (!is_array($value) || count($value) > 100) {
            throw new InvalidArgumentException('The Core evidence map is malformed.');
        }

        $normalised = [];
        foreach ($value as $annotation => $record) {
            if (!is_string($annotation) || preg_match('/^[A-Za-z0-9_-]{1,128}$/', $annotation) !== 1 || !is_array($record)) {
                throw new InvalidArgumentException('The Core evidence map is malformed.');
            }

            $title = self::boundedString($record['title'] ?? '', 160);
            $url = self::boundedString($record['url'] ?? '', 2048);
            if ($title === '' || filter_var($url, FILTER_VALIDATE_URL) === false || strncmp($url, 'https://', 8) !== 0 || self::hasCredential($url)) {
                throw new InvalidArgumentException('The Core evidence map has an invalid public link.');
            }

            $normalised[$annotation] = ['title' => $title, 'url' => $url];
        }

        ksort($normalised);
        return $normalised;
    }

    private static function hasCredential(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts) && (isset($parts['user']) || isset($parts['pass']));
    }
}
