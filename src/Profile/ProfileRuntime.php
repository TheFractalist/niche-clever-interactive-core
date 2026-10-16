<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Profile;

use NicheCleverInteractive\Core\AI\Transport\RequestOptions;
use NicheCleverInteractive\Core\Api\V1\Value\ResolvedProfileV1;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\ModelSettings;
use NicheCleverInteractive\Core\Configuration\PanelAppearance;
use NicheCleverInteractive\Core\Security\PolicyFingerprint;
use NicheCleverInteractive\Core\Security\PromptPolicy;

/**
 * The profile Core actually answers from. It is built either from Core's own settings
 * (the default profile) or from a configuration an extension supplied and Core validated.
 * Core computes every fingerprint itself; nothing an extension says about identity is trusted.
 */
final class ProfileRuntime
{
    private string $id;
    private string $vectorStoreReference;
    private string $corpusVersion;
    private string $instructions;
    private string $responsePreset;
    private RequestOptions $options;
    private int $visitorRequestsPerDay;
    /** @var array{title: string, headline: string, introduction: string, placeholder: string, footer_left: string, footer_right: string, unavailable_message: string} */
    private array $presentation;
    private string $panelStyle;
    private string $panelWidth;
    private string $accentColor;
    private bool $showCredit;

    /**
     * @param array{title: string, headline: string, introduction: string, placeholder: string, footer_left: string, footer_right: string, unavailable_message: string} $presentation
     */
    private function __construct(
        string $id,
        string $vectorStoreReference,
        string $corpusVersion,
        string $instructions,
        string $responsePreset,
        RequestOptions $options,
        int $visitorRequestsPerDay,
        array $presentation,
        string $panelStyle,
        string $panelWidth,
        string $accentColor,
        bool $showCredit
    ) {
        $this->id = $id;
        $this->vectorStoreReference = $vectorStoreReference;
        $this->corpusVersion = $corpusVersion;
        $this->instructions = $instructions;
        $this->responsePreset = $responsePreset;
        $this->options = $options;
        $this->visitorRequestsPerDay = $visitorRequestsPerDay;
        $this->presentation = $presentation;
        $this->panelStyle = $panelStyle;
        $this->panelWidth = $panelWidth;
        $this->accentColor = $accentColor;
        $this->showCredit = $showCredit;
    }

    public static function fromSettings(CoreSettings $settings): self
    {
        return new self(
            CoreSettings::DEFAULT_PROFILE_ID,
            $settings->vectorStoreReference(),
            $settings->corpusVersion(),
            $settings->instructions(),
            $settings->responsePreset(),
            RequestOptions::fromSettings($settings),
            $settings->visitorRequestsPerDay(),
            $settings->presentation(),
            $settings->panelStyle(),
            $settings->panelWidth(),
            $settings->accentColor(),
            $settings->showCredit()
        );
    }

    /**
     * An extension profile supplies its own store, corpus version, guidance, wording, style and
     * limit, and may supply its own model settings and panel width, accent colour and credit line;
     * anything it leaves out is inherited from the default profile. A value Core does not accept
     * (an unknown model, a bad colour) makes the profile unavailable rather than falling back.
     * A profile built without a configuration cannot be answered from (null).
     */
    public static function fromResolved(ResolvedProfileV1 $profile, CoreSettings $defaults): ?self
    {
        $configuration = $profile->configuration();
        if ($configuration === null || $profile->id()->value() === CoreSettings::DEFAULT_PROFILE_ID) {
            return null;
        }

        try {
            $options = $configuration['model'] === null
                ? RequestOptions::fromSettings($defaults)
                : self::optionsFrom(ModelSettings::validate($configuration['model'], $defaults->model()));
        } catch (\InvalidArgumentException $exception) {
            return null;
        }

        $inherited = $defaults->toArray()['appearance'];
        $own = $configuration['appearance'] ?? [];
        $width = $own['panel_width'] ?? $inherited['panel_width'];
        $accent = strtolower($own['accent_color'] ?? $inherited['accent_color']);
        if (!isset(PanelAppearance::WIDTHS[$width]) || ($accent !== '' && !PanelAppearance::isHexColour($accent))) {
            return null;
        }

        return new self(
            $profile->id()->value(),
            $configuration['vector_store_reference'],
            $configuration['corpus_version'],
            $configuration['instructions'],
            $configuration['response_preset'],
            $options,
            $configuration['visitor_requests_per_day'],
            array_merge($defaults->presentation(), $configuration['presentation']),
            $configuration['panel_style'],
            $width,
            $accent,
            $own['show_credit'] ?? $inherited['show_credit']
        );
    }

    /** @param array{id: string, reasoning_effort: string, retrieval_depth: int, max_output_tokens: int, timeout_seconds: int} $model */
    private static function optionsFrom(array $model): RequestOptions
    {
        return new RequestOptions($model['id'], $model['reasoning_effort'], $model['retrieval_depth'], $model['max_output_tokens'], $model['timeout_seconds']);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function isDefault(): bool
    {
        return $this->id === CoreSettings::DEFAULT_PROFILE_ID;
    }

    public function vectorStoreReference(): string
    {
        return $this->vectorStoreReference;
    }

    public function corpusVersion(): string
    {
        return $this->corpusVersion;
    }

    public function instructions(): string
    {
        return $this->instructions;
    }

    public function responsePreset(): string
    {
        return $this->responsePreset;
    }

    public function options(): RequestOptions
    {
        return $this->options;
    }

    public function visitorRequestsPerDay(): int
    {
        return $this->visitorRequestsPerDay;
    }

    /** @return array{title: string, headline: string, introduction: string, placeholder: string, footer_left: string, footer_right: string, unavailable_message: string} */
    public function presentation(): array
    {
        return $this->presentation;
    }

    public function panelStyle(): string
    {
        return $this->panelStyle;
    }

    public function panelWidth(): string
    {
        return $this->panelWidth;
    }

    /** Six-digit hex colour, or an empty string for the panel style's own colour. */
    public function accentColor(): string
    {
        return $this->accentColor;
    }

    public function showCredit(): bool
    {
        return $this->showCredit;
    }

    public function unavailableMessage(): string
    {
        $message = $this->presentation['unavailable_message'];

        return $message !== '' ? $message : CoreSettings::DEFAULT_UNAVAILABLE_MESSAGE;
    }

    /** @param list<\NicheCleverInteractive\Core\Api\V1\Value\ChronologyFactV1> $chronology */
    public function policyFingerprint(PromptPolicy $policy, array $chronology = [], bool $earlierTurns = false): string
    {
        return PolicyFingerprint::create($policy, $this->instructions, $this->responsePreset, $chronology, $earlierTurns);
    }

    /**
     * Identity of everything that decides what this profile answers from: corpus version, the
     * vector store and the model settings. Used only inside the answer-cache key, never exposed.
     */
    public function answerSourceFingerprint(): string
    {
        return hash('sha256', json_encode([
            'corpus' => $this->corpusVersion,
            'vector_store' => $this->vectorStoreReference,
            'model' => $this->options->model(),
            'effort' => $this->options->reasoningEffort(),
            'depth' => $this->options->retrievalDepth(),
            'tokens' => $this->options->maxOutputTokens(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function corpusFingerprint(): string
    {
        return hash('sha256', $this->corpusVersion);
    }
}
