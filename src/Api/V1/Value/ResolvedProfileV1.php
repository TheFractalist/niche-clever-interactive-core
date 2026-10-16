<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

final class ResolvedProfileV1
{
    private const PRESETS = ['concise', 'balanced', 'detailed'];
    private const STYLES = ['classic', 'minimal', 'dark'];
    private const PRESENTATION_LIMITS = [
        'title' => 120,
        'headline' => 180,
        'introduction' => 500,
        'placeholder' => 160,
        'footer_left' => 120,
        'footer_right' => 120,
        'unavailable_message' => 240,
    ];

    private ProfileIdV1 $id;
    private bool $enabled;
    private string $policyFingerprint;
    private string $corpusFingerprint;
    private string $responsePreset;
    /** @var array{vector_store_reference: string, corpus_version: string, instructions: string, response_preset: string, presentation: array<string, string>, panel_style: string, visitor_requests_per_day: int, model: array<string, mixed>|null, appearance: array<string, mixed>|null}|null */
    private ?array $configuration = null;

    public function __construct(
        ProfileIdV1 $id,
        bool $enabled,
        string $policyFingerprint,
        string $corpusFingerprint,
        string $responsePreset
    ) {
        $this->id = $id;
        $this->enabled = $enabled;
        $this->policyFingerprint = $policyFingerprint;
        $this->corpusFingerprint = $corpusFingerprint;
        $this->responsePreset = $responsePreset;
    }

    /**
     * A profile Core can answer from. The extension supplies configuration only; Core computes
     * the policy and corpus fingerprints itself, so the two stored below are placeholders that
     * Core never reads for a configured profile. Every field is bounded here and again by Core.
     *
     * @param array<string, string> $presentation Any subset of title, headline, introduction, placeholder, footer_left, footer_right, unavailable_message.
     * @param array<string, mixed>|null $model All of id, reasoning_effort, retrieval_depth, max_output_tokens, timeout_seconds, or null to use the default profile's model settings. Core checks the values against its model catalogue when it builds the profile and treats any other value as unavailable.
     * @param array<string, mixed>|null $appearance Any subset of panel_width, accent_color (six-digit hex or empty), show_credit, or null to use the default profile's.
     */
    public static function configured(
        ProfileIdV1 $id,
        bool $enabled,
        string $vectorStoreReference,
        string $corpusVersion,
        string $ownerGuidance,
        string $responsePreset,
        array $presentation,
        string $panelStyle,
        int $visitorRequestsPerDay,
        ?array $model = null,
        ?array $appearance = null
    ): self {
        if (strlen($vectorStoreReference) > 256 || strlen($corpusVersion) > 128 || strlen($ownerGuidance) > 4000) {
            throw new \InvalidArgumentException('A profile configuration value is out of bounds.');
        }
        if (!in_array($responsePreset, self::PRESETS, true) || !in_array($panelStyle, self::STYLES, true)) {
            throw new \InvalidArgumentException('A profile preset or style is not one Core offers.');
        }
        if ($visitorRequestsPerDay < 1 || $visitorRequestsPerDay > 10000) {
            throw new \InvalidArgumentException('A profile visitor limit is out of bounds.');
        }
        foreach ([$vectorStoreReference, $corpusVersion, $ownerGuidance] as $value) {
            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) === 1) {
                throw new \InvalidArgumentException('A profile configuration value is malformed.');
            }
        }
        if ($model !== null) {
            $expected = ['id' => 'string', 'reasoning_effort' => 'string', 'retrieval_depth' => 'integer', 'max_output_tokens' => 'integer', 'timeout_seconds' => 'integer'];
            if (array_keys($model) !== array_keys($expected)) {
                throw new \InvalidArgumentException('A profile model block needs exactly its five settings.');
            }
            foreach ($expected as $key => $type) {
                if (gettype($model[$key]) !== $type || (is_string($model[$key]) && (strlen($model[$key]) > 128 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $model[$key]) === 1))) {
                    throw new \InvalidArgumentException('A profile model value is malformed.');
                }
            }
        }
        if ($appearance !== null) {
            $expected = ['panel_width' => 'string', 'accent_color' => 'string', 'show_credit' => 'boolean'];
            foreach ($appearance as $key => $value) {
                if (!isset($expected[$key]) || gettype($value) !== $expected[$key] || (is_string($value) && strlen($value) > 16)) {
                    throw new \InvalidArgumentException('A profile appearance value is malformed.');
                }
            }
        }
        foreach ($presentation as $key => $value) {
            if (!is_string($key) || !isset(self::PRESENTATION_LIMITS[$key]) || !is_string($value)
                || strlen($value) > self::PRESENTATION_LIMITS[$key] || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value) === 1) {
                throw new \InvalidArgumentException('A profile wording value is out of bounds.');
            }
        }

        $profile = new self($id, $enabled, '', '', $responsePreset);
        $profile->configuration = [
            'vector_store_reference' => trim($vectorStoreReference),
            'corpus_version' => trim($corpusVersion),
            'instructions' => trim($ownerGuidance),
            'response_preset' => $responsePreset,
            'presentation' => array_map('trim', $presentation),
            'panel_style' => $panelStyle,
            'visitor_requests_per_day' => $visitorRequestsPerDay,
            'model' => $model,
            'appearance' => $appearance === null ? null : array_map(static fn ($v) => is_string($v) ? trim($v) : $v, $appearance),
        ];

        return $profile;
    }

    /** Null for a profile built with the legacy constructor, which Core cannot answer from. */
    public function configuration(): ?array
    {
        return $this->configuration;
    }

    public function id(): ProfileIdV1
    {
        return $this->id;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function policyFingerprint(): string
    {
        return $this->policyFingerprint;
    }

    public function corpusFingerprint(): string
    {
        return $this->corpusFingerprint;
    }

    public function responsePreset(): string
    {
        return $this->responsePreset;
    }
}
