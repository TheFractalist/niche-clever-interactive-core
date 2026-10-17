<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;

use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Security\AdminActionGuard;
use NicheCleverInteractive\Core\Support\Translator;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Core's own markup uses the escape wrappers below; the hosted extension panel is administrator-only output from a registered extension, which escapes its own content.
// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- AdminActionGuard verifies capability and nonce before any POST value is read; GET values only choose a tab and are matched against fixed values.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Values are matched against strict patterns and validated by CoreSettings before anything is stored.
/**
 * The site-wide Settings page, present while an extension supplies the profile hub: the daily provider
 * allowance and Delete data on uninstall (General), whatever the extension adds for the whole site,
 * and the Help Centre. Without an extension Core's single Settings page holds these and this page does
 * not exist.
 */
final class SiteSettingsPage
{
    public const PAGE_SLUG = 'ncli-settings';
    private const SAVE_ACTION = 'ncli_save_site_settings';
    private const TABS = ['general' => 'General', 'help' => 'Help'];

    private ?ProfileHub $hub;
    private ?ExtensionScreens $screens;
    private AdminActionGuard $guard;
    /** @var callable(array<string, string>): void */
    private $redirect;
    private ?string $hook = null;

    /** @param callable(array<string, string>): void|null $redirect Receives the query for the page to return to. */
    public function __construct(?ProfileHub $hub = null, ?ExtensionScreens $screens = null, ?AdminActionGuard $guard = null, ?callable $redirect = null)
    {
        $this->hub = $hub;
        $this->screens = $screens;
        $this->guard = $guard ?? AdminActionGuard::forWordPress();
        $this->redirect = $redirect ?? [$this, 'redirectTo'];
    }

    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        // After Conversations (11) so the menu reads Profiles, Conversations, Settings, Help.
        add_action('admin_menu', [$this, 'addMenu'], 13);
        add_action('admin_post_' . self::SAVE_ACTION, [$this, 'save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    private function active(): bool
    {
        return $this->hub !== null && $this->hub->active();
    }

    public function addMenu(): void
    {
        if (!$this->active() || !function_exists('add_submenu_page')) {
            return;
        }

        $hook = add_submenu_page(SettingsPage::PAGE_SLUG, $this->t('Settings'), $this->t('Settings'), 'manage_options', self::PAGE_SLUG, [$this, 'render']);
        $this->hook = is_string($hook) ? $hook : null;
        // A menu entry that opens the Help tab directly: WordPress links a slug that is not a registered page as written.
        add_submenu_page(SettingsPage::PAGE_SLUG, $this->t('Help'), $this->t('Help'), 'manage_options', 'admin.php?page=' . self::PAGE_SLUG . '&tab=help');
    }

    public function enqueueAssets(string $hook): void
    {
        if ($this->hook === null || $hook !== $this->hook || !function_exists('wp_enqueue_style') || !function_exists('wp_enqueue_script') || !function_exists('plugins_url')) {
            return;
        }

        wp_enqueue_style('ncli-core-admin', plugins_url('assets/css/admin.css', dirname(__DIR__, 2) . '/niche-clever-interactive-core.php'), [], NCLI_CORE_VERSION);
        wp_enqueue_script('ncli-core-admin', plugins_url('assets/js/admin.js', dirname(__DIR__, 2) . '/niche-clever-interactive-core.php'), [], NCLI_CORE_VERSION, true);
    }

    public function render(): void
    {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            if (function_exists('wp_die')) {
                wp_die($this->t('You are not permitted to change NCI Core settings.'));
            }
            return;
        }

        $tab = $this->tab();
        echo '<div class="wrap ncli-admin ncli-admin--workspace"><h1 class="screen-reader-text">' . $this->html($this->t('Niche Clever Interactive settings')) . '</h1>';
        if ($this->hub !== null) {
            $this->hub->titleBar($this->t('Settings'), $this->t('Site settings'));
            $this->hub->masthead();
        }
        echo '<nav class="nav-tab-wrapper ncli-admin__tabs" aria-label="' . $this->attr($this->t('Niche Clever Interactive settings')) . '">';
        foreach (self::TABS as $id => $label) {
            echo '<a class="nav-tab' . ($id === $tab ? ' nav-tab-active' : '') . '"' . ($id === $tab ? ' aria-current="page"' : '') . ' href="' . $this->attr($this->url(['tab' => $id])) . '">' . $this->html($this->t($label)) . '</a>';
        }
        echo '</nav>';
        $this->notice();

        if ($tab === 'help') {
            (new HelpRenderer(new ProfileFieldRenderer(), fn (string $section): string => $this->url(['tab' => 'help', 'help' => $section])))->render();
        } else {
            $this->renderGeneral();
        }
        echo '</div>';
    }

    private function renderGeneral(): void
    {
        $values = $this->load()->toArray();
        echo '<form class="ncli-admin__workspace" method="post" action="' . $this->attr($this->postUrl()) . '"><input type="hidden" name="action" value="' . self::SAVE_ACTION . '">';
        echo $this->nonceField(self::SAVE_ACTION);
        echo '<table class="form-table ncli-admin__form-table" role="presentation"><tbody>';
        (new ProfileFieldRenderer())->siteRows($values);
        echo '</tbody></table>';
        echo '<div class="ncli-admin__save-bar"><button type="submit" class="button button-primary">' . $this->html($this->t('Save settings')) . '</button></div></form>';

        $body = $this->screens !== null ? $this->screens->bodyHtml('settings') : '';
        if ($body !== '') {
            echo '<section class="ncli-admin__tab-panel ncli-admin__extension ncli-admin__profile-section"><h2>' . $this->html($this->t('Extension settings')) . '</h2>' . $body . '</section>';
        }
    }

    public function save(): void
    {
        if (!$this->guard->allows(self::SAVE_ACTION, $_POST['_ncli_nonce'] ?? null)) {
            if (function_exists('wp_die')) {
                wp_die($this->t('You are not permitted to change NCI Core settings.'));
            }
            return;
        }

        $existing = $this->load()->toArray();
        $cap = $_POST['provider_requests_per_day'] ?? null;
        $existing['limits']['provider_requests_per_day'] = is_string($cap) && ctype_digit($cap) ? (int) $cap : $existing['limits']['provider_requests_per_day'];
        if (isset($_POST['delete_on_uninstall'])) {
            $existing['lifecycle']['delete_on_uninstall'] = $_POST['delete_on_uninstall'] === '1';
        }

        try {
            $settings = CoreSettings::fromArray($existing);
            update_option(CoreSettings::OPTION_NAME, $settings->toArray(), false);
            call_user_func($this->redirect, ['tab' => 'general', 'ncli_notice' => 'saved']);
        } catch (\InvalidArgumentException $exception) {
            call_user_func($this->redirect, ['tab' => 'general', 'ncli_notice' => 'invalid']);
        }
    }

    public function redirectTo(array $query): void
    {
        if (!function_exists('wp_safe_redirect')) {
            return;
        }

        wp_safe_redirect($this->url($query));
        exit;
    }

    private function load(): CoreSettings
    {
        $stored = function_exists('get_option') ? get_option(CoreSettings::OPTION_NAME, CoreSettings::defaults()) : CoreSettings::defaults();

        return CoreSettings::fromArray(is_array($stored) ? $stored : CoreSettings::defaults());
    }

    private function tab(): string
    {
        $tab = $_GET['tab'] ?? 'general';

        return is_string($tab) && isset(self::TABS[$tab]) ? $tab : 'general';
    }

    private function notice(): void
    {
        $notice = $_GET['ncli_notice'] ?? '';
        if ($notice === 'saved') {
            echo '<div class="notice notice-success"><p>' . $this->html($this->t('Settings saved.')) . '</p></div>';
        } elseif ($notice === 'invalid') {
            echo '<div class="notice notice-error"><p>' . $this->html($this->t('Settings could not be saved. Review the field values and try again.')) . '</p></div>';
        }
    }

    /** @param array<string, string> $query */
    private function url(array $query): string
    {
        $query = array_merge(['page' => self::PAGE_SLUG], $query);

        return function_exists('admin_url') ? (string) admin_url('admin.php?' . http_build_query($query)) : '/wp-admin/admin.php?' . http_build_query($query);
    }

    private function postUrl(): string
    {
        return function_exists('admin_url') ? (string) admin_url('admin-post.php') : '/wp-admin/admin-post.php';
    }

    /** The nonce as a hidden field. WordPress's own helper gives every field the same id, which is invalid when a page has two forms. */
    private function nonceField(string $action): string
    {
        $nonce = function_exists('wp_create_nonce') ? (string) wp_create_nonce($action) : '';

        return '<input type="hidden" name="_ncli_nonce" value="' . $this->attr($nonce) . '">';
    }

    private function html(string $value): string
    {
        return function_exists('esc_html') ? esc_html($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function attr(string $value): string
    {
        return function_exists('esc_attr') ? esc_attr($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function t(string $text): string
    {
        return Translator::text($text);
    }
}
