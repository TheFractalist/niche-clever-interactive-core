<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Admin\ExtensionMarkupPolicy;
use NicheCleverInteractive\Core\Admin\ExtensionScreens;
use NicheCleverInteractive\Core\Admin\ProfileHub;
use NicheCleverInteractive\Core\Api\V1\Contracts\ProfileEditorV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ProfileWorkspaceV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\WorkspaceExtensionV1;
use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileEditV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileSectionV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileSummaryV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceContextV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceScreenV1;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;

/*
 * This deliberately small stand-in proves that Core passes every buffered extension response to
 * wp_kses with its one policy. WordPress itself owns the full HTML parser and URL-protocol check.
 */
$ncliKsesPolicies = [];
function wp_kses(string $markup, array $allowed, ?array $protocols = null): string
{
    global $ncliKsesPolicies;
    $ncliKsesPolicies[] = $allowed;
    $markup = (string) preg_replace('#<script\b[^>]*>.*?</script>#is', '', $markup);
    $markup = (string) preg_replace('/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $markup);
    $markup = (string) preg_replace('/\s+style\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $markup);
    $markup = (string) preg_replace('/\s+(href|src)\s*=\s*(?:"javascript:[^"]*"|\'javascript:[^\']*\'|javascript:[^\s>]+)/i', '', $markup);
    return $markup;
}
function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_attr(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function current_user_can(string $capability): bool { return $capability === 'manage_options'; }
function wp_create_nonce(string $action): string { return 'nonce-' . $action; }

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}
require_once dirname(__DIR__) . '/src/autoload.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$allowed = ExtensionMarkupPolicy::allowedHtml();
$assert(isset($allowed['details'], $allowed['summary'], $allowed['input'], $allowed['textarea'], $allowed['select'], $allowed['button']), 'Hosted markup keeps the documented semantic controls.');
$assert(isset($allowed['div']['hidden'], $allowed['div']['aria-live'], $allowed['input']['aria-describedby'], $allowed['input']['data-*']), 'Hosted markup keeps bounded accessibility and data attributes.');
$assert(!isset($allowed['script'], $allowed['style']) && !isset($allowed['button']['onclick']), 'Hosted markup never permits executable tags or event attributes.');

$hostile = '<details open><summary>Help</summary><p hidden aria-live="polite" onclick="alert(1)" style="color:red">Text</p><a href="javascript:alert(1)">bad</a><input name="x" data-ncip-select-all=".pick" required><script>steal()</script></details>';
$expectedSafe = '<details open><summary>Help</summary><p hidden aria-live="polite">Text</p><a>bad</a><input name="x" data-ncip-select-all=".pick" required></details>';
$assert(ExtensionMarkupPolicy::sanitise($hostile) === $expectedSafe, 'The common policy removes hostile executable markup while retaining permitted controls.');

$screenExtension = new class () implements WorkspaceExtensionV1 {
    public function screens(): array { return [new WorkspaceScreenV1('profiles')]; }
    public function render(string $screenId, WorkspaceContextV1 $context): void
    {
        echo '<p onclick="bad()"><a href="javascript:bad()">screen</a><script>bad()</script></p>';
    }
};
$screenRegistry = new ExtensionRegistryV1();
$screenRegistry->register('workspace_extension', $screenExtension);
$screens = new ExtensionScreens($screenRegistry);
$screenHtml = $screens->bodyHtml('profiles');
$assert(str_contains($screenHtml, '<p><a>screen</a></p>') && !str_contains($screenHtml, 'script') && !str_contains($screenHtml, 'onclick') && !str_contains($screenHtml, 'javascript:'), 'Workspace screens pass buffered extension HTML through the common policy.');

$workspace = new class () implements ProfileWorkspaceV1 {
    public function sections(ProfileIdV1 $id): array { return [new ProfileSectionV1('questions', 'hostile', 'Hosted section')]; }
    public function render(string $key, ProfileIdV1 $id, WorkspaceContextV1 $context): void
    {
        echo '<form onsubmit="bad()"><button type="submit" data-ncip-confirm="Confirm">Save</button><img src="javascript:bad()" onerror="bad()"></form>';
    }
};
$editor = new class () implements ProfileEditorV1 {
    public function summaries(): array { return []; }
    public function values(ProfileIdV1 $id): ?ProfileEditV1 { return null; }
    public function create(ProfileIdV1 $id, ProfileEditV1 $edit): void {}
    public function save(ProfileIdV1 $id, ProfileEditV1 $edit): void {}
    public function delete(ProfileIdV1 $id): bool { return false; }
    public function limit(): int { return 1; }
};
$profileRegistry = new ExtensionRegistryV1();
$profileRegistry->register('profile_editor', $editor);
$profileRegistry->register('profile_workspace', $workspace);
$hub = new ProfileHub(new ProfileDirectory($profileRegistry));
ob_start();
$hub->sections('default', 'questions');
$sectionHtml = (string) ob_get_clean();
$assert(str_contains($sectionHtml, '<form><button type="submit" data-ncip-confirm="Confirm">Save</button><img></form>') && !str_contains($sectionHtml, 'onsubmit') && !str_contains($sectionHtml, 'onerror') && !str_contains($sectionHtml, 'javascript:'), 'Profile sections pass buffered extension HTML through the same common policy.');
$assert(count($ncliKsesPolicies) === 3 && $ncliKsesPolicies[1] === $allowed && $ncliKsesPolicies[2] === $allowed, 'Every exercised extension renderer uses exactly the shared allowlist.');

$extensionSource = (string) file_get_contents(dirname(__DIR__) . '/src/Admin/ExtensionScreens.php');
$hubSource = (string) file_get_contents(dirname(__DIR__) . '/src/Admin/ProfileHub.php');
$siteSource = (string) file_get_contents(dirname(__DIR__) . '/src/Admin/SiteSettingsPage.php');
$conversationSource = (string) file_get_contents(dirname(__DIR__) . '/src/Admin/ConversationsPage.php');
$assert(!str_contains($extensionSource, 'function extensionMarkup') && str_contains($extensionSource, 'ExtensionMarkupPolicy::sanitise'), 'Workspace hosting has no private policy bypass.');
$assert(str_contains($hubSource, 'wp_kses((string) ob_get_clean(), ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols())') && !str_contains($hubSource, 'phpcs:disable WordPress.Security.EscapeOutput'), 'Profile hosting has no raw buffered output or file-wide output suppression.');
$assert(
    str_contains($siteSource, 'wp_kses($body, ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols())')
    && str_contains($conversationSource, "wp_kses(\$this->screens->bodyHtml('archive'), ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols())"),
    'Settings and governance apply the shared policy again at their final extension-output sinks.'
);

// A new file-wide security suppression is a test failure, not a hidden drift.
$remainingSuppressions = [];
$sourceIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/src', FilesystemIterator::SKIP_DOTS));
foreach ($sourceIterator as $file) {
    if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
        continue;
    }
    $source = (string) file_get_contents($file->getPathname());
    $count = preg_match_all('/phpcs:disable WordPress\.Security\./', $source);
    if ($count > 0) {
        $remainingSuppressions[basename($file->getPathname())] = $count;
    }
}
$expectedSuppressions = [];
ksort($remainingSuppressions);
ksort($expectedSuppressions);
$assert($remainingSuppressions === $expectedSuppressions, 'No file-wide WPCS security suppression remains; a new broad suppression is refused.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
fwrite(STDOUT, "PASS: WordPress.org hosted-markup recovery boundaries verified\n");
