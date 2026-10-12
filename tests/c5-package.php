<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$bootstrap = (string) file_get_contents($root . '/niche-clever-interactive-core.php');
$script = (string) file_get_contents($root . '/scripts/package-core.ps1');
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(str_contains($bootstrap, "Version: 1.0.0-rc.1"), 'The plugin header must identify the C5 candidate version.');
$assert(str_contains($bootstrap, "NCLI_CORE_VERSION = '1.0.0-rc.1'"), 'The runtime version must match the plugin header.');
$assert(is_file($root . '/CHANGELOG.md'), 'The candidate must provide a changelog.');
$assert(is_file($root . '/readme.txt'), 'The candidate must provide WordPress package metadata.');
$readme = is_file($root . '/readme.txt') ? (string) file_get_contents($root . '/readme.txt') : '';
$assert(str_contains($readme, 'Tested up to: 7.1'), 'The package readme must record the current tested WordPress series.');
$assert(str_contains($readme, 'License: GPLv2 or later'), 'The package readme must declare its GPL-compatible licence.');
$assert(str_contains($readme, 'Stable tag: 1.0.0-rc.1'), 'The package readme stable tag must match the candidate version.');
$assert(is_file($root . '/phpcs.xml.dist'), 'The candidate source must provide its deterministic WPCS configuration.');
$assert(is_file($root . '/COPYING'), 'The candidate must provide the complete GPL v2 text.');
$copying = is_file($root . '/COPYING') ? (string) file_get_contents($root . '/COPYING') : '';
$assert(str_contains($copying, 'GNU GENERAL PUBLIC LICENSE'), 'COPYING must identify the GNU GPL.');
$assert(str_contains($copying, 'Version 2, June 1991'), 'COPYING must contain the GPL v2 text.');
$assert(str_contains($copying, 'END OF TERMS AND CONDITIONS'), 'COPYING must contain the complete GPL v2 terms.');
$assert(str_contains($script, 'Candidate output must be outside the Core repository.'), 'The package script must prohibit repository output.');
$assert(str_contains($script, "'COPYING'"), 'The package allowlist must include the complete GPL v2 text.');
$assert(str_contains($script, "'assets', 'blocks', 'src'"), 'The package allowlist must include only Core runtime directories.');
foreach (['tests', 'fixtures', 'docs', 'provenance', 'scripts', 'vendor', 'node_modules', 'wpip', 'askfolio'] as $forbidden) {
    $assert(stripos($script, $forbidden) !== false, 'The package denylist must name ' . $forbidden . '.');
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C5 package policy checks verified\n";
