<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;

/**
 * The sole final-output policy for extension markup hosted in the Core administrator UI.
 * Extensions may supply only ordinary, non-executable administrator controls; Core owns the
 * resulting HTML boundary and intentionally never admits scripts, styles or event attributes.
 */
final class ExtensionMarkupPolicy
{
    /** @var list<string> Protocols permitted in extension-controlled links and image URLs. */
    private const URL_PROTOCOLS = ['http', 'https', 'mailto'];

    public static function sanitise(string $markup): string
    {
        if (!function_exists('wp_kses')) {
            return '';
        }

        return wp_kses($markup, self::allowedHtml(), self::allowedProtocols());
    }

    /** @return array<string, array<string, bool>> */
    public static function allowedHtml(): array
    {
        return [
            'a' => ['aria-current' => true, 'class' => true, 'href' => true, 'rel' => true, 'target' => true],
            'button' => ['aria-controls' => true, 'aria-expanded' => true, 'aria-label' => true, 'class' => true, 'data-*' => true, 'disabled' => true, 'form' => true, 'name' => true, 'type' => true, 'value' => true],
            'code' => ['class' => true],
            'details' => ['class' => true, 'open' => true],
            'div' => ['aria-live' => true, 'class' => true, 'data-*' => true, 'hidden' => true, 'id' => true, 'role' => true],
            'em' => [],
            'form' => ['action' => true, 'class' => true, 'method' => true],
            'h2' => ['class' => true],
            'h3' => ['class' => true],
            'img' => ['alt' => true, 'class' => true, 'height' => true, 'src' => true, 'width' => true],
            'input' => ['aria-describedby' => true, 'aria-label' => true, 'autocomplete' => true, 'checked' => true, 'class' => true, 'data-*' => true, 'disabled' => true, 'hidden' => true, 'id' => true, 'max' => true, 'maxlength' => true, 'min' => true, 'name' => true, 'pattern' => true, 'placeholder' => true, 'readonly' => true, 'required' => true, 'step' => true, 'type' => true, 'value' => true],
            'label' => ['class' => true, 'for' => true],
            'li' => ['class' => true],
            'ol' => ['class' => true],
            'option' => ['data-*' => true, 'disabled' => true, 'selected' => true, 'value' => true],
            'p' => ['class' => true],
            'select' => ['aria-describedby' => true, 'class' => true, 'data-*' => true, 'disabled' => true, 'id' => true, 'name' => true, 'required' => true],
            'span' => ['aria-hidden' => true, 'class' => true, 'data-*' => true, 'hidden' => true, 'id' => true, 'role' => true],
            'strong' => ['class' => true],
            'summary' => ['class' => true],
            'table' => ['class' => true, 'role' => true],
            'tbody' => [],
            'td' => ['class' => true, 'colspan' => true, 'rowspan' => true],
            'textarea' => ['aria-describedby' => true, 'class' => true, 'cols' => true, 'id' => true, 'maxlength' => true, 'name' => true, 'placeholder' => true, 'required' => true, 'rows' => true],
            'th' => ['class' => true, 'colspan' => true, 'rowspan' => true, 'scope' => true],
            'thead' => [],
            'tr' => ['class' => true],
            'ul' => ['class' => true],
        ];
    }

    /** @return list<string> */
    public static function allowedProtocols(): array
    {
        return self::URL_PROTOCOLS;
    }
}
