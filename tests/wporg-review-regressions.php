<?php

declare(strict_types=1);

/*
 * Guards against three defects found by cross-checking 1.0.6 against the WordPress.org reviews:
 * a block render file whose return value WordPress discards, custom escaping wrappers that review
 * tooling cannot see through, and an uninstall routine that misses a Core-owned option.
 */
$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

// The block shares the shortcode's renderer instead of a render file.
$block = json_decode((string) file_get_contents($root . '/blocks/interactive/block.json'), true);
$assert(is_array($block) && !isset($block['render']), 'block.json must not name a render file; WordPress ignores a render file\'s return value.');
$assert(!file_exists($root . '/blocks/interactive/render.php'), 'The unused block render file must not ship.');
$assert(($block['attributes']['title']['default'] ?? null) === '', 'The block title must default to empty so the owner\'s configured panel title applies.');
$panel = (string) file_get_contents($root . '/src/Frontend/InteractivePanel.php');
$assert(str_contains($panel, "'render_callback' =>") && str_contains($panel, '$this->renderInteractive($attributes)'), 'The block must render through the same callback as the shortcode.');

// No private escaping wrappers: review tooling must see esc_html()/esc_attr() at each sink.
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS));
$optionNames = [];
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $source = (string) file_get_contents($file->getPathname());
    if (preg_match('/function\s+(html|attr|escapeHtml|escapeAttribute|escape)\s*\(/', $source) || preg_match('/\$this->(html|attr|escapeHtml|escapeAttribute)\(/', $source)) {
        $failures[] = basename($file->getPathname()) . ': use esc_html()/esc_attr() directly rather than a private escaping wrapper.';
    }
    preg_match_all("/OPTION(?:_NAME)?\s*=\s*'(ncli_core_[a-z_]+)'/", $source, $matches);
    foreach ($matches[1] as $name) {
        $optionNames[$name] = true;
    }
}

// Every option Core defines is removed by an opted-in uninstall.
$uninstall = (string) file_get_contents($root . '/uninstall.php');
$assert(count($optionNames) >= 4, 'The option inventory should find Core\'s option constants.');
foreach (array_keys($optionNames) as $name) {
    $assert(str_contains($uninstall, "delete_option( '{$name}' )"), "uninstall.php must delete the Core option {$name}.");
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "PASS: WordPress.org review regressions guarded\n";
