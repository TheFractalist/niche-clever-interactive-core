<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Profile;

use NicheCleverInteractive\Core\AI\Transport\RequestOptions;
use NicheCleverInteractive\Core\Api\V1\Value\ResolvedProfileV1;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
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
        string $panelStyle
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
            $settings->panelStyle()
        );
    }

    /**
     * An extension profile inherits Core's model, retrieval, timeout and provider settings;
     * it supplies only its own store, corpus version, guidance, wording, style and limit.
     * A profile built without a configuration cannot be answered from (null).
     */
    public static function fromResolved(ResolvedProfileV1 $profile, CoreSettings $defaults): ?self
    {
        $configuration = $profile->configuration();
        if ($configuration === null || $profile->id()->value() === CoreSettings::DEFAULT_PROFILE_ID) {
            return null;
        }

        return new self(
            $profile->id()->value(),
            $configuration['vector_store_reference'],
            $configuration['corpus_version'],
            $configuration['instructions'],
            $configuration['response_preset'],
            RequestOptions::fromSettings($defaults),
            $configuration['visitor_requests_per_day'],
            array_merge($defaults->presentation(), $configuration['presentation']),
            $configuration['panel_style']
        );
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
