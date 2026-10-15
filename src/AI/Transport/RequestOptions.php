<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\AI\Transport;

use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\ModelCatalogue;

/**
 * Immutable provider-request tuning derived from validated Core settings.
 */
final class RequestOptions
{
    private string $model;
    private string $reasoningEffort;
    private int $retrievalDepth;
    private int $maxOutputTokens;
    private int $timeoutSeconds;

    public function __construct(
        string $model = ModelCatalogue::DEFAULT_MODEL,
        string $reasoningEffort = ModelCatalogue::DEFAULT_EFFORT,
        int $retrievalDepth = ModelCatalogue::DEFAULT_RETRIEVAL_DEPTH,
        int $maxOutputTokens = ModelCatalogue::DEFAULT_OUTPUT_TOKENS,
        int $timeoutSeconds = ModelCatalogue::DEFAULT_TIMEOUT_SECONDS
    ) {
        $this->model = $model;
        $this->reasoningEffort = $reasoningEffort;
        $this->retrievalDepth = $retrievalDepth;
        $this->maxOutputTokens = $maxOutputTokens;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public static function fromSettings(CoreSettings $settings): self
    {
        $model = $settings->toArray()['model'];

        return new self($model['id'], $model['reasoning_effort'], $model['retrieval_depth'], $model['max_output_tokens'], $model['timeout_seconds']);
    }

    public function model(): string
    {
        return $this->model;
    }

    public function reasoningEffort(): string
    {
        return $this->reasoningEffort;
    }

    /** The effort to send, or null when the provider default applies or the model has no reasoning control. */
    public function effortToSend(): ?string
    {
        if ($this->reasoningEffort === ModelCatalogue::DEFAULT_EFFORT || !ModelCatalogue::validEffort($this->model, $this->reasoningEffort)) {
            return null;
        }

        return $this->reasoningEffort;
    }

    public function retrievalDepth(): int
    {
        return $this->retrievalDepth;
    }

    public function maxOutputTokens(): int
    {
        return $this->maxOutputTokens;
    }

    public function timeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }
}
