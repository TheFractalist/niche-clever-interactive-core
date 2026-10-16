<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;

use NicheCleverInteractive\Core\Api\V1\Contracts\WorkspaceExtensionV1;
use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceContextV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceScreenV1;
use NicheCleverInteractive\Core\Support\Translator;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Core's own markup uses the escape wrappers below; the buffered extension screen is administrator-only output from a registered extension, which is responsible for escaping its own content.
/**
 * Hosts the screens of a registered workspace extension as submenu pages of the
 * "NC Interactive" menu. Core owns the menu, capability check, screen identifiers,
 * titles and nonce; the extension supplies only the screen body. Any failure in the
 * extension hides its screens or replaces one body with a generic notice.
 */
final class ExtensionScreens
{
    public const SEAM = 'workspace_extension';
    public const PAGE_PREFIX = 'ncli-ext-';
    public const NONCE_PREFIX = 'ncli_ext_';
    private const CAPABILITY = 'manage_options';

    private ExtensionRegistryV1 $registry;
    /** @var array<string, string> Hook suffix => screen id, for the pages Core added. */
    private array $hooks = [];

    public function __construct(ExtensionRegistryV1 $registry)
    {
        $this->registry = $registry;
    }

    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        // After SettingsPage (10) and ConversationsPage (11) have created the menu.
        add_action('admin_menu', [$this, 'addMenus'], 12);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /** Core-owned, translatable titles; an extension cannot choose its own label. */
    public static function label(string $screenId): string
    {
        $labels = [
            'profiles' => 'Profiles',
            'chronology' => 'Chronology',
            'questions' => 'Questions',
            'collections' => 'Collections',
            'cache' => 'Answer cache',
            'archive' => 'Conversation governance',
            'evidence' => 'Evidence links',
        ];

        return Translator::text($labels[$screenId] ?? 'Extension');
    }

    public function addMenus(): void
    {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        foreach ($this->screenIds() as $screenId) {
            $hook = add_submenu_page(
                SettingsPage::PAGE_SLUG,
                self::label($screenId),
                self::label($screenId),
                self::CAPABILITY,
                self::PAGE_PREFIX . $screenId,
                function () use ($screenId): void {
                    $this->render($screenId);
                }
            );
            if (is_string($hook)) {
                $this->hooks[$hook] = $screenId;
            }
        }
    }

    /** Loads the same stylesheet Core's own screens use, so extension screens look identical. */
    public function enqueueAssets(string $hook): void
    {
        if (!isset($this->hooks[$hook]) || !function_exists('wp_enqueue_style') || !function_exists('plugins_url')) {
            return;
        }

        wp_enqueue_style('ncli-core-admin', plugins_url('assets/css/admin.css', dirname(__DIR__, 2) . '/niche-clever-interactive-core.php'), [], NCLI_CORE_VERSION);
    }

    /**
     * Screen identifiers the extension offers, validated and de-duplicated by Core.
     * An unregistered, throwing or malformed extension yields none.
     *
     * @return list<string>
     */
    public function screenIds(): array
    {
        $extension = $this->registry->implementation(self::SEAM);
        if (!$extension instanceof WorkspaceExtensionV1) {
            return [];
        }

        try {
            $screens = $extension->screens();
        } catch (\Throwable $exception) {
            return [];
        }

        if (!is_array($screens)) {
            return [];
        }

        $ids = [];
        foreach ($screens as $screen) {
            if (!$screen instanceof WorkspaceScreenV1) {
                return [];
            }
            $ids[$screen->id()] = true;
        }

        return array_keys($ids);
    }

    public function render(string $screenId): void
    {
        if (!function_exists('current_user_can') || !current_user_can(self::CAPABILITY)) {
            echo '<div class="wrap"><p>' . $this->escape(Translator::text('You do not have permission to view this page.')) . '</p></div>';
            return;
        }

        $ids = $this->screenIds();
        echo '<div class="wrap ncli-admin ncli-admin--workspace"><h1 class="screen-reader-text">' . $this->escape(self::label($screenId)) . '</h1>';
        $this->masthead();
        $this->tabs($ids, $screenId);

        $extension = $this->registry->implementation(self::SEAM);
        if (!in_array($screenId, $ids, true) || !$extension instanceof WorkspaceExtensionV1) {
            $this->notice();
            echo '</div>';
            return;
        }

        $level = ob_get_level();
        ob_start();
        try {
            $action = self::NONCE_PREFIX . $screenId;
            $nonce = function_exists('wp_create_nonce') ? (string) wp_create_nonce($action) : '';
            $extension->render($screenId, new WorkspaceContextV1(true, $nonce, $screenId, $action));
            $body = (string) ob_get_clean();
        } catch (\Throwable $exception) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            $this->notice();
            echo '</div>';
            return;
        }

        echo '<section class="ncli-admin__tab-panel ncli-admin__extension">' . $body . '</section></div>';
    }

    private function masthead(): void
    {
        $path = 'assets/images/ncli-admin-banner.png';
        $url = function_exists('plugins_url') ? (string) plugins_url($path, dirname(__DIR__, 2) . '/niche-clever-interactive-core.php') : '';
        $file = dirname(__DIR__, 2) . '/' . $path;
        $modified = is_file($file) ? (int) filemtime($file) : 0;
        if ($url !== '' && $modified > 0) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'ver=' . $modified;
        }

        echo '<header class="ncli-admin__masthead"><img class="ncli-admin__masthead-art" src="' . $this->attribute($url) . '" alt="" width="1544" height="500"></header>';
    }

    /**
     * The same tab bar Core's other screens use: a way back to Settings, then one tab per
     * screen the extension offers, in the order it declared them.
     *
     * @param list<string> $ids
     */
    private function tabs(array $ids, string $current): void
    {
        $admin = static fn (string $page): string => function_exists('admin_url') ? (string) admin_url('admin.php?page=' . $page) : 'admin.php?page=' . $page;

        echo '<nav class="nav-tab-wrapper ncli-admin__tabs" aria-label="' . $this->attribute(Translator::text('Niche Clever Interactive Premium screens')) . '">';
        echo '<a class="nav-tab" href="' . $this->attribute($admin(SettingsPage::PAGE_SLUG)) . '">' . $this->escape(Translator::text('Settings')) . '</a>';
        foreach ($ids as $id) {
            $active = $id === $current;
            echo '<a class="nav-tab' . ($active ? ' nav-tab-active' : '') . '" href="' . $this->attribute($admin(self::PAGE_PREFIX . $id)) . '"' . ($active ? ' aria-current="page"' : '') . '>' . $this->escape(self::label($id)) . '</a>';
        }
        echo '</nav>';
    }

    private function attribute(string $value): string
    {
        return function_exists('esc_attr') ? esc_attr($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function notice(): void
    {
        echo '<div class="notice notice-error"><p>' . $this->escape(Translator::text('This screen is temporarily unavailable.')) . '</p></div>';
    }

    private function escape(string $value): string
    {
        return function_exists('esc_html') ? esc_html($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
