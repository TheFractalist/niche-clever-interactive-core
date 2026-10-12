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
$assert(str_contains($script, 'Candidate output must be outside the Core repository.'), 'The package script must prohibit repository output.');
$assert(str_contains($script, "'assets', 'blocks', 'src'"), 'The package allowlist must include only Core runtime directories.');
foreach (['tests', 'fixtures', 'docs', 'provenance', 'scripts', 'vendor', 'node_modules', 'wpip', 'askfolio'] as $forbidden) {
    $assert(stripos($script, $forbidden) !== false, 'The package denylist must name ' . $forbidden . '.');
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C5 package policy checks verified\n";
