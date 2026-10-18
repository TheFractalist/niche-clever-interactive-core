<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Frontend;

use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\PanelAppearance;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;
use NicheCleverInteractive\Core\Profile\ProfileRuntime;
use NicheCleverInteractive\Core\Security\PublicError;
use NicheCleverInteractive\Core\Support\Translator;

final class InteractivePanel
{
    /** Destination of the optional, opt-in credit line. */
    private const CREDIT_URL = 'https://nicheclever.co.uk/';

    private ProfileDirectory $profiles;

    public function __construct(?ProfileDirectory $profiles = null)
    {
        $this->profiles = $profiles ?? new ProfileDirectory();
    }

    public function register(): void
    {
        if (!function_exists('add_shortcode')) {
            return;
        }

        add_shortcode('ncli_interactive', [$this, 'renderInteractive']);
        add_shortcode('ncli_limit_notice', [$this, 'renderLimitNotice']);

        if (function_exists('add_action')) {
            add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
            add_action('init', [$this, 'registerBlock']);
        }
    }

    public function enqueueAssets(): void
    {
        if (!function_exists('wp_register_style') || !function_exists('plugins_url')) {
            return;
        }

        wp_register_style('ncli-core-public', plugins_url('assets/css/public.css', dirname(__DIR__, 2) . '/niche-clever-interactive-core.php'), [], NCLI_CORE_VERSION);
        wp_register_script('ncli-core-public', plugins_url('assets/js/public.js', dirname(__DIR__, 2) . '/niche-clever-interactive-core.php'), [], NCLI_CORE_VERSION, true);
        if (function_exists('wp_add_inline_script') && function_exists('rest_url') && function_exists('wp_json_encode')) {
            $config = [
                'route' => rest_url('ncli/v1/chat'),
                // Lets the route recognise a logged-in viewer; the client sends it only for named profiles.
                'nonce' => function_exists('wp_create_nonce') ? wp_create_nonce('wp_rest') : '',
                'strings' => [
                    'conversationReset' => Translator::text('Conversation reset for this browser session.'),
                    'unavailable' => $this->load()->unavailableMessage(),
                    'invalidQuestion' => Translator::text('Please enter a valid question.'),
                    'findingAnswer' => Translator::text('Finding a verified answer…'),
                    'temporaryFailure' => Translator::text('This interactive service is temporarily unavailable.'),
                    'answerAdded' => Translator::text('Verified answer added to this session.'),
                    'questionLabel' => Translator::text('Question:'),
                    'answerLabel' => Translator::text('Answer:'),
                    'sourcesLabel' => Translator::text('Sources'),
                ],
            ];
            // An installed extension may add to the public script's configuration, for example a
            // `cachedNote` string shown under reused answers. Core adds none.
            if (function_exists('apply_filters')) {
                $filtered = apply_filters('ncli_core_public_config', $config);
                $config = is_array($filtered) ? $filtered : $config;
            }
            wp_add_inline_script('ncli-core-public', 'window.NCLI_CORE_CHAT = ' . wp_json_encode($config) . ';', 'before');
        }
    }

    public function registerBlock(): void
    {
        if (function_exists('register_block_type')) {
            register_block_type(dirname(__DIR__, 2) . '/blocks/interactive');
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderInteractive(array $attributes = []): string
    {
        $settings = $this->load();
        $this->enqueueRegisteredAssets();

        // A `profile` attribute selects an extension profile. Anything Core cannot resolve
        // renders the default panel's disabled, generic state, never the default corpus.
        $requested = isset($attributes['profile']) && is_string($attributes['profile']) ? trim($attributes['profile']) : '';
        $profileId = '';
        $colourProfile = null;
        $panelMarker = 'unavailable';
        $width = $settings->panelWidth();
        $accentColour = $settings->accentColor();
        $showCredit = $settings->showCredit();
        if ($requested === '' || $requested === CoreSettings::DEFAULT_PROFILE_ID) {
            $style = $settings->panelStyle();
            $colourProfile = ProfileRuntime::fromSettings($settings);
            $panelMarker = CoreSettings::DEFAULT_PROFILE_ID;
            $ready = Readiness::evaluate($settings, new SecretResolver())->isReady();
            $presentation = $settings->presentation();
            $unavailable = $settings->unavailableMessage();
        } else {
            $profile = $this->profiles->resolve($requested, $settings);
            if ($profile === null) {
                $style = $settings->panelStyle();
                $ready = false;
                $presentation = $settings->presentation();
                $unavailable = $settings->unavailableMessage();
            } else {
                $style = $profile->panelStyle();
                $width = $profile->panelWidth();
                $accentColour = $profile->accentColor();
                $showCredit = $profile->showCredit();
                $presentation = $profile->presentation();
                $unavailable = $profile->unavailableMessage();
                $access = $this->profiles->access($profile);
                if ($access->isRestricted()) {
                    // A page whose panel depends on who is viewing must never be served from a shared cache.
                    $this->markUncacheable();
                }
                if (!$access->isAllowed()) {
                    // The form stays disabled and carries no profile marker; the owner's message replaces the status.
                    $ready = false;
                    $unavailable = PublicError::plainText($access->message()) ?? Translator::text('Access to this service has not been granted.');
                } else {
                    $profileId = $profile->id();
                    $colourProfile = $profile;
                    $panelMarker = $profile->id();
                    $ready = Readiness::evaluateProfile($profile, new SecretResolver(), $this->profiles->testRecord($profile, $settings))->isReady();
                }
            }
        }
        $title = isset($attributes['title']) && is_string($attributes['title']) && trim($attributes['title']) !== ''
            ? $attributes['title']
            : $presentation['title'];
        $status = $ready ? Translator::text('Ready for a verified answer.') : $unavailable;
        $inlineStyle = PanelAppearance::inlineStyle($style, $width, $accentColour);
        $colours = $colourProfile !== null ? $this->profiles->colours($colourProfile) : null;
        if ($colours !== null) {
            $colourStyle = PanelAppearance::colourStyle($style, $colours->toArray());
            $inlineStyle = $inlineStyle === '' ? $colourStyle : ($colourStyle === '' ? $inlineStyle : $inlineStyle . ';' . $colourStyle);
        }
        $credit = $showCredit
            ? '<p class="ncli-panel__credit"><a href="' . $this->attr(self::CREDIT_URL) . '" target="_blank" rel="noopener">' . $this->html(Translator::text('Powered by NC Interactive')) . '</a></p>'
            : '';
        $disabled = $ready ? '' : ' disabled';

        return '<section class="ncli-panel ncli-panel--' . $this->attr($style) . '" data-ncli-panel="' . $this->attr($panelMarker) . '"' . ($inlineStyle !== '' ? ' style="' . $this->attr($inlineStyle) . '"' : '') . ' aria-labelledby="ncli-panel-title">'
            . '<div class="ncli-panel__frame">'
            . '<header class="ncli-panel__header"><h2 id="ncli-panel-title" class="ncli-panel__title">' . $this->html($title) . '</h2>'
            . '<p class="ncli-panel__headline">' . $this->html($presentation['headline']) . '</p>'
            . ($presentation['introduction'] !== '' ? '<p class="ncli-panel__introduction">' . $this->html($presentation['introduction']) . '</p>' : '')
            . '</header>'
            . '<div class="ncli-panel__transcript" aria-live="polite"></div>'
            . '<p class="ncli-panel__status" aria-live="polite">' . $this->html($status) . '</p>'
            . '<form class="ncli-panel__form" data-ncli-interactive="1"' . ($profileId !== '' ? ' data-ncli-profile="' . $this->attr($profileId) . '"' : '') . '>'
            . '<label class="screen-reader-text" for="ncli-question">' . $this->html(Translator::text('Ask a question')) . '</label>'
            . '<div class="ncli-panel__input-shell"><textarea id="ncli-question" name="question" rows="1" maxlength="2000" placeholder="' . $this->attr($presentation['placeholder']) . '"' . $disabled . '></textarea>'
            . '<button type="submit" aria-label="' . $this->attr(Translator::text('Submit question')) . '"' . $disabled . '><span aria-hidden="true">↗</span><span class="screen-reader-text">' . $this->html(Translator::text('Ask')) . '</span></button></div>'
            . '<button class="ncli-panel__reset" type="button" hidden>' . $this->html(Translator::text('Reset conversation')) . '</button>'
            . '</form>'
            . '<footer class="ncli-panel__footer"><span>' . $this->html($presentation['footer_left']) . '</span><span>' . $this->html($presentation['footer_right']) . ' <b aria-hidden="true">→</b></span></footer>'
            . '</div>'
            . $credit
            . '</section>';
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderLimitNotice(array $attributes = []): string
    {
        $message = isset($attributes['message']) && is_string($attributes['message'])
            ? $attributes['message']
            : Translator::text('Question limits help keep this service available.');

        return '<p class="ncli-limit-notice" role="status">' . $this->html($message) . '</p>';
    }

    private function markUncacheable(): void
    {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- The constant name is the page-cache convention WordPress caching plugins read.
        }
        if (function_exists('nocache_headers') && !headers_sent()) {
            nocache_headers();
        }
    }

    private function load(): CoreSettings
    {
        $stored = function_exists('get_option') ? get_option(CoreSettings::OPTION_NAME, CoreSettings::defaults()) : CoreSettings::defaults();
        return CoreSettings::fromArray(is_array($stored) ? $stored : CoreSettings::defaults());
    }

    private function enqueueRegisteredAssets(): void
    {
        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style('ncli-core-public');
        }
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script('ncli-core-public');
        }
    }

    private function html(string $value): string
    {
        return function_exists('esc_html') ? esc_html($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function attr(string $value): string
    {
        return function_exists('esc_attr') ? esc_attr($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
