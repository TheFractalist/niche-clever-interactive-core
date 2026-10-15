<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Budget\BucketStore;
use NicheCleverInteractive\Core\Budget\BudgetGate;
use NicheCleverInteractive\Core\Budget\ClientScopeResolver;
use NicheCleverInteractive\Core\Configuration\CoreSettings;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

if (!defined('NCLI_CORE_SCOPE_SECRET')) {
    define('NCLI_CORE_SCOPE_SECRET', 'c4-test-scope-secret');
}
if (!defined('NCLI_CORE_TRUSTED_PROXY_CIDRS')) {
    define('NCLI_CORE_TRUSTED_PROXY_CIDRS', '127.0.0.1/32');
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

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

$settings = CoreSettings::fromArray([
    'enabled' => true,
    'profile' => ['id' => 'default', 'corpus_version' => 'fixture-corpus', 'vector_store_reference' => 'vs_fixture_budget'],
    'instructions' => '',
    'response_preset' => 'balanced',
    'limits' => ['visitor_requests_per_day' => 2, 'provider_requests_per_day' => 3],
    'appearance' => ['panel_style' => 'classic'],
]);

$scopes = new ClientScopeResolver();
$untrusted = $scopes->hash(['REMOTE_ADDR' => '203.0.113.10', 'HTTP_X_FORWARDED_FOR' => '198.51.100.2']);
$direct = $scopes->hash(['REMOTE_ADDR' => '203.0.113.10']);
$trusted = $scopes->hash(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.2, 127.0.0.1']);
$assert($untrusted === $direct, 'Forwarded headers from an untrusted remote must be ignored.');
$assert($trusted !== null && $trusted !== $scopes->hash(['REMOTE_ADDR' => '127.0.0.1']), 'Configured trusted proxies may supply a validated client address.');
$assert($untrusted !== null && !str_contains($untrusted, '203.0.113.10'), 'Only the server-secret hash, not a raw IP, is used as storage scope.');

// Automatic secret: no constant is needed on a normal WordPress site.
$noConstant = 'NCLI_TEST_UNDEFINED_SCOPE_SECRET';
$derived = new ClientScopeResolver($noConstant, 'NCLI_TEST_UNDEFINED_PROXIES', static fn (): string => 'site-salt-one');
$derivedHash = $derived->hash(['REMOTE_ADDR' => '203.0.113.10']);
$assert($derived->isAvailable() && $derivedHash !== null, 'A visitor scope must derive from the WordPress salt when no constant is defined.');
$assert($derivedHash === $derived->hash(['REMOTE_ADDR' => '203.0.113.10']), 'The derived scope must be stable for one address.');
$assert($derivedHash !== $derived->hash(['REMOTE_ADDR' => '203.0.113.11']), 'Different addresses must derive different scopes.');
$assert($derivedHash !== (new ClientScopeResolver($noConstant, 'NCLI_TEST_UNDEFINED_PROXIES', static fn (): string => 'site-salt-two'))->hash(['REMOTE_ADDR' => '203.0.113.10']), 'A different site salt must derive a different scope.');
$assert($derivedHash !== $scopes->hash(['REMOTE_ADDR' => '203.0.113.10']), 'An explicit constant must take precedence over the salt-derived secret.');
$assert($derivedHash !== null && !str_contains($derivedHash, 'site-salt-one') && !str_contains($derivedHash, '203.0.113.10'), 'The derived scope must expose neither the salt nor the address.');
foreach ([null, '', '   '] as $badSalt) {
    $unavailable = new ClientScopeResolver($noConstant, 'NCLI_TEST_UNDEFINED_PROXIES', static fn (): ?string => $badSalt);
    $assert(!$unavailable->isAvailable() && $unavailable->hash(['REMOTE_ADDR' => '203.0.113.10']) === null, 'With neither a constant nor a usable salt the scope must fail closed.');
}

$gate = new BudgetGate($store, $scopes);
$first = $gate->reserve($settings, ['REMOTE_ADDR' => '203.0.113.10']);
$second = $gate->reserve($settings, ['REMOTE_ADDR' => '203.0.113.10']);
$third = $gate->reserve($settings, ['REMOTE_ADDR' => '203.0.113.10']);
$assert($first !== null && $second !== null && $third === null, 'Reservations must enforce the visitor allowance atomically at the configured boundary.');
$first?->release();
$replacement = $gate->reserve($settings, ['REMOTE_ADDR' => '203.0.113.10']);
$assert($replacement !== null, 'A pre-dispatch reservation release must restore allowance.');
$replacement?->commit();
$second?->commit();
$assert($gate->reserve($settings, ['REMOTE_ADDR' => '203.0.113.10']) === null, 'Committed requests must remain counted for the UTC day.');

$providerOnly = $gate->reserveProviderTest($settings);
$assert($providerOnly !== null, 'A connection test may reserve the global provider budget without a visitor scope.');
$providerOnly?->release();

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C4 budget and proxy-scope checks verified\n";
