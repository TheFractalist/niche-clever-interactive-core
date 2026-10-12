<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Frontend;

use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;

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
            wp_add_inline_script('ncli-core-public', 'window.NCLI_CORE_CHAT = ' . wp_json_encode(['route' => rest_url('ncli/v1/chat')]) . ';', 'before');
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

        $title = isset($attributes['title']) && is_string($attributes['title']) ? $attributes['title'] : 'Ask Niche Clever';
        $status = $readiness->isReady() ? 'Ready for a verified answer.' : 'This interactive service is not available yet.';
        $disabled = $readiness->isReady() ? '' : ' disabled';

        return '<section class="ncli-panel ncli-panel--' . $this->attr($style) . '" aria-labelledby="ncli-panel-title">'
            . '<h2 id="ncli-panel-title">' . $this->html($title) . '</h2>'
            . '<form class="ncli-panel__form" data-ncli-interactive="1">'
            . '<label for="ncli-question">Your question</label>'
            . '<textarea id="ncli-question" name="question" rows="4" maxlength="2000"' . $disabled . '></textarea>'
            . '<button type="submit"' . $disabled . '>Ask</button>'
            . '</form>'
            . '<p class="ncli-panel__status" aria-live="polite">' . $this->html($status) . '</p>'
            . '<div class="ncli-panel__transcript" aria-live="polite"></div>'
            . '</section>';
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function renderLimitNotice(array $attributes = []): string
    {
        $message = isset($attributes['message']) && is_string($attributes['message'])
            ? $attributes['message']
            : 'Question limits help keep this service available.';

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
