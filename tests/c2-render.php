<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Frontend\InteractivePanel;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

$panel = new InteractivePanel();
$output = $panel->renderInteractive(['title' => '<script>alert(1)</script>']);
$notice = $panel->renderLimitNotice(['message' => '<strong>Limited</strong>']);

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(str_contains($output, 'ncli-panel--classic'), 'Default panel style should be present.');
$assert(str_contains($output, 'aria-live="polite"'), 'Panel should contain an accessible status region.');
$assert(str_contains($output, 'disabled'), 'Unready Core should render a disabled question form.');
$assert(!str_contains($output, '<script>'), 'Panel title must be escaped.');
$assert(str_contains($notice, '&lt;strong&gt;Limited&lt;/strong&gt;'), 'Limit notice must be escaped.');
$assert(is_file(dirname(__DIR__) . '/blocks/interactive/block.json'), 'Interactive block metadata must exist.');
$assert(is_file(dirname(__DIR__) . '/assets/css/public.css'), 'Public CSS must exist.');
$assert(is_file(dirname(__DIR__) . '/assets/js/public.js'), 'Public JavaScript must exist.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C2 render checks verified\n";
