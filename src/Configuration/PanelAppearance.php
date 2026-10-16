<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Configuration;

/**
 * Pure helper for the bounded public-panel appearance options: width and an
 * optional owner accent colour. It never emits free-form CSS.
 */
final class PanelAppearance
{
    public const WIDTHS = [
        'narrow' => '36rem',
        'standard' => '48rem',
        'wide' => '64rem',
    ];

    private const STYLE_ACCENTS = [
        'classic' => '#111111',
        'minimal' => '#2563eb',
        'dark' => '#7dd3fc',
    ];

    private const STYLE_BACKGROUNDS = [
        'classic' => '#ffffff',
        'minimal' => '#ffffff',
        'dark' => '#0f1115',
    ];

    private const STYLE_INKS = [
        'classic' => '#111111',
        'minimal' => '#1f2937',
        'dark' => '#e8eaed',
    ];

    private const STYLE_SURFACES = [
        'classic' => '#eeeeee',
        'minimal' => '#f3f4f6',
        'dark' => '#1a1e27',
    ];

    private const MINIMUM_ACCENT_CONTRAST = 3.0;
    private const MINIMUM_TEXT_CONTRAST = 3.0;

    public static function isHexColour(string $value): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/D', $value) === 1;
    }

    public static function defaultAccent(string $style): string
    {
        return self::STYLE_ACCENTS[$style] ?? self::STYLE_ACCENTS['classic'];
    }

    /**
     * WCAG relative-luminance contrast ratio between two #rrggbb colours.
     */
    public static function contrastRatio(string $first, string $second): float
    {
        $a = self::luminance($first);
        $b = self::luminance($second);
        $lighter = max($a, $b);
        $darker = min($a, $b);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * The owner's accent, lower-cased, whenever it is a valid colour; otherwise an
     * empty string, meaning "use the style accent". The owner's choice is never
     * discarded for being pale or dark: readability is protected instead by
     * inlineStyle(), which adds a button edge and a safe focus colour when needed.
     */
    public static function effectiveAccent(string $style, string $accent): string
    {
        return self::isHexColour($accent) ? strtolower($accent) : '';
    }

    /**
     * Whichever of white or near-black reads better on the given accent.
     */
    public static function onAccent(string $accent): string
    {
        return self::contrastRatio($accent, '#ffffff') >= self::contrastRatio($accent, '#111111') ? '#ffffff' : '#111111';
    }

    /**
     * Inline custom properties for the panel root. Every value is chosen from a
     * fixed map or validated as #rrggbb, so the result is safe to escape as an
     * attribute. An empty string means no inline override is needed.
     */
    public static function inlineStyle(string $style, string $width, string $accent): string
    {
        $declarations = [];
        if ($width !== 'standard' && isset(self::WIDTHS[$width])) {
            $declarations[] = '--ncli-max:' . self::WIDTHS[$width];
        }

        $effective = self::effectiveAccent($style, $accent);
        if ($effective !== '') {
            $declarations[] = '--ncli-accent:' . $effective;
            $declarations[] = '--ncli-on-accent:' . self::onAccent($effective);

            // An accent too close to the panel background would make the button or the
            // keyboard focus ring hard to see, so give the button an edge in the style's
            // text colour and keep the style's own accent for the focus ring.
            $background = self::STYLE_BACKGROUNDS[$style] ?? self::STYLE_BACKGROUNDS['classic'];
            if (self::contrastRatio($effective, $background) < self::MINIMUM_ACCENT_CONTRAST) {
                $declarations[] = '--ncli-focus:' . self::defaultAccent($style);
                $declarations[] = '--ncli-edge:' . (self::STYLE_INKS[$style] ?? self::STYLE_INKS['classic']);
            }
        }

        return implode(';', $declarations);
    }

    /**
     * Custom properties for the owner's question and answer block colours. A block's background and
     * text are used only if the text can be read on the background (WCAG contrast of at least 3:1),
     * measured against the style's own colour for whichever of the two was not chosen; otherwise both
     * fall back to the style. Borders are always used when valid. Values are validated #rrggbb.
     *
     * @param array<string, string> $colours Keys of PanelColoursV1::KEYS.
     */
    public static function colourStyle(string $style, array $colours): string
    {
        $background = self::STYLE_BACKGROUNDS[$style] ?? self::STYLE_BACKGROUNDS['classic'];
        $surface = self::STYLE_SURFACES[$style] ?? self::STYLE_SURFACES['classic'];
        $ink = self::STYLE_INKS[$style] ?? self::STYLE_INKS['classic'];
        $declarations = [];

        foreach ([
            'question' => ['q', $surface],
            'answer' => ['a', $background],
        ] as $block => [$short, $defaultBackground]) {
            $bg = $colours[$block . '_background'] ?? null;
            $text = $colours[$block . '_text'] ?? null;
            $border = $colours[$block . '_border'] ?? null;
            foreach ([$bg, $text, $border] as $value) {
                if ($value !== null && !self::isHexColour($value)) {
                    return '';
                }
            }

            $effectiveBackground = $bg ?? $defaultBackground;
            $effectiveText = $text ?? $ink;
            if (($bg !== null || $text !== null) && self::contrastRatio($effectiveText, $effectiveBackground) >= self::MINIMUM_TEXT_CONTRAST) {
                if ($bg !== null) {
                    $declarations[] = '--ncli-' . $short . '-bg:' . strtolower($bg);
                }
                if ($text !== null) {
                    $declarations[] = '--ncli-' . $short . '-ink:' . strtolower($text);
                }
            }
            if ($border !== null) {
                $declarations[] = '--ncli-' . $short . '-rule:' . strtolower($border);
            }
        }

        return implode(';', $declarations);
    }

    private static function luminance(string $colour): float
    {
        $channels = [
            hexdec(substr($colour, 1, 2)) / 255,
            hexdec(substr($colour, 3, 2)) / 255,
            hexdec(substr($colour, 5, 2)) / 255,
        ];
        foreach ($channels as $index => $channel) {
            $channels[$index] = $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
