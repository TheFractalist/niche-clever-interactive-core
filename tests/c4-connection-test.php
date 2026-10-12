<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\AI\ConnectionTestService;
use NicheCleverInteractive\Core\AI\Transport\ResponseTransport;
use NicheCleverInteractive\Core\AI\Transport\TransportRequest;
use NicheCleverInteractive\Core\AI\Transport\TransportResult;
use NicheCleverInteractive\Core\Budget\BucketStore;
use NicheCleverInteractive\Core\Budget\BudgetGate;
use NicheCleverInteractive\Core\Configuration\ConnectionFingerprint;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Security\PromptPolicy;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

if (!defined('NCLI_CORE_MOCK_TRANSPORT')) {
    define('NCLI_CORE_MOCK_TRANSPORT', true);
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$settings = CoreSettings::fromArray([
    'enabled' => true,
    'profile' => [
        'id' => 'default',
        'corpus_version' => 'fixture-corpus-v1',
        'vector_store_reference' => 'vs_fixture_connection_test',
        'approved_evidence' => [],
    ],
    'instructions' => 'Bounded fixture instructions.',
    'response_preset' => 'balanced',
    'limits' => ['visitor_requests_per_day' => 3, 'provider_requests_per_day' => 2],
    'appearance' => ['panel_style' => 'classic'],
]);

$secrets = new SecretResolver('NCLI_CONNECTION_TEST_UNUSED');
$policy = new PromptPolicy();
$fingerprint = ConnectionFingerprint::forSettings($settings, $secrets, $policy, TransportMode::MOCK);
$assert(is_string($fingerprint) && preg_match('/^[a-f0-9]{64}$/', $fingerprint) === 1, 'The diagnostic fingerprint must be opaque and bounded.');
$passed = $settings->withConnectionTest('passed', (string) $fingerprint, '2026-10-12T12:00:00Z');
$assert(Readiness::evaluate($passed, $secrets)->isReady(), 'An enabled configured profile needs a current passing connection test to be ready.');

$changedCorpus = CoreSettings::fromArray(array_replace_recursive($passed->toArray(), ['profile' => ['corpus_version' => 'fixture-corpus-v2']]));
$assert(Readiness::evaluate($changedCorpus, $secrets)->state() === Readiness::STALE_CONNECTION_TEST, 'Changing the corpus version must invalidate a passing connection test.');
$changedPolicy = CoreSettings::fromArray(array_replace_recursive($passed->toArray(), ['instructions' => 'Changed bounded instructions.']));
$assert(Readiness::evaluate($changedPolicy, $secrets)->state() === Readiness::STALE_CONNECTION_TEST, 'Changing policy inputs must invalidate a passing connection test.');
$never = CoreSettings::fromArray($settings->toArray());
$assert(Readiness::evaluate($never, $secrets)->state() === Readiness::MISSING_CONNECTION_TEST, 'A profile without a current successful test must remain unavailable.');

if (!defined('NCLI_CONNECTION_TEST_KEY_A')) {
    define('NCLI_CONNECTION_TEST_KEY_A', 'fixture-key-a');
}
if (!defined('NCLI_CONNECTION_TEST_KEY_B')) {
    define('NCLI_CONNECTION_TEST_KEY_B', 'fixture-key-b');
}
$keyFingerprintA = ConnectionFingerprint::forSettings($settings, new SecretResolver('NCLI_CONNECTION_TEST_KEY_A'), $policy, TransportMode::LIVE);
$keyFingerprintB = ConnectionFingerprint::forSettings($settings, new SecretResolver('NCLI_CONNECTION_TEST_KEY_B'), $policy, TransportMode::LIVE);
$assert($keyFingerprintA !== $keyFingerprintB, 'Changing the effective server key must invalidate a passing connection test without storing the key.');

$store = new class implements BucketStore {
    /** @var array<string, array{reserved: int, committed: int}> */
    public array $rows = [];
    public function reserve(string $kind, string $scopeHash, string $periodStart, int $limit): bool {
        $key = $kind . ':' . $scopeHash . ':' . $periodStart;
        $row = $this->rows[$key] ?? ['reserved' => 0, 'committed' => 0];
        if ($row['reserved'] + $row['committed'] >= $limit) {
            return false;
        }
        ++$row['reserved'];
        $this->rows[$key] = $row;
        return true;
    }
    public function commit(string $kind, string $scopeHash, string $periodStart): void {
        $key = $kind . ':' . $scopeHash . ':' . $periodStart;
        $row = $this->rows[$key];
        $row['reserved'] = max(0, $row['reserved'] - 1);
        ++$row['committed'];
        $this->rows[$key] = $row;
    }
    public function release(string $kind, string $scopeHash, string $periodStart): void {
        $key = $kind . ':' . $scopeHash . ':' . $periodStart;
        $row = $this->rows[$key];
        $row['reserved'] = max(0, $row['reserved'] - 1);
        $this->rows[$key] = $row;
    }
};

$completed = new class implements ResponseTransport {
    public function respond(TransportRequest $request): TransportResult {
        return new TransportResult('discarded answer', true, ['discarded_citation'], $request->policyFingerprint(), $request->corpusFingerprint());
    }
};
$service = new ConnectionTestService($completed, $secrets, new BudgetGate($store), $policy, TransportMode::MOCK);
$outcome = $service->test($settings);
$assert($outcome->passed(), 'A completed File Search transport result must pass the administrator connection test.');
$assert(!str_contains(json_encode($outcome, JSON_THROW_ON_ERROR), 'discarded'), 'The test result must not retain answer or citation content.');
$keys = implode('|', array_keys($store->rows));
$assert(str_contains($keys, 'provider:'), 'The connection test must reserve the global provider bucket.');
$assert(!str_contains($keys, 'visitor:'), 'The connection test must not reserve a visitor bucket.');
$secondOutcome = $service->test($settings);
$exhaustedOutcome = $service->test($settings);
$assert($secondOutcome->passed() && !$exhaustedOutcome->passed(), 'A connection test must respect the configured provider-only budget without retrying after exhaustion.');

$incomplete = new class implements ResponseTransport {
    public function respond(TransportRequest $request): TransportResult {
        return new TransportResult('discarded answer', false, [], $request->policyFingerprint(), $request->corpusFingerprint());
    }
};
$failed = (new ConnectionTestService($incomplete, $secrets, new BudgetGate(new class implements BucketStore {
    public function reserve(string $kind, string $scopeHash, string $periodStart, int $limit): bool { return true; }
    public function commit(string $kind, string $scopeHash, string $periodStart): void {}
    public function release(string $kind, string $scopeHash, string $periodStart): void {}
}), $policy, TransportMode::MOCK))->test($settings);
$assert(!$failed->passed(), 'An incomplete File Search result must fail the connection test.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C4 connection-test/readiness/provider-budget checks verified\n";
