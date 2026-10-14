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
    private const PANEL_STYLES = ['classic', 'minimal', 'dark'];

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
            'presentation' => [
                'title' => 'Ask a question',
                'headline' => 'Explore the material behind this site.',
                'introduction' => '',
                'placeholder' => 'What would you like to know?',
                'footer_left' => 'PREPARED KNOWLEDGE',
                'footer_right' => 'GROUNDED ANSWERS',
            ],
            'diagnostics' => [
                'connection_test' => [
                    'status' => 'never',
                    'tested_at' => '',
                    'fingerprint' => '',
                ],
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
        $presentation = is_array($input['presentation'] ?? null) ? $input['presentation'] : [];
        $diagnostics = is_array($input['diagnostics'] ?? null) ? $input['diagnostics'] : [];
        $connectionTest = is_array($diagnostics['connection_test'] ?? null) ? $diagnostics['connection_test'] : [];

        $id = self::boundedString($profile['id'] ?? self::DEFAULT_PROFILE_ID, 64);
        if ($id !== self::DEFAULT_PROFILE_ID) {
            throw new InvalidArgumentException('Core supports only its default profile.');
        }

        $preset = self::boundedString($input['response_preset'] ?? $defaults['response_preset'], 16);
        if (!in_array($preset, self::PRESETS, true)) {
            throw new InvalidArgumentException('Unknown Core response preset.');
        }

        $style = self::boundedString($appearance['panel_style'] ?? $defaults['appearance']['panel_style'], 16);
        if ($style === 'editorial') {
            $style = 'dark';
        }
        if (!in_array($style, self::PANEL_STYLES, true)) {
            throw new InvalidArgumentException('Unknown Core panel style.');
        }

        return new self([
            'enabled' => self::boolValue($input['enabled'] ?? $defaults['enabled']),
            'profile' => [
                'id' => $id,
                'corpus_version' => self::boundedString($profile['corpus_version'] ?? '', 128),
                'vector_store_reference' => self::boundedString($profile['vector_store_reference'] ?? '', 256),
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
            'presentation' => [
                'title' => self::boundedString($presentation['title'] ?? $defaults['presentation']['title'], 120),
                'headline' => self::boundedString($presentation['headline'] ?? $defaults['presentation']['headline'], 180),
                'introduction' => self::boundedString($presentation['introduction'] ?? '', 500),
                'placeholder' => self::boundedString($presentation['placeholder'] ?? $defaults['presentation']['placeholder'], 160),
                'footer_left' => self::boundedString($presentation['footer_left'] ?? $defaults['presentation']['footer_left'], 120),
                'footer_right' => self::boundedString($presentation['footer_right'] ?? $defaults['presentation']['footer_right'], 120),
            ],
            'diagnostics' => [
                'connection_test' => self::connectionTest($connectionTest),
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

    public function responsePreset(): string
    {
        return $this->values['response_preset'];
    }

    public function visitorRequestsPerDay(): int
    {
        return $this->values['limits']['visitor_requests_per_day'];
    }

    public function providerRequestsPerDay(): int
    {
        return $this->values['limits']['provider_requests_per_day'];
    }

    public function instructions(): string
    {
        return $this->values['instructions'];
    }

    public function connectionTestStatus(): string
    {
        return $this->values['diagnostics']['connection_test']['status'];
    }

    public function connectionTestTime(): string
    {
        return $this->values['diagnostics']['connection_test']['tested_at'];
    }

    public function connectionTestFingerprint(): string
    {
        return $this->values['diagnostics']['connection_test']['fingerprint'];
    }

    public function withConnectionTest(string $status, string $fingerprint, string $testedAt): self
    {
        $values = $this->values;
        $values['diagnostics']['connection_test'] = [
            'status' => $status,
            'tested_at' => $testedAt,
            'fingerprint' => $fingerprint,
        ];

        return self::fromArray($values);
    }

    /**
     * @return array{title: string, headline: string, introduction: string, placeholder: string, footer_left: string, footer_right: string}
     */
    public function presentation(): array
    {
        return $this->values['presentation'];
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
     * @param array<string, mixed> $connectionTest
     * @return array{status: string, tested_at: string, fingerprint: string}
     */
    private static function connectionTest(array $connectionTest): array
    {
        $status = self::boundedString($connectionTest['status'] ?? 'never', 16);
        if (!in_array($status, ['never', 'passed', 'failed'], true)) {
            throw new InvalidArgumentException('The Core connection-test status is malformed.');
        }

        $testedAt = self::boundedString($connectionTest['tested_at'] ?? '', 32);
        $fingerprint = self::boundedString($connectionTest['fingerprint'] ?? '', 64);
        if ($status === 'never' && ($testedAt !== '' || $fingerprint !== '')) {
            throw new InvalidArgumentException('The Core connection-test record is malformed.');
        }
        if ($status !== 'never' && (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $testedAt) !== 1 || preg_match('/^[a-f0-9]{64}$/', $fingerprint) !== 1)) {
            throw new InvalidArgumentException('The Core connection-test record is malformed.');
        }

        return ['status' => $status, 'tested_at' => $testedAt, 'fingerprint' => $fingerprint];
    }

}
