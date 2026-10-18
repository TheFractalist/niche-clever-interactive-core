<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Admin\ProfileFieldRenderer;
use NicheCleverInteractive\Core\Configuration\CoreSettings;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$values = array_replace_recursive(CoreSettings::defaults(), [
    'profile' => ['corpus_version' => 'v7 <b>', 'vector_store_reference' => 'vs_abc'],
    'appearance' => ['panel_style' => 'dark', 'accent_color' => '#123456'],
]);
$render = static function (string $tab) use ($values): string {
    ob_start();
    (new ProfileFieldRenderer())->rows($tab, $values, 'https://example.test/history');
    return (string) ob_get_clean();
};

$setup = $render('setup');
$assert(str_contains($setup, 'name="enabled"') && str_contains($setup, 'The corpus version is set on the Profile tab.') && !str_contains($setup, 'name="corpus_version"'), 'Setup draws availability and points to the Profile tab for the corpus version.');
$profileTab = $render('profile');
$assert(str_contains($profileTab, 'name="corpus_version"') && str_contains($profileTab, 'v7 &lt;b&gt;') && !str_contains($profileTab, 'v7 <b>'), 'The Profile tab draws the corpus version and escapes values.');
$ai = $render('ai-corpus');
$assert(str_contains($ai, 'name="vector_store_reference"') && str_contains($ai, 'name="model_id"') && str_contains($ai, 'name="model_timeout_seconds"'), 'AI & Corpus draws the store and every model field.');
$conversation = $render('conversation');
$assert(str_contains($conversation, 'href="https://example.test/history"') && str_contains($conversation, 'name="conversation_log_enabled"'), 'Conversation draws history settings and the history link it was given.');
$appearance = $render('appearance-limits');
$assert(str_contains($appearance, 'value="#123456"') && str_contains($appearance, 'name="provider_requests_per_day"') && str_contains($appearance, 'name="visitor_requests_per_day"'), 'Appearance & Limits draws the accent colour and both limits.');
$assert($render('diagnostics') === '' && $render('help') === '' && $render('placement') === '', 'Tabs without fields draw nothing.');
$assert(str_contains((new ProfileFieldRenderer())->renderModelTable(), 'ncli-admin__model-table'), 'The model table is available to the Help screen.');

$page = (string) file_get_contents(dirname(__DIR__) . '/src/Admin/SettingsPage.php');
$assert(str_contains($page, 'new ProfileFieldRenderer()') && !str_contains($page, 'function fieldLabel') && !str_contains($page, 'function renderModelFields'), 'SettingsPage delegates its field rows instead of keeping a second copy.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS: CC-10a profile field renderer checks verified\n";
