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
            add_submenu_page(
                SettingsPage::PAGE_SLUG,
                self::label($screenId),
                self::label($screenId),
                self::CAPABILITY,
                self::PAGE_PREFIX . $screenId,
                function () use ($screenId): void {
                    $this->render($screenId);
                }
            );
        }
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

        echo '<div class="wrap ncli-admin ncli-admin--workspace"><h1>' . $this->escape(self::label($screenId)) . '</h1>';

        $extension = $this->registry->implementation(self::SEAM);
        if (!in_array($screenId, $this->screenIds(), true) || !$extension instanceof WorkspaceExtensionV1) {
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

        echo $body . '</div>';
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
