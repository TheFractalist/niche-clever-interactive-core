<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Admin\ConversationsPage;
use NicheCleverInteractive\Core\Admin\ExtensionScreens;
use NicheCleverInteractive\Core\Admin\ProfileHub;
use NicheCleverInteractive\Core\Admin\SettingsPage;
use NicheCleverInteractive\Core\Admin\SiteSettingsPage;
use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Conversation\ConversationStore;
use NicheCleverInteractive\Core\Conversation\ConversationTurn;
use NicheCleverInteractive\Core\Frontend\InteractivePanel;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;
use NicheCleverInteractive\Core\Security\AdminActionGuard;

/**
 * Core must stand on its own. This guards two things: the shipped Core code never contains,
 * refers to or depends on the separately supplied edition, and with no extension registered every
 * page and menu is exactly Core's own.
 */
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

// ---- Source: no dependency on, and no locking by, another plugin ------------------------------------------------------
$root = dirname(__DIR__);
$runtime = [];
foreach (['src', 'blocks', 'assets'] as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && in_array(strtolower($file->getExtension()), ['php', 'js', 'css', 'json'], true)) {
            $runtime[] = $file->getPathname();
        }
    }
}
$runtime[] = $root . '/niche-clever-interactive-core.php';
$runtime[] = $root . '/uninstall.php';

// The product name may appear only where Core openly describes the separate edition to the owner.
$mayNameTheEdition = ['src/Admin/HelpRenderer.php', 'src/Admin/SettingsPage.php'];
$forbiddenReferences = [
    'NicheCleverInteractive\\Premium', 'niche-clever-interactive-premium', 'ncip_', 'NCIP_',
    'is_plugin_active', 'get_plugins(', 'plugin_basename', 'class_exists(\'NicheCleverInteractive',
    'license_key', 'licence_key', 'activation_key', 'is_premium', 'is_pro', 'upgrade_url', 'freemius', 'edd_sl', 'trial_expires',
];
foreach ($runtime as $path) {
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    $text = (string) file_get_contents($path);
    foreach ($forbiddenReferences as $needle) {
        $assert(stripos($text, $needle) === false || $relative === 'niche-clever-interactive-core.php' && $needle === 'plugin_basename', "$relative contains '$needle'.");
    }
    if (!in_array($relative, $mayNameTheEdition, true)) {
        $assert(preg_match('/\bPremium\b/', $text) !== 1, "$relative names the separate edition; only the Help Centre and Placement notes may.");
    }
    if (str_ends_with($relative, '.php') && $relative !== 'src/AI/Transport/WordPressResponsesHttpClient.php') {
        $assert(!str_contains($text, 'wp_remote_'), "$relative makes an HTTP request; only the provider client may.");
    }
}
$main = (string) file_get_contents($root . '/niche-clever-interactive-core.php');
$assert(!preg_match('/Requires Plugins:/', $main), 'Core does not require another plugin.');

// ---- Behaviour: with no extension registered Core is exactly itself -------------------------------------------------------------
$GLOBALS['opts'] = [];
function current_user_can(string $c): bool { return $c === 'manage_options'; }
function get_option(string $n, $d = false) { return $GLOBALS['opts'][$n] ?? $d; }
function admin_url(string $p = ''): string { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg(array $q, string $u): string { return $u . '?' . http_build_query($q); }
function plugins_url(string $p = '', string $f = ''): string { return 'https://example.test/p/' . ltrim($p, '/'); }
function wp_nonce_field(string $a, string $n): void { echo '<input type="hidden" name="' . $n . '" value="nonce">'; }
$GLOBALS['menus'] = [];
function add_submenu_page(string $parent, string $title, string $menu, string $cap, string $slug, $cb = null)
{
    $GLOBALS['menus'][] = $slug;
    return 'hook-' . $slug;
}
function add_menu_page(string $title, string $menu, string $cap, string $slug, $cb = null, string $icon = '', $pos = null)
{
    $GLOBALS['menus'][] = 'top:' . $slug;
    return 'toplevel_page_' . $slug;
}

$registry = new ExtensionRegistryV1();
$directory = new ProfileDirectory($registry);
$hub = new ProfileHub($directory);
$screens = new ExtensionScreens($registry);
$assert(!$hub->active() && $directory->summaries() === [] && $directory->editor() === null && $directory->workspace() === null && $directory->sections('docs') === [], 'With no extension there is no hub, profile list or section.');
$assert(!$screens->hosted() && $screens->screenIds() === [] && $screens->bodyHtml('settings') === '' && $screens->bodyHtml('archive') === '', 'With no extension no screen is declared or hosted.');
$assert($directory->resolve('docs', CoreSettings::fromArray(CoreSettings::defaults())) === null && !$directory->hasResolver() && !$directory->hasContextProvider(), 'A named profile never resolves, and no context provider exists.');

$settingsPage = new SettingsPage(null, null, null, $hub);
$plainSettings = new SettingsPage();
$render = static function (callable $fn): string {
    ob_start();
    $fn();
    return (string) ob_get_clean();
};
foreach (['setup', 'profile', 'ai-corpus', 'conversation', 'appearance-limits', 'placement', 'diagnostics', 'help'] as $tab) {
    $_GET = ['tab' => $tab];
    $withHubObject = $render(static fn () => $settingsPage->render());
    $plain = $render(static fn () => $plainSettings->render());
    $assert($withHubObject === $plain, "The $tab tab is identical whether or not Core holds an inactive hub.");
    foreach (['ncli-admin__titlebar', 'view=profiles', 'All profiles', 'Switch profile', 'Governance', 'ncli-settings'] as $marker) {
        $assert(!str_contains($plain, $marker), "The $tab tab carries no hub element '$marker'.");
    }
}
$_GET = ['tab' => 'appearance-limits'];
$assert(str_contains($render(static fn () => $plainSettings->render()), 'provider_requests_per_day'), 'The site-wide provider allowance stays on the settings tabs.');
$_GET = ['tab' => 'conversation'];
$assert(str_contains($render(static fn () => $plainSettings->render()), 'delete_on_uninstall'), 'Delete data on uninstall stays on the Conversation tab.');
$_GET = [];
$assert(!str_contains($render(static fn () => $settingsPage->render()), 'Your profiles'), 'The bare Core address opens the settings, not a profile list.');

$GLOBALS['menus'] = [];
$settingsPage->addMenu();
$screens->addMenus();
(new SiteSettingsPage($hub, $screens))->addMenu();
$assert($GLOBALS['menus'] === ['top:ncli-core', 'ncli-core'], 'The menu is the top entry and its Settings item; nothing else.');
$assert($hub->redirectRetiredPages() === null && ($GLOBALS['menus'] === ['top:ncli-core', 'ncli-core']), 'Retired-page handling does nothing without an extension.');

$store = new class implements ConversationStore {
    public function record(ConversationTurn $turn): void {}
    public function listConversations(int $limit, int $offset): array { return []; }
    public function countConversations(): int { return 0; }
    public function turns(string $conversationId): array { return []; }
    public function deleteConversations(array $conversationIds): int { return 0; }
    public function deleteAll(): int { return 0; }
    public function purgeOlderThan(string $cutoffUtc, int $limit): int { return 0; }
};
$guard = new AdminActionGuard(static fn (): bool => true, static fn (): bool => true);
$_GET = ['tab' => 'governance'];
$conversations = $render(static fn () => (new ConversationsPage($guard, $store, $screens, $hub))->render());
$assert(str_contains($conversations, 'Conversation settings') && !str_contains($conversations, 'Governance'), 'The Conversations page has no governance tab without an extension, even if one is requested.');

$panel = (new InteractivePanel($directory))->renderInteractive([]);
$assert(str_contains($panel, 'ncli-panel') && !str_contains($panel, 'data-ncli-profile'), 'The public panel renders as the default profile only.');
$named = (new InteractivePanel($directory))->renderInteractive(['profile' => 'docs']);
$assert(str_contains($named, ' disabled') && !str_contains($named, 'data-ncli-profile'), 'A profile that no extension supplies renders disabled, never from the default corpus.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS: Core stands on its own without any extension\n";
