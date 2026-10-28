<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Configuration;

use InvalidArgumentException;

final class CoreSettings
{
    public const OPTION_NAME = 'ncli_core_settings';
    public const SCHEMA_OPTION = 'ncli_core_schema_version';
    public const SCHEMA_VERSION = 2;
    public const DEFAULT_UNAVAILABLE_MESSAGE = 'This interactive service is not available yet.';
    public const DEFAULT_PROCESSING_MESSAGE = 'Receiving response…';
    public const DEFAULT_RETENTION_DAYS = 90;
    public const DEFAULT_PROFILE_ID = 'default';

    private const PRESETS = ['concise', 'balanced', 'detailed'];
    private const PANEL_STYLES = ['classic', 'minimal', 'dark'];
    private const RETENTION_CHOICES = [0, 30, 90, 365];

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
            'model' => [
                'id' => ModelCatalogue::DEFAULT_MODEL,
                'reasoning_effort' => ModelCatalogue::DEFAULT_EFFORT,
                'retrieval_depth' => ModelCatalogue::DEFAULT_RETRIEVAL_DEPTH,
                'max_output_tokens' => ModelCatalogue::DEFAULT_OUTPUT_TOKENS,
                'timeout_seconds' => ModelCatalogue::DEFAULT_TIMEOUT_SECONDS,
            ],
            'limits' => [
                'visitor_requests_per_day' => 10,
                'provider_requests_per_day' => 100,
            ],
            'appearance' => [
                'panel_style' => 'classic',
                'panel_width' => 'standard',
                'accent_color' => '',
                'show_credit' => false,
            ],
            'conversation_log' => [
                'enabled' => false,
                'retention_days' => self::DEFAULT_RETENTION_DAYS,
            ],
            'streaming' => [
                'progressive' => true,
            ],
            'lifecycle' => [
                'delete_on_uninstall' => false,
            ],
            'presentation' => [
                'title' => 'Ask a question',
                'headline' => 'Explore the material behind this site.',
                'introduction' => '',
                'placeholder' => 'What would you like to know?',
                'footer_left' => 'PREPARED KNOWLEDGE',
                'footer_right' => 'GROUNDED ANSWERS',
                'unavailable_message' => self::DEFAULT_UNAVAILABLE_MESSAGE,
                'processing_message' => self::DEFAULT_PROCESSING_MESSAGE,
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
        $model = is_array($input['model'] ?? null) ? $input['model'] : [];
        $limits = is_array($input['limits'] ?? null) ? $input['limits'] : [];
        $appearance = is_array($input['appearance'] ?? null) ? $input['appearance'] : [];
        $presentation = is_array($input['presentation'] ?? null) ? $input['presentation'] : [];
        $conversationLog = is_array($input['conversation_log'] ?? null) ? $input['conversation_log'] : [];
        $streaming = is_array($input['streaming'] ?? null) ? $input['streaming'] : [];
        $lifecycle = is_array($input['lifecycle'] ?? null) ? $input['lifecycle'] : [];
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

        $modelBlock = ModelSettings::validate($model, $defaults['model']);

        $style = self::boundedString($appearance['panel_style'] ?? $defaults['appearance']['panel_style'], 16);
        if ($style === 'editorial') {
            $style = 'dark';
        }
        if (!in_array($style, self::PANEL_STYLES, true)) {
            throw new InvalidArgumentException('Unknown Core panel style.');
        }

        $width = self::boundedString($appearance['panel_width'] ?? $defaults['appearance']['panel_width'], 16);
        if (!isset(PanelAppearance::WIDTHS[$width])) {
            throw new InvalidArgumentException('Unknown Core panel width.');
        }

        $accent = strtolower(self::boundedString($appearance['accent_color'] ?? '', 7));
        if ($accent !== '' && !PanelAppearance::isHexColour($accent)) {
            throw new InvalidArgumentException('The Core accent colour must be a six-digit hexadecimal colour.');
        }

        $retention = $conversationLog['retention_days'] ?? $defaults['conversation_log']['retention_days'];
        if (!is_int($retention) || !in_array($retention, self::RETENTION_CHOICES, true)) {
            throw new InvalidArgumentException('Unknown Core conversation retention period.');
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
            'model' => $modelBlock,
            'limits' => [
                'visitor_requests_per_day' => self::positiveInt($limits['visitor_requests_per_day'] ?? $defaults['limits']['visitor_requests_per_day'], 1, 10000),
                'provider_requests_per_day' => self::positiveInt($limits['provider_requests_per_day'] ?? $defaults['limits']['provider_requests_per_day'], 1, 100000),
            ],
            'appearance' => [
                'panel_style' => $style,
                'panel_width' => $width,
                'accent_color' => $accent,
                'show_credit' => self::boolValue($appearance['show_credit'] ?? $defaults['appearance']['show_credit']),
            ],
            'conversation_log' => [
                'enabled' => self::boolValue($conversationLog['enabled'] ?? $defaults['conversation_log']['enabled']),
                'retention_days' => $retention,
            ],
            'streaming' => [
                'progressive' => self::boolValue($streaming['progressive'] ?? $defaults['streaming']['progressive']),
            ],
            'lifecycle' => [
                'delete_on_uninstall' => self::boolValue($lifecycle['delete_on_uninstall'] ?? $defaults['lifecycle']['delete_on_uninstall']),
            ],
            'presentation' => [
                'title' => self::boundedString($presentation['title'] ?? $defaults['presentation']['title'], 120),
                'headline' => self::boundedString($presentation['headline'] ?? $defaults['presentation']['headline'], 180),
                'introduction' => self::boundedString($presentation['introduction'] ?? '', 500),
                'placeholder' => self::boundedString($presentation['placeholder'] ?? $defaults['presentation']['placeholder'], 160),
                'footer_left' => self::boundedString($presentation['footer_left'] ?? $defaults['presentation']['footer_left'], 120),
                'footer_right' => self::boundedString($presentation['footer_right'] ?? $defaults['presentation']['footer_right'], 120),
                'unavailable_message' => self::boundedString($presentation['unavailable_message'] ?? $defaults['presentation']['unavailable_message'], 240),
                'processing_message' => self::boundedString($presentation['processing_message'] ?? $defaults['presentation']['processing_message'], 160),
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

    /**
     * @return array{id: string, reasoning_effort: string, retrieval_depth: int, max_output_tokens: int, timeout_seconds: int}
     */
    public function model(): array
    {
        return $this->values['model'];
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
     * @return array{title: string, headline: string, introduction: string, placeholder: string, footer_left: string, footer_right: string, unavailable_message: string, processing_message: string}
     */
    public function presentation(): array
    {
        return $this->values['presentation'];
    }

    public function panelStyle(): string
    {
        return $this->values['appearance']['panel_style'];
    }

    public function panelWidth(): string
    {
        return $this->values['appearance']['panel_width'];
    }

    public function accentColor(): string
    {
        return $this->values['appearance']['accent_color'];
    }

    public function showCredit(): bool
    {
        return $this->values['appearance']['show_credit'];
    }

    public function conversationLogEnabled(): bool
    {
        return $this->values['conversation_log']['enabled'];
    }

    /** Whether compatible panels should request a progressive event stream. */
    public function progressiveStreaming(): bool
    {
        return $this->values['streaming']['progressive'];
    }

    /** Zero means keep indefinitely. */
    public function conversationRetentionDays(): int
    {
        return $this->values['conversation_log']['retention_days'];
    }

    public function deleteOnUninstall(): bool
    {
        return $this->values['lifecycle']['delete_on_uninstall'];
    }

    /** The configured unavailable message, or the default when it was saved blank. */
    public function unavailableMessage(): string
    {
        $message = $this->values['presentation']['unavailable_message'];

        return $message !== '' ? $message : self::DEFAULT_UNAVAILABLE_MESSAGE;
    }

    /** The configured processing message, or the default when it was saved blank. */
    public function processingMessage(): string
    {
        $message = $this->values['presentation']['processing_message'];

        return $message !== '' ? $message : self::DEFAULT_PROCESSING_MESSAGE;
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
