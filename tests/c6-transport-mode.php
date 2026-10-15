<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

/**
 * Constants cannot be redefined inside one process, so each combination runs in a child PHP
 * process that defines only what that scenario needs and prints the resulting mode and readiness.
 *
 * @param list<string> $defines PHP define() statements
 * @return array{mode: string, readiness: string}
 */
$run = static function (array $defines) use ($root): array {
    $script = "<?php\n"
        . "define('ABSPATH', " . var_export($root . DIRECTORY_SEPARATOR, true) . ");\n"
        . "require " . var_export($root . '/tests/bootstrap.php', true) . ";\n"
        . "require " . var_export($root . '/src/autoload.php', true) . ";\n"
        . implode("\n", $defines) . "\n"
        . "use NicheCleverInteractive\\Core\\Configuration\\CoreSettings;\n"
        . "use NicheCleverInteractive\\Core\\Configuration\\Readiness;\n"
        . "use NicheCleverInteractive\\Core\\Configuration\\SecretResolver;\n"
        . "use NicheCleverInteractive\\Core\\Configuration\\TransportMode;\n"
        . "\$settings = CoreSettings::fromArray(['enabled' => true, 'profile' => ['id' => 'default', 'corpus_version' => 'v1', 'vector_store_reference' => 'vs_mode_fixture']]);\n"
        . "echo json_encode(['mode' => TransportMode::current(), 'readiness' => Readiness::evaluate(\$settings, new SecretResolver())->state()]);\n";
    $file = tempnam(sys_get_temp_dir(), 'ncli-mode');
    file_put_contents($file, $script);
    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1');
    unlink($file);
    $decoded = json_decode((string) $output, true);

    return is_array($decoded) ? $decoded : ['mode' => 'unparsed:' . $output, 'readiness' => ''];
};

$key = "define('NCLI_OPENAI_API_KEY_DEFAULT', 'test-key-not-real');";

$none = $run([]);
$assert($none['mode'] === 'unavailable' && $none['readiness'] === 'missing_secret', 'With no key and no constant the provider must be unavailable.');

$keyOnly = $run([$key]);
$assert($keyOnly['mode'] === 'live', 'Defining the API key alone must select live mode.');
$assert($keyOnly['readiness'] === 'missing_connection_test', 'With only the key defined, the next requirement must be a connection test, not a missing secret.');

$emptyKey = $run(["define('NCLI_OPENAI_API_KEY_DEFAULT', '   ');"]);
$assert($emptyKey['mode'] === 'unavailable', 'A blank key must not select live mode.');

$offSwitch = $run([$key, "define('NCLI_CORE_LIVE_TRANSPORT', false);"]);
$assert($offSwitch['mode'] === 'unavailable' && $offSwitch['readiness'] === 'missing_secret', 'NCLI_CORE_LIVE_TRANSPORT false must switch the provider off even with a key.');

$notExactlyTrue = $run([$key, "define('NCLI_CORE_LIVE_TRANSPORT', 'yes');"]);
$assert($notExactlyTrue['mode'] === 'unavailable', 'Only a boolean true may force live mode; any other defined value is treated as off.');

$forcedLive = $run(["define('NCLI_CORE_LIVE_TRANSPORT', true);"]);
$assert($forcedLive['mode'] === 'live' && $forcedLive['readiness'] === 'missing_secret', 'Forcing live mode without a key must still report a missing secret.');

$both = $run([$key, "define('NCLI_CORE_LIVE_TRANSPORT', true);"]);
$assert($both['mode'] === 'live', 'The explicit live constant must keep working beside the key.');

$mock = $run([$key, "define('NCLI_CORE_MOCK_TRANSPORT', true);"]);
$assert($mock['mode'] === 'mock', 'The mock transport must still take priority for tests.');

$source = (string) file_get_contents($root . '/src/Configuration/TransportMode.php');
$assert(!preg_match('/get_option|update_option|\$_(GET|POST|REQUEST|COOKIE)/', $source), 'Transport selection must read only server constants.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C6 transport-mode checks verified\n";
