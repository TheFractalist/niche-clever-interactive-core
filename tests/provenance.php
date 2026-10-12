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
$approvedAssetIds = ['nci-logo', 'nci-admin-banner'];
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
$assert($assetIds === $approvedAssetIds, 'Only the two owner-approved legacy visual assets may be listed.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: provenance manifest controls verified\n";
