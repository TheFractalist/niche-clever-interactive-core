<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

/**
 * The editable settings of one extension profile, validated by Core before an extension stores
 * them. Core checks every value with the same rules it applies when it later answers from the
 * profile (ResolvedProfileV1::configured, the model catalogue and the panel appearance rules), so
 * an extension is never handed a value Core would reject. The vector-store reference is write-only:
 * null means "keep the stored one", and a stored reference is never handed back.
 */
final class ProfileEditV1
{
    private string $label;
    private bool $enabled;
    private ?string $vectorStoreReference;
    private bool $referenceSaved;
    private string $corpusVersion;
    private string $guidance;
    private string $preset;
    /** @var array<string, string> */
    private array $presentation;
    private string $panelStyle;
    private int $visitorRequestsPerDay;
    /** @var array<string, mixed>|null */
    private ?array $model;
    /** @var array<string, mixed>|null */
    private ?array $appearance;

    /**
     * @param array<string, string> $presentation
     * @param array<string, mixed>|null $model Complete model block, or null to inherit the default profile's.
     * @param array<string, mixed>|null $appearance Any of panel_width, accent_color, show_credit, or null to inherit.
     * @param bool $referenceSaved Only meaningful when an extension hands values back to Core: whether a reference is already stored.
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $label,
        bool $enabled,
        ?string $vectorStoreReference,
        string $corpusVersion,
        string $guidance,
        string $preset,
        array $presentation,
        string $panelStyle,
        int $visitorRequestsPerDay,
        ?array $model = null,
        ?array $appearance = null,
        bool $referenceSaved = false
    ) {
        $label = trim($label);
        if ($label === '' || strlen($label) > 80 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $label) === 1) {
            throw new InvalidArgumentException('A profile needs a short plain-text label.');
        }

        // One definition of what is valid: building the configuration Core would answer from.
        $resolved = ResolvedProfileV1::configured(new ProfileIdV1('placeholder'), $enabled, (string) $vectorStoreReference, $corpusVersion, $guidance, $preset, $presentation, $panelStyle, $visitorRequestsPerDay, $model, $appearance);
        $configuration = $resolved->configuration();
        if ($configuration === null) {
            throw new InvalidArgumentException('A profile configuration is required.');
        }

        $this->label = $label;
        $this->enabled = $enabled;
        $this->vectorStoreReference = $vectorStoreReference === null ? null : $configuration['vector_store_reference'];
        $this->referenceSaved = $referenceSaved;
        $this->corpusVersion = $configuration['corpus_version'];
        $this->guidance = $configuration['instructions'];
        $this->preset = $preset;
        $this->presentation = $configuration['presentation'];
        $this->panelStyle = $panelStyle;
        $this->visitorRequestsPerDay = $visitorRequestsPerDay;
        $this->model = $configuration['model'];
        $this->appearance = $configuration['appearance'];
    }

    public function label(): string
    {
        return $this->label;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /** The new reference to store, or null to keep the one already stored. */
    public function vectorStoreReference(): ?string
    {
        return $this->vectorStoreReference;
    }

    /** Whether a reference is already stored; its value is never available. */
    public function hasStoredReference(): bool
    {
        return $this->referenceSaved;
    }

    public function corpusVersion(): string
    {
        return $this->corpusVersion;
    }

    public function guidance(): string
    {
        return $this->guidance;
    }

    public function preset(): string
    {
        return $this->preset;
    }

    /** @return array<string, string> */
    public function presentation(): array
    {
        return $this->presentation;
    }

    public function panelStyle(): string
    {
        return $this->panelStyle;
    }

    public function visitorRequestsPerDay(): int
    {
        return $this->visitorRequestsPerDay;
    }

    /** @return array<string, mixed>|null */
    public function model(): ?array
    {
        return $this->model;
    }

    /** @return array<string, mixed>|null */
    public function appearance(): ?array
    {
        return $this->appearance;
    }
}
