<?php
/**
 * Verifies an installed Niche Clever Interactive plugin folder against a
 * manifest generated from a verified expanded candidate archive. Read-only:
 * it changes nothing.
 *
 * Usage: php verify-install.php <plugin-folder> <manifest-file>
 *
 * Text files are compared after normalising CRLF to LF, so a Windows checkout
 * with autocrlf does not cause false differences. Binary files compare raw.
 */

$path = $argv[1] ?? '';
$manifestPath = $argv[2] ?? '';
if ($path === '' || !is_dir($path) || $manifestPath === '' || !is_file($manifestPath)) {
    fwrite(STDERR, "Usage: php verify-install.php <plugin-folder> <manifest-file>\n");
    exit(2);
}

$path = rtrim(str_replace('\\', '/', (string) realpath($path)), '/');
$manifestPath = (string) realpath($manifestPath);
$want = [];
$label = basename($manifestPath);
foreach (file($manifestPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (str_starts_with($line, '# ')) {
        $label = substr($line, 2);
        continue;
    }
    if (!preg_match('/^([a-f0-9]{64})  (.+)$/', $line, $matches)) {
        fwrite(STDERR, "Invalid manifest row: {$line}\n");
        exit(2);
    }
    $want[$matches[2]] = $matches[1];
}
if ($want === []) {
    fwrite(STDERR, "Manifest contains no file hashes.\n");
    exit(2);
}

$textExtensions = ['php', 'js', 'css', 'md', 'txt', 'json'];
$have = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $info) {
    if ($info->isFile()) {
        $relative = substr(str_replace('\\', '/', $info->getPathname()), strlen($path) + 1);
        $have[$relative] = $info->getPathname();
    }
}

$missing = array_diff_key($want, $have);
$extra = array_diff_key($have, $want);
$different = [];
foreach ($want as $file => $hash) {
    if (!isset($have[$file])) {
        continue;
    }
    $bytes = (string) file_get_contents($have[$file]);
    if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $textExtensions, true)) {
        $bytes = str_replace("\r\n", "\n", $bytes);
    }
    if (hash('sha256', $bytes) !== $hash) {
        $different[] = $file;
    }
}

$header = isset($have['niche-clever-interactive-core.php'])
    ? (string) file_get_contents($have['niche-clever-interactive-core.php'])
    : '';
preg_match('/^\s*\*\s*Version:\s*(\S+)/m', $header, $version);
echo 'Folder:            ' . $path . "\n";
echo 'Manifest:          ' . $label . "\n";
echo 'Header version:    ' . ($version[1] ?? '(main plugin file not found)') . "\n";
echo 'Expected files:    ' . count($want) . "\n";
echo 'Files found:       ' . count($have) . "\n";
foreach (['MISSING' => array_keys($missing), 'DIFFERENT CONTENT' => $different, 'UNEXPECTED EXTRA' => array_keys($extra)] as $heading => $files) {
    if ($files !== []) {
        sort($files);
        echo "\n{$heading} (" . count($files) . "):\n  " . implode("\n  ", $files) . "\n";
    }
}

if ($missing === [] && $different === [] && $extra === []) {
    echo "\nRESULT: MATCH.\n";
    exit(0);
}
echo "\nRESULT: DOES NOT MATCH.\n";
exit(1);
