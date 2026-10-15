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

$assert(str_contains($bootstrap, "Plugin Name: Niche Clever Interactive"), 'The plugin header must use the public Niche Clever Interactive name.');
$assert(str_contains($bootstrap, "Version: 1.0.0-rc.9"), 'The plugin header must identify the current local-only candidate version.');
$assert(str_contains($bootstrap, "NCLI_CORE_VERSION = '1.0.0-rc.9'"), 'The runtime version must match the plugin header.');
$assert(str_contains($bootstrap, "Text Domain: niche-clever-interactive"), 'The plugin header must use the planned public text domain.');
$assert(is_file($root . '/CHANGELOG.md'), 'The candidate must provide a changelog.');
$assert(is_file($root . '/readme.txt'), 'The candidate must provide WordPress package metadata.');
$readme = is_file($root . '/readme.txt') ? (string) file_get_contents($root . '/readme.txt') : '';
$assert(str_contains($readme, 'Tested up to: 7.1'), 'The package readme must record the current tested WordPress series.');
$assert(str_contains($readme, 'License: GPLv2 or later'), 'The package readme must declare its GPL-compatible licence.');
$assert(str_contains($readme, '=== Niche Clever Interactive ==='), 'The package readme must use the public plugin name.');
$assert(str_contains($readme, 'Stable tag: trunk'), 'The RC readme must retain trunk until a final numeric release is frozen.');
$assert(str_contains($readme, 'NCI Premium is planned as a separately supplied edition.'), 'The readme must not claim that NCI Premium is currently available.');
$assert(is_file($root . '/phpcs.xml.dist'), 'The candidate source must provide its deterministic WPCS configuration.');
$assert(is_file($root . '/COPYING'), 'The candidate must provide the complete GPL v2 text.');
$copying = is_file($root . '/COPYING') ? (string) file_get_contents($root . '/COPYING') : '';
$assert(str_contains($copying, 'GNU GENERAL PUBLIC LICENSE'), 'COPYING must identify the GNU GPL.');
$assert(str_contains($copying, 'Version 2, June 1991'), 'COPYING must contain the GPL v2 text.');
$assert(str_contains($copying, 'END OF TERMS AND CONDITIONS'), 'COPYING must contain the complete GPL v2 terms.');
$assert(str_contains($script, 'Candidate output must be outside the Core repository.'), 'The package script must prohibit repository output.');
$assert(str_contains($script, "'COPYING'"), 'The package allowlist must include the complete GPL v2 text.');
$assert(str_contains($script, "'uninstall.php'"), 'The package allowlist must include the owner-scoped uninstall routine.');
$assert(str_contains($script, "'assets', 'blocks', 'src'"), 'The package allowlist must include only Core runtime directories.');
$assert(str_contains($script, '$allowedAssetFiles'), 'The package script must define an explicit Core asset allowlist.');
$assert(str_contains($script, "'assets/images/ncli-logo.png'"), 'The package asset allowlist must name the approved NCI logo.');
$assert(str_contains($script, "'assets/images/ncli-admin-banner.png'"), 'The package asset allowlist must name the approved NCI banner.');
$assert(str_contains($script, "'assets/js/admin.js'"), 'The package asset allowlist must include the accessible administrator help behaviour.');
$assert(hash_file('sha1', $root . '/assets/images/ncli-logo.png') === 'b0631fe6fd3e19ee878851bb9529e313ca935d31', 'The packaged logo must match its approved binary content.');
$assert(hash_file('sha1', $root . '/assets/images/ncli-admin-banner.png') === '42e68f0857f5135ff4c6722b446d7b7fad3910aa', 'The packaged banner must match its approved binary content.');
$assert(str_contains($script, 'ZipArchiveMode]::Create'), 'The package script must create its ZIP with the portable archive writer.');
$assert(str_contains($script, 'non-portable path separator'), 'The package script must reject non-portable ZIP entry paths.');
foreach (['tests', 'fixtures', 'docs', 'provenance', 'scripts', 'vendor', 'node_modules', 'wordpress-org-assets', 'wpip', 'askfolio'] as $forbidden) {
    $assert(stripos($script, $forbidden) !== false, 'The package denylist must name ' . $forbidden . '.');
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C5 package policy checks verified\n";
