<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$forbidden = [
    'wpip_',
    'WPIP_',
    'wp-interactive-portfolio',
    'askfolio.php',
    'sk-',
    'BEGIN OPENAI',
];
$extensions = ['php', 'js', 'css'];
$failures = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !in_array(strtolower($file->getExtension()), $extensions, true)) {
        continue;
    }

    $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
    if ($relative === 'tests' . DIRECTORY_SEPARATOR . 'source-audit.php' || str_starts_with($relative, 'provenance' . DIRECTORY_SEPARATOR)) {
        continue;
    }

    $contents = (string) file_get_contents($file->getPathname());
    foreach ($forbidden as $needle) {
        if (stripos($contents, $needle) !== false) {
            $failures[] = $relative . ' contains forbidden token ' . $needle;
        }
    }

    if (
        in_array(strtolower($file->getExtension()), ['js', 'mjs'], true)
        && !str_starts_with($relative, 'tests' . DIRECTORY_SEPARATOR)
        && preg_match('/\.innerHTML\s*=|\.outerHTML\s*=|insertAdjacentHTML\(|document\.write\(|srcdoc\s*=|createContextualFragment\(/', $contents) === 1
    ) {
        $failures[] = $relative . ' contains a prohibited production JavaScript HTML sink';
    }
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: executable source audit verified\n";
