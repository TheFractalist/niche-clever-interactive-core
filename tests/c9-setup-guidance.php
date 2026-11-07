<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Admin\SettingsPage;
use NicheCleverInteractive\Core\Admin\SetupGuidance;
use NicheCleverInteractive\Core\Configuration\ConnectionFingerprint;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Security\PromptPolicy;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

// A key must exist for readiness to reach the connection-test states; this is not a real key.
if (!defined('NCLI_OPENAI_API_KEY_DEFAULT')) {
    define('NCLI_OPENAI_API_KEY_DEFAULT', 'test-key-not-a-real-credential');
}

$ncliGuidanceOptions = [];
function current_user_can(string $capability): bool
{
    return $capability === 'manage_options';
}
function get_option(string $name, $default = false)
{
    global $ncliGuidanceOptions;
    return $ncliGuidanceOptions[$name] ?? $default;
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
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

// The vector-store step names exactly what is missing.
$assert(SetupGuidance::vectorStoreMessage(true, true) === 'A vector-store reference and corpus version are saved.', 'Both saved: say so.');
$assert(str_contains(SetupGuidance::vectorStoreMessage(true, true, true), 'for this profile'), 'A named profile\'s message names the profile.');
$assert(SetupGuidance::vectorStoreMessage(true, false) === 'The corpus version is missing. Save it in Profile.', 'Only the corpus version missing: say exactly that, and where.');
$assert(SetupGuidance::vectorStoreMessage(false, true) === 'The vector-store reference is missing. Save it in AI & Corpus.', 'Only the reference missing: say exactly that, and where.');
$assert(str_contains(SetupGuidance::vectorStoreMessage(false, false), 'vector-store reference') && str_contains(SetupGuidance::vectorStoreMessage(false, false), 'corpus version'), 'Neither saved: name both.');
$assert(str_contains(SetupGuidance::vectorStoreTitle(), 'corpus version'), 'The step title must mention the corpus version, not only the vector store.');

// Which readiness states mean "run a new test".
foreach ([Readiness::STALE_CONNECTION_TEST, Readiness::MISSING_CONNECTION_TEST] as $state) {
    $assert(SetupGuidance::needsRetest($state), $state . ' needs a new test.');
}
foreach ([Readiness::READY, Readiness::DISABLED, Readiness::MISSING_SECRET, Readiness::MISSING_SCOPE_SECRET, Readiness::MISSING_VECTOR_STORE, Readiness::MISSING_CORPUS_VERSION, 'unavailable'] as $state) {
    $assert(!SetupGuidance::needsRetest($state), $state . ' must not show the retest warning: another step comes first, or nothing is wrong.');
}

// The warning itself.
$notice = static function (string $state, string $status, string $setup = 'https://example.test/setup', string $diagnostics = 'https://example.test/diag'): string {
    ob_start();
    SetupGuidance::renderRetestNotice($state, $status, $setup, $diagnostics);
    return (string) ob_get_clean();
};
$stale = $notice(Readiness::STALE_CONNECTION_TEST, 'passed');
$assert(str_contains($stale, 'changed since the last connection test') && str_contains($stale, 'href="https://example.test/setup"') && !str_contains($stale, 'href="https://example.test/diag"'), 'A stale test explains the change and links to Setup only.');
$failed = $notice(Readiness::MISSING_CONNECTION_TEST, 'failed');
$assert(str_contains($failed, 'did not pass') && str_contains($failed, 'href="https://example.test/setup"') && str_contains($failed, 'href="https://example.test/diag"'), 'A failed test links to both Setup and Diagnostics.');
$never = $notice(Readiness::MISSING_CONNECTION_TEST, 'never');
$assert(str_contains($never, 'not passed a connection test yet') && !str_contains($never, 'href="https://example.test/diag"'), 'A test that was never run links to Setup only.');
$assert($notice(Readiness::READY, 'passed') === '' && $notice(Readiness::DISABLED, 'never') === '' && $notice(Readiness::MISSING_SECRET, 'never') === '', 'No warning when no test is needed.');
$assert(str_contains($stale, 'notice-warning'), 'The warning uses the administrator warning style.');
$hostile = $notice(Readiness::STALE_CONNECTION_TEST, 'passed', 'https://x.test/"><script>alert(1)</script>');
$assert(!str_contains($hostile, '<script>') && str_contains($hostile, '&quot;&gt;&lt;script&gt;'), 'URLs in the warning are escaped.');

// The real Settings screens.
$base = CoreSettings::defaults();
$configured = array_replace($base, [
    'enabled' => true,
    'profile' => ['id' => 'default', 'corpus_version' => 'v1', 'vector_store_reference' => 'vs_fixture_store'],
]);
$render = static function (array $stored, array $query) use (&$ncliGuidanceOptions): string {
    $ncliGuidanceOptions = [CoreSettings::OPTION_NAME => $stored];
    $_GET = $query;
    ob_start();
    (new SettingsPage())->render();
    return (string) ob_get_clean();
};

$staleSettings = CoreSettings::fromArray($configured)->withConnectionTest('passed', str_repeat('a', 64), '2026-11-07T12:00:00Z')->toArray();
$secrets = new SecretResolver();
$fingerprint = (string) ConnectionFingerprint::forSettings(CoreSettings::fromArray($configured), $secrets, new PromptPolicy(), TransportMode::current());
$readySettings = CoreSettings::fromArray($configured)->withConnectionTest('passed', $fingerprint, '2026-11-07T12:00:00Z')->toArray();
$failedSettings = CoreSettings::fromArray($configured)->withConnectionTest('failed', $fingerprint, '2026-11-07T12:00:00Z')->toArray();

$assert(Readiness::evaluate(CoreSettings::fromArray($staleSettings), $secrets)->state() === Readiness::STALE_CONNECTION_TEST, 'Fixture: a wrong fingerprint is stale.');
$assert(Readiness::evaluate(CoreSettings::fromArray($readySettings), $secrets)->isReady(), 'Fixture: a matching fingerprint is ready.');

foreach (['profile', 'ai-corpus', 'conversation', 'appearance-limits', 'placement', 'diagnostics'] as $tab) {
    $html = $render($staleSettings, ['tab' => $tab]);
    $assert(str_contains($html, 'ncli-admin__retest-notice') && str_contains($html, 'tab=setup'), 'The ' . $tab . ' tab must warn that a new test is needed and link to Setup.');
}
$assert(!str_contains($render($staleSettings, ['tab' => 'setup']), 'ncli-admin__retest-notice'), 'Setup already holds the Test connection button, so it needs no extra warning.');
$assert(!str_contains($render($staleSettings, ['tab' => 'help']), 'ncli-admin__retest-notice'), 'Help needs no warning.');
$assert(!str_contains($render($readySettings, ['tab' => 'profile']), 'ncli-admin__retest-notice'), 'No warning once the test is current.');
$assert(!str_contains($render(array_replace($configured, ['enabled' => false]), ['tab' => 'profile']), 'ncli-admin__retest-notice'), 'A disabled profile needs no warning.');
$missing = $render(array_replace($configured, ['profile' => ['id' => 'default', 'corpus_version' => '', 'vector_store_reference' => 'vs_fixture_store']]), ['tab' => 'profile']);
$assert(!str_contains($missing, 'ncli-admin__retest-notice'), 'A missing corpus version comes first; do not ask for a test yet.');
$failedHtml = $render($failedSettings, ['tab' => 'ai-corpus']);
$assert(str_contains($failedHtml, 'did not pass') && str_contains($failedHtml, 'tab=diagnostics'), 'After a failed test, the warning links to Diagnostics.');

// Saving a change shows both the saved notice and the warning.
$afterSave = $render($staleSettings, ['tab' => 'ai-corpus', 'ncli_notice' => 'saved']);
$assert(str_contains($afterSave, 'Settings saved.') && str_contains($afterSave, 'ncli-admin__retest-notice'), 'After saving a change that invalidates the test, the owner sees both messages.');

// The Setup checklist step.
$referenceOnly = $render(array_replace($configured, ['enabled' => false, 'profile' => ['id' => 'default', 'corpus_version' => '', 'vector_store_reference' => 'vs_fixture_store']]), ['tab' => 'setup']);
$assert(str_contains($referenceOnly, 'The corpus version is missing. Save it in Profile.') && str_contains($referenceOnly, 'tab=profile') && str_contains($referenceOnly, 'Choose your vector store and corpus version'), 'With only the reference saved, the step says the corpus version is missing and links to Profile.');
$corpusOnly = $render(array_replace($configured, ['enabled' => false, 'profile' => ['id' => 'default', 'corpus_version' => 'v1', 'vector_store_reference' => '']]), ['tab' => 'setup']);
$assert(str_contains($corpusOnly, 'The vector-store reference is missing. Save it in AI &amp; Corpus.') && str_contains($corpusOnly, 'tab=ai-corpus'), 'With only the corpus version saved, the step says the reference is missing and links to AI & Corpus.');
$neither = $render(array_replace($configured, ['enabled' => false, 'profile' => ['id' => 'default', 'corpus_version' => '', 'vector_store_reference' => '']]), ['tab' => 'setup']);
$assert(str_contains($neither, 'tab=ai-corpus') && str_contains($neither, 'tab=profile'), 'With neither saved, the step links to both tabs.');

// A failed connection test points at Diagnostics.
$failedNotice = $render($failedSettings, ['tab' => 'setup', 'ncli_notice' => 'connection-failed']);
$assert(str_contains($failedNotice, 'Connection test did not pass.') && str_contains($failedNotice, 'tab=diagnostics'), 'The failure notice must link to Diagnostics.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "PASS: setup guidance and the retest warning verified\n";
