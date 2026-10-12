<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Frontend\InteractivePanel;
use NicheCleverInteractive\Core\Configuration\CoreSettings;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

$panel = new InteractivePanel();
$output = $panel->renderInteractive(['title' => '<script>alert(1)</script>']);
$notice = $panel->renderLimitNotice(['message' => '<strong>Limited</strong>']);
$publicCss = (string) file_get_contents(dirname(__DIR__) . '/assets/css/public.css');
$settingsPage = (string) file_get_contents(dirname(__DIR__) . '/src/Admin/SettingsPage.php');
$darkSettings = CoreSettings::fromArray([
    'appearance' => ['panel_style' => 'editorial'],
    'presentation' => [
        'title' => '<b>Configured</b>',
        'headline' => 'A bounded headline',
        'introduction' => 'An introduction',
        'placeholder' => 'Write safely',
        'footer_left' => 'Context',
        'footer_right' => 'Answers',
    ],
]);

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(str_contains($output, 'ncli-panel--classic'), 'Default panel style should be present.');
$assert(str_contains($output, 'aria-live="polite"'), 'Panel should contain an accessible status region.');
$assert(str_contains($output, 'ncli-panel__frame'), 'Panel should render the NCI frame.');
$assert(str_contains($output, 'ncli-panel__reset'), 'Panel should render the session-only reset control.');
$assert(str_contains($publicCss, '.ncli-panel__evidence'), 'Panel stylesheet contract should include evidence markup support.');
$assert(str_contains($publicCss, '.ncli-panel__reset[hidden]'), 'The initial session reset state must remain hidden.');
$assert(str_contains($output, 'disabled'), 'Unready Core should render a disabled question form.');
$assert(!str_contains($output, '<script>'), 'Panel title must be escaped.');
$assert(str_contains($notice, '&lt;strong&gt;Limited&lt;/strong&gt;'), 'Limit notice must be escaped.');
$assert($darkSettings->toArray()['appearance']['panel_style'] === 'dark', 'The historical editorial setting must normalise to Dark.');
$assert($darkSettings->presentation()['title'] === '<b>Configured</b>', 'Presentation values must retain bounded text for escaped rendering.');
$assert(is_file(dirname(__DIR__) . '/blocks/interactive/block.json'), 'Interactive block metadata must exist.');
$assert(is_file(dirname(__DIR__) . '/assets/css/public.css'), 'Public CSS must exist.');
$assert(is_file(dirname(__DIR__) . '/assets/js/public.js'), 'Public JavaScript must exist.');
$assert(is_file(dirname(__DIR__) . '/assets/css/admin.css'), 'NCI-only admin CSS must exist.');
$assert(is_file(dirname(__DIR__) . '/assets/images/ncli-logo.png'), 'The approved NCI logo asset must exist.');
$assert(is_file(dirname(__DIR__) . '/assets/images/ncli-admin-banner.png'), 'The approved NCI banner asset must exist.');
$assert(str_contains($settingsPage, 'add_menu_page'), 'The Core workspace must register as an NCI top-level administrator page.');
$assert(str_contains($settingsPage, "'ncli-core-admin'"), 'The Core workspace must load only its NCI administrator stylesheet.');
$assert(str_contains($settingsPage, "['setup', 'profile', 'ai-evidence', 'conversation', 'appearance-limits', 'placement', 'diagnostics', 'help']"), 'The administrator tab set must remain Core-bounded.');
$assert(!str_contains($settingsPage, 'archive'), 'The Core workspace must not add archive controls.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C2 render checks verified\n";
