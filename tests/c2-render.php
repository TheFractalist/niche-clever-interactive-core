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
$settingsPage = (string) file_get_contents(dirname(__DIR__) . '/src/Admin/SettingsPage.php') . (string) file_get_contents(dirname(__DIR__) . '/src/Admin/ProfileFieldRenderer.php');
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
$assert(strpos($output, 'ncli-panel__transcript') < strpos($output, '<form') && strpos($output, '<form') < strpos($output, 'ncli-panel__reset') && strpos($output, 'ncli-panel__reset') < strpos($output, '<footer'), 'The thread must come first, then the question input, the reset control and the footer.');
$assert(str_contains($publicCss, '.ncli-panel__question {') && str_contains($publicCss, 'background: var(--ncli-q-bg, var(--ncli-surface))') && str_contains($publicCss, '.ncli-panel__answer {') && str_contains($publicCss, '.ncli-panel__transcript:empty'), 'Questions must be shaded blocks and answers separate bordered blocks, with no empty gaps.');
$panelSource = (string) file_get_contents(dirname(__DIR__) . '/src/Frontend/InteractivePanel.php');
$assert(str_contains($panelSource, "'questionLabel' => Translator::text('Question:')") && str_contains($panelSource, "'answerLabel' => Translator::text('Answer:')"), 'The panel must supply translatable hidden labels for question and answer blocks.');
$assert(str_contains($output, 'ncli-panel__reset'), 'Panel should render the session-only reset control.');
$assert(!str_contains($publicCss, '.ncli-panel__evidence'), 'The public panel must not retain evidence-link markup support.');
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
$assert(str_contains($settingsPage, "['setup', 'profile', 'ai-corpus', 'conversation', 'appearance-limits', 'placement', 'diagnostics', 'help']"), 'The administrator tab set must remain Core-bounded.');
$assert(!str_contains($settingsPage, 'approved_evidence'), 'The administrator workspace must not expose an evidence-map setting.');
$assert(str_contains($settingsPage, 'ncli-admin__masthead-art'), 'The Core workspace must render its Dashboard-style standalone masthead art.');
$assert(str_contains($settingsPage, 'ncli-admin__get-started-count'), 'The Setup tab must render the Core-safe Dashboard-style checklist summary.');
$assert(str_contains($settingsPage, 'ncli-admin__form-table'), 'Editable Core settings must use the grouped table-like workspace rows.');
$assert(str_contains($settingsPage, 'ncli-admin__tab-panel'), 'Read-only Core tabs must retain the shared workspace panel frame.');
$assert(str_contains($settingsPage, 'ncli-admin__save-bar'), 'Editable Core settings must retain the NCI save treatment.');
$assert(str_contains($settingsPage, 'ncli-admin__help-toggle'), 'Technical settings must include reusable accessible question-mark guidance.');
$assert(str_contains($settingsPage, 'renderComparisonTable'), 'The Help Centre must render a bounded Core and planned-Premium comparison.');
$assert(str_contains($settingsPage, "Translator::text"), 'Fixed administrator strings must pass through the NCI text domain.');
$assert(!str_contains($settingsPage, 'profile-picker'), 'The Core workspace must not add a profile picker.');
$assert(!str_contains($settingsPage, 'add-profile'), 'The Core workspace must not add profile creation controls.');
$adminCss = (string) file_get_contents(dirname(__DIR__) . '/assets/css/admin.css');
$assert(str_contains($adminCss, 'max-width: 1000px'), 'Administrator chrome must retain the Dashboard workspace width.');
$assert(str_contains($adminCss, 'margin: 16px 0 20px'), 'Administrator chrome must retain the standalone masthead spacing.');
$assert(str_contains($adminCss, '.ncli-admin__save-bar { position: sticky'), 'The blue save treatment must remain sticky.');
$assert(str_contains($adminCss, 'background: #123b5a'), 'Administrator chrome must retain the dashboard blue treatment.');
$assert(is_file(dirname(__DIR__) . '/tests/c2-admin-chrome.php'), 'The administrator Chrome must have a focused rendered-markup test.');
$assert(!str_contains($settingsPage, 'profile_id'), 'The Core workspace must not expose profile selection.');
$assert(!str_contains($settingsPage, 'Content-Disposition'), 'The Core workspace must not add conversation file export; governance export is Pro.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C2 render checks verified\n";
