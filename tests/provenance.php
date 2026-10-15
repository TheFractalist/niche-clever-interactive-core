<?php

declare(strict_types=1);

$manifestPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'provenance' . DIRECTORY_SEPARATOR . 'nci-source-manifest.json';
$manifest = json_decode((string) file_get_contents($manifestPath), true);

if (!is_array($manifest)) {
    throw new RuntimeException('The provenance manifest must be valid JSON.');
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(($manifest['schema_version'] ?? null) === 1, 'Unsupported manifest schema.');
$assert(($manifest['protected_source']['checkpoint'] ?? null) === '1c3290f1f70f7df5193978eed3717882584e3af8', 'Protected checkpoint mismatch.');
$assert(($manifest['policy']['whole_legacy_file_copy'] ?? true) === false, 'Whole-file copying must be denied.');
$assert(($manifest['policy']['require_entry_before_adaptation'] ?? false) === true, 'Manifest entries must precede adaptation.');

$seen = [];
$approvedAssetIds = ['nci-logo'];
$assetIds = [];

foreach (($manifest['entries'] ?? []) as $entry) {
    $id = $entry['id'] ?? '';
    $assert(is_string($id) && $id !== '', 'Every entry requires an id.');
    $assert(!isset($seen[$id]), 'Duplicate provenance id: ' . $id);
    $seen[$id] = true;

    $kind = $entry['kind'] ?? '';
    $assert(in_array($kind, ['new-authored', 'adaptation', 'owner-approved-asset'], true), 'Unknown provenance kind: ' . $id);
    $assert(isset($entry['owner'], $entry['licence'], $entry['target'], $entry['method_scope'], $entry['decision_reference'], $entry['test_evidence']), 'Incomplete provenance record: ' . $id);

    if ($kind === 'adaptation') {
        $assert(($entry['direct_incorporation'] ?? true) === false, 'Adaptation cannot be direct incorporation: ' . $id);
        $assert(isset($entry['source_path'], $entry['source_blob']), 'Adaptation must pin source path/blob: ' . $id);
    }

    if ($kind === 'owner-approved-asset') {
        $assetIds[] = $id;
        $assert(($entry['direct_incorporation'] ?? false) === true, 'Approved asset must declare direct incorporation: ' . $id);
    }
}

sort($assetIds);
sort($approvedAssetIds);
$assert($assetIds === $approvedAssetIds, 'Only the one owner-approved legacy visual asset (the logo) may be listed.');

$banner = null;
foreach (($manifest['entries'] ?? []) as $entry) {
    if (($entry['id'] ?? '') === 'nci-admin-banner') {
        $banner = $entry;
    }
}
$assert($banner !== null && ($banner['kind'] ?? '') === 'new-authored' && ($banner['direct_incorporation'] ?? true) === false, 'The administrator banner must be recorded as new-authored artwork, not a legacy incorporation.');
$assert($banner !== null && ($banner['sha256'] ?? '') === hash_file('sha256', dirname(__DIR__) . '/assets/images/ncli-admin-banner.png'), 'The recorded banner SHA-256 must match the shipped file.');
$assert($banner !== null && ($banner['supersedes_sha256'] ?? '') === 'ccf545900fd67304eb96ddeee43a9b1039ac17fba287152d3d70429147c3f4b8', 'The superseded legacy banner hash must stay on record.');
$assert($banner !== null && is_string($banner['licence_declared'] ?? null) && $banner['licence_declared'] !== '' && !isset($banner['open_conditions']) && str_contains((string) ($banner['licence'] ?? ''), 'GPL-2.0-or-later'), 'The banner must record the owner licensing declaration and carry no open licensing condition.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: provenance manifest controls verified\n";
