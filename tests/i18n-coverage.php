<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Admin\SettingsPage;
use NicheCleverInteractive\Core\Configuration\CoreSettings;

/*
 * Every fixed string an administrator sees must reach WordPress's translation functions as a literal
 * gettext call. This marking __() wraps whatever it returns, so any visible text left unmarked after
 * rendering was printed without passing through gettext.
 */
const NCLI_I18N_OPEN = "\u{E000}";
const NCLI_I18N_CLOSE = "\u{E001}";

function __(string $text, string $domain = 'default'): string
{
    return NCLI_I18N_OPEN . $text . NCLI_I18N_CLOSE;
}

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

$ncliI18nOptions = [CoreSettings::OPTION_NAME => CoreSettings::defaults()];

function current_user_can(string $capability): bool
{
    return $capability === 'manage_options';
}

function get_option(string $name, $default = false)
{
    global $ncliI18nOptions;
    return $ncliI18nOptions[$name] ?? $default;
}

function admin_url(string $path = ''): string
{
    return 'https://example.test/wp-admin/' . $path;
}

function add_query_arg(array $query, string $url): string
{
    return $url . '?' . http_build_query($query);
}

function plugins_url(string $path = '', string $plugin = ''): string
{
    return 'https://example.test/wp-content/plugins/niche-clever-interactive/' . ltrim($path, '/');
}

function wp_nonce_field(string $action, string $name): void
{
    echo '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="test-nonce">';
}

$failures = [];

// Data and identifiers that are deliberately shown as written: model and constant names, the shortcode,
// a configuration example, the effort identifiers in the model table and bare punctuation or numbers.
$allowed = '/^(?:gpt-[\w.-]+|GPT-[\w. -]+|NCLI_[A-Z_]+|\[ncli_interactive\]|define\(.*\);|(?:none|low|medium|high|xhigh|max)(?:, (?:low|medium|high|xhigh|max))*|[\W\d_]*)$/u';

$check = static function (string $where, string $html) use (&$failures, $allowed): void {
    $html = (string) preg_replace('#<(script|style)\b.*?</\1>#s', '', $html);
    $fragments = [];
    foreach (preg_split('/<[^>]*>/', $html) ?: [] as $text) {
        $fragments[] = $text;
    }
    preg_match_all('/\s(?:aria-label|placeholder|title)="([^"]*)"/', $html, $attributes);
    foreach ($attributes[1] as $text) {
        $fragments[] = $text;
    }
    foreach ($fragments as $fragment) {
        $text = html_entity_decode($fragment, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $unmarked = (string) preg_replace('/' . NCLI_I18N_OPEN . '.*?' . NCLI_I18N_CLOSE . '/su', ' ', $text);
        $unmarked = (string) preg_replace("/define\('NCLI_[A-Z_]+', '…'\);/u", ' ', $unmarked);
        foreach (preg_split('/\s{2,}|\s*[·()–]\s*/u', trim($unmarked)) ?: [] as $piece) {
            $piece = trim($piece, " \t\n\r\0\x0B:;,.");
            if ($piece === '' || !preg_match('/\p{L}{2}/u', $piece) || preg_match($allowed, $piece)) {
                continue;
            }
            $failures[] = $where . ': "' . $piece . '"';
        }
    }
};

$render = static function (array $query): string {
    $_GET = $query;
    ob_start();
    (new SettingsPage())->render();
    return (string) ob_get_clean();
};

foreach (['setup', 'profile', 'ai-corpus', 'conversation', 'appearance-limits', 'placement', 'diagnostics'] as $tab) {
    $check('Settings ' . $tab, $render(['tab' => $tab]));
}
foreach (['overview', 'faq', 'premium'] as $section) {
    $check('Help ' . $section, $render(['tab' => 'help', 'help' => $section]));
}

// No pass-through helper may stand between a fixed string and gettext, in any Core source file.
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/src', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $source = (string) file_get_contents($file->getPathname());
    if (preg_match('/function\s+t\s*\(\s*string\s+\$text\s*\)\s*:\s*string\s*\{\s*return\s+\$text;\s*\}/', $source) || str_contains($source, '$this->t(')) {
        $failures[] = basename($file->getPathname()) . ': an identity t() helper hides strings from gettext extraction.';
    }
}

$failures = array_values(array_unique($failures));
if ($failures !== []) {
    fwrite(STDERR, "FAIL: " . count($failures) . " untranslatable administrator strings\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "PASS: every rendered administrator string reaches a literal gettext call\n";
