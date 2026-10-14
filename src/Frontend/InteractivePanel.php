<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Frontend;

use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Support\Translator;

final class InteractivePanel
{
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
            wp_add_inline_script('ncli-core-public', 'window.NCLI_CORE_CHAT = ' . wp_json_encode([
                'route' => rest_url('ncli/v1/chat'),
                'strings' => [
                    'conversationReset' => Translator::text('Conversation reset for this browser session.'),
                    'unavailable' => Translator::text('This interactive service is unavailable.'),
                    'invalidQuestion' => Translator::text('Please enter a valid question.'),
                    'findingAnswer' => Translator::text('Finding a verified answer…'),
                    'temporaryFailure' => Translator::text('This interactive service is temporarily unavailable.'),
                    'answerAdded' => Translator::text('Verified answer added to this session.'),
                ],
            ]) . ';', 'before');
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
        $style = $settings->toArray()['appearance']['panel_style'];
        $readiness = Readiness::evaluate($settings, new SecretResolver());
        $this->enqueueRegisteredAssets();

        $presentation = $settings->presentation();
        $title = isset($attributes['title']) && is_string($attributes['title']) && trim($attributes['title']) !== ''
            ? $attributes['title']
            : $presentation['title'];
        $status = $readiness->isReady() ? Translator::text('Ready for a verified answer.') : Translator::text('This interactive service is not available yet.');
        $disabled = $readiness->isReady() ? '' : ' disabled';

        return '<section class="ncli-panel ncli-panel--' . $this->attr($style) . '" aria-labelledby="ncli-panel-title">'
            . '<div class="ncli-panel__frame">'
            . '<header class="ncli-panel__header"><h2 id="ncli-panel-title" class="ncli-panel__title">' . $this->html($title) . '</h2>'
            . '<p class="ncli-panel__headline">' . $this->html($presentation['headline']) . '</p>'
            . ($presentation['introduction'] !== '' ? '<p class="ncli-panel__introduction">' . $this->html($presentation['introduction']) . '</p>' : '')
            . '</header>'
            . '<form class="ncli-panel__form" data-ncli-interactive="1">'
            . '<label class="screen-reader-text" for="ncli-question">' . $this->html(Translator::text('Ask a question')) . '</label>'
            . '<div class="ncli-panel__input-shell"><textarea id="ncli-question" name="question" rows="1" maxlength="2000" placeholder="' . $this->attr($presentation['placeholder']) . '"' . $disabled . '></textarea>'
            . '<button type="submit" aria-label="' . $this->attr(Translator::text('Submit question')) . '"' . $disabled . '><span aria-hidden="true">↗</span><span class="screen-reader-text">' . $this->html(Translator::text('Ask')) . '</span></button></div>'
            . '<button class="ncli-panel__reset" type="button" hidden>' . $this->html(Translator::text('Reset conversation')) . '</button>'
            . '</form>'
            . '<p class="ncli-panel__status" aria-live="polite">' . $this->html($status) . '</p>'
            . '<div class="ncli-panel__transcript" aria-live="polite"></div>'
            . '<footer class="ncli-panel__footer"><span>' . $this->html($presentation['footer_left']) . '</span><span>' . $this->html($presentation['footer_right']) . ' <b aria-hidden="true">→</b></span></footer>'
            . '</div>'
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
