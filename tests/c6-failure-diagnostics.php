<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\AI\ChatService;
use NicheCleverInteractive\Core\AI\ConnectionTestService;
use NicheCleverInteractive\Core\AI\Transport\OpenAiResponsesFileSearchTransport;
use NicheCleverInteractive\Core\AI\Transport\ProviderFailure;
use NicheCleverInteractive\Core\AI\Transport\RequestOptions;
use NicheCleverInteractive\Core\AI\Transport\ResponseTransport;
use NicheCleverInteractive\Core\AI\Transport\ResponsesHttpClient;
use NicheCleverInteractive\Core\AI\Transport\ResponsesHttpResponse;
use NicheCleverInteractive\Core\AI\Transport\TransportRequest;
use NicheCleverInteractive\Core\AI\Transport\TransportResult;
use NicheCleverInteractive\Core\Admin\SettingsPage;
use NicheCleverInteractive\Core\Budget\BucketStore;
use NicheCleverInteractive\Core\Budget\BudgetGate;
use NicheCleverInteractive\Core\Configuration\ConnectionFingerprint;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Diagnostics\FailureLog;
use NicheCleverInteractive\Core\Diagnostics\OptionFailureLog;
use NicheCleverInteractive\Core\Security\PromptPolicy;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

if (!defined('NCLI_CORE_MOCK_TRANSPORT')) {
    define('NCLI_CORE_MOCK_TRANSPORT', true);
}

// Minimal option store so OptionFailureLog can be exercised outside WordPress.
$GLOBALS['c6_options'] = [];
if (!function_exists('update_option')) {
    function update_option($name, $value, $autoload = null)
    {
        $GLOBALS['c6_options'][$name] = ['value' => $value, 'autoload' => $autoload];
        return true;
    }
}
if (!function_exists('get_option')) {
    function get_option($name, $default = false)
    {
        return $GLOBALS['c6_options'][$name]['value'] ?? $default;
    }
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

// --- Reason classification -------------------------------------------------
$assert(ProviderFailure::fromHttpStatus(429)->reason() === ProviderFailure::HTTP_429, '429 must be recorded as its own reason.');
$assert(ProviderFailure::fromHttpStatus(401)->reason() === ProviderFailure::HTTP_4XX, 'Other 4xx statuses must be a client-error class.');
$assert(ProviderFailure::fromHttpStatus(503)->reason() === ProviderFailure::HTTP_5XX, '5xx statuses must be a server-error class.');
$assert(ProviderFailure::fromHttpStatus(302)->reason() === ProviderFailure::HTTP_OTHER, 'Other statuses must be recorded generically.');
$assert(ProviderFailure::fromTransportError('cURL error 28: Operation timed out after 90005 milliseconds with 0 bytes received')->reason() === ProviderFailure::TIMEOUT, 'A cURL timeout must be recorded as a timeout.');
$assert(ProviderFailure::fromTransportError('cURL error 6: Could not resolve host: api.openai.com')->reason() === ProviderFailure::CONNECTION_ERROR, 'Other transport errors must be a connection error.');
$assert((new ProviderFailure('not-a-real-reason'))->reason() === ProviderFailure::UNEXPECTED_ERROR, 'An unknown reason must collapse to the unexpected-error code.');
$assert(!str_contains(ProviderFailure::fromTransportError('cURL error 6: Could not resolve host: api.openai.com')->getMessage(), 'openai'), 'The failure must not retain the underlying error text.');
$assert(ProviderFailure::reasonFor(new RuntimeException('secret provider detail')) === ProviderFailure::UNEXPECTED_ERROR, 'A foreign exception must not leak into the recorded reason.');
foreach ([ProviderFailure::TIMEOUT, ProviderFailure::HTTP_429, ProviderFailure::EMPTY_ANSWER, ProviderFailure::ANSWER_TOO_LONG, ProviderFailure::MALFORMED_RESPONSE] as $reason) {
    $assert(ProviderFailure::label($reason) !== '' && ProviderFailure::advice($reason) !== '', 'Every reason needs a label and advice: ' . $reason);
}

// --- The live transport maps provider responses to reasons -----------------
if (!defined('NCLI_C6_TEST_PROVIDER_KEY')) {
    define('NCLI_C6_TEST_PROVIDER_KEY', 'test-key-for-pure-checks');
}
$fakeHttp = static function (callable $behaviour): ResponsesHttpClient {
    return new class($behaviour) implements ResponsesHttpClient {
        /** @var callable */
        private $behaviour;
        public function __construct(callable $behaviour)
        {
            $this->behaviour = $behaviour;
        }
        public function post(string $url, array $headers, string $body, int $timeout): ResponsesHttpResponse
        {
            return ($this->behaviour)();
        }
    };
};
$request = new TransportRequest('Question?', 'Instructions.', 'balanced', 'policy', 'corpus', 'vs_c6_fixture_store', new RequestOptions());
$reasonFor = static function (callable $behaviour) use ($fakeHttp, $request): string {
    $transport = new OpenAiResponsesFileSearchTransport(new SecretResolver('NCLI_C6_TEST_PROVIDER_KEY'), $fakeHttp($behaviour));
    try {
        $transport->respond($request);
    } catch (Throwable $exception) {
        return ProviderFailure::reasonFor($exception);
    }
    return 'none';
};
$assert($reasonFor(static fn () => new ResponsesHttpResponse(429, '{"error":"limit"}')) === ProviderFailure::HTTP_429, 'A 429 response must be recorded as http_429.');
$assert($reasonFor(static fn () => new ResponsesHttpResponse(401, '{}')) === ProviderFailure::HTTP_4XX, 'A 401 response must be recorded as http_4xx.');
$assert($reasonFor(static fn () => new ResponsesHttpResponse(502, '')) === ProviderFailure::HTTP_5XX, 'A 502 response must be recorded as http_5xx.');
$assert($reasonFor(static fn () => new ResponsesHttpResponse(200, 'not json')) === ProviderFailure::MALFORMED_RESPONSE, 'A body that is not JSON must be recorded as malformed.');
$assert($reasonFor(static fn () => new ResponsesHttpResponse(200, '"text"')) === ProviderFailure::MALFORMED_RESPONSE, 'A JSON body that is not an object must be recorded as malformed.');
$assert($reasonFor(static fn () => new ResponsesHttpResponse(200, '{"status":"completed"}')) === ProviderFailure::INCOMPLETE_RESPONSE, 'A reply without output must be recorded as incomplete.');
$assert($reasonFor(static function () { throw ProviderFailure::fromTransportError('cURL error 28: timed out'); }) === ProviderFailure::TIMEOUT, 'A client timeout must pass through as a timeout.');
$unconfigured = new OpenAiResponsesFileSearchTransport(new SecretResolver('NCLI_C6_UNDEFINED_KEY'), $fakeHttp(static fn () => new ResponsesHttpResponse(200, '{}')));
try {
    $unconfigured->respond($request);
    $assert(false, 'A missing key must stop the request.');
} catch (Throwable $exception) {
    $assert(ProviderFailure::reasonFor($exception) === ProviderFailure::NOT_CONFIGURED, 'A missing key must be recorded as not configured.');
}

// --- ChatService records the reason without changing the visitor response ---
$memory = new class implements FailureLog {
    /** @var list<array{string, string, int}> */
    public array $records = [];
    public bool $throws = false;
    public function record(string $source, string $reason, int $elapsedSeconds): void
    {
        if ($this->throws) {
            throw new RuntimeException('storage down');
        }
        $this->records[] = [$source, $reason, $elapsedSeconds];
    }
    public function last(): ?array
    {
        return null;
    }
};

$settings = CoreSettings::fromArray([
    'enabled' => true,
    'profile' => ['id' => 'default', 'corpus_version' => 'fixture-corpus-2026-10', 'vector_store_reference' => 'vs_c6_fixture_store'],
    'instructions' => 'Use the configured corpus only.',
    'response_preset' => 'balanced',
    'limits' => ['visitor_requests_per_day' => 10, 'provider_requests_per_day' => 100],
    'appearance' => ['panel_style' => 'classic'],
]);
$secrets = new SecretResolver('NCLI_NOT_A_SECRET');
$settings = $settings->withConnectionTest(
    'passed',
    (string) ConnectionFingerprint::forSettings($settings, $secrets, new PromptPolicy(), TransportMode::MOCK),
    '2026-10-15T12:00:00Z'
);

$transportReturning = static function (callable $make): ResponseTransport {
    return new class($make) implements ResponseTransport {
        /** @var callable */
        private $make;
        public function __construct(callable $make)
        {
            $this->make = $make;
        }
        public function respond(TransportRequest $request): TransportResult
        {
            return ($this->make)($request);
        }
    };
};
$run = static function (callable $make) use ($transportReturning, $secrets, $settings, $memory): array {
    $memory->records = [];
    $payload = (new ChatService($transportReturning($make), $secrets, null, null, null, null, $memory))->handle(['question' => 'Is this a test?'], $settings)->payload();
    return [$payload, $memory->records];
};

[$payload, $records] = $run(static function (TransportRequest $r): TransportResult {
    throw ProviderFailure::fromTransportError('cURL error 28: Operation timed out');
});
$assert($payload['ok'] === false && $payload['error']['code'] === 'provider_failure', 'A timeout must still look like a generic provider failure to the visitor.');
$assert($records === [['public', ProviderFailure::TIMEOUT, 0]] || (count($records) === 1 && $records[0][0] === 'public' && $records[0][1] === ProviderFailure::TIMEOUT), 'A timeout must be recorded with its reason.');
$assert(!str_contains(json_encode($payload, JSON_THROW_ON_ERROR), 'timeout') && !str_contains(json_encode($payload, JSON_THROW_ON_ERROR), 'timed out'), 'The visitor response must not reveal the reason.');

[$payload, $records] = $run(static fn (TransportRequest $r): TransportResult => new TransportResult('', true, $r->policyFingerprint(), $r->corpusFingerprint()));
$assert(count($records) === 1 && $records[0][1] === ProviderFailure::EMPTY_ANSWER, 'An empty answer must be recorded as empty_answer.');
[$payload, $records] = $run(static fn (TransportRequest $r): TransportResult => new TransportResult(str_repeat('a', 5001), true, $r->policyFingerprint(), $r->corpusFingerprint()));
$assert(count($records) === 1 && $records[0][1] === ProviderFailure::ANSWER_TOO_LONG && $payload['error']['code'] === 'provider_failure', 'An over-long answer must be recorded as answer_too_long.');
[$payload, $records] = $run(static fn (TransportRequest $r): TransportResult => new TransportResult('Answer.', false, $r->policyFingerprint(), $r->corpusFingerprint()));
$assert(count($records) === 1 && $records[0][1] === ProviderFailure::EVIDENCE_MISMATCH && $payload['error']['code'] === 'evidence_failure', 'An incomplete search must be recorded as evidence_mismatch.');
[$payload, $records] = $run(static function (TransportRequest $r): TransportResult {
    throw new RuntimeException('contains secret detail');
});
$assert(count($records) === 1 && $records[0][1] === ProviderFailure::UNEXPECTED_ERROR, 'A non-provider exception must be recorded only as unexpected_error.');
[$payload, $records] = $run(static fn (TransportRequest $r): TransportResult => new TransportResult('A good answer.', true, $r->policyFingerprint(), $r->corpusFingerprint()));
$assert($payload['ok'] === true && $records === [], 'A successful answer must record nothing.');
$memory->throws = true;
[$payload] = $run(static function (TransportRequest $r): TransportResult {
    throw new ProviderFailure(ProviderFailure::HTTP_5XX);
});
$assert($payload['ok'] === false && $payload['error']['code'] === 'provider_failure', 'A diagnostics storage failure must not alter the visitor response.');
$memory->throws = false;

// --- The connection test records its own failures ---------------------------
$openStore = new class implements BucketStore {
    public function reserve(string $kind, string $scopeHash, string $periodStart, int $limit): bool { return true; }
    public function commit(string $kind, string $scopeHash, string $periodStart): void {}
    public function release(string $kind, string $scopeHash, string $periodStart): void {}
};
$memory->records = [];
$failing = $transportReturning(static function (TransportRequest $r): TransportResult {
    throw new ProviderFailure(ProviderFailure::HTTP_429);
});
$result = (new ConnectionTestService($failing, $secrets, new BudgetGate($openStore), new PromptPolicy(), TransportMode::MOCK, $memory))->test($settings);
$assert(!$result->passed() && count($memory->records) === 1 && $memory->records[0][0] === 'connection_test' && $memory->records[0][1] === ProviderFailure::HTTP_429, 'A failed connection test must record its reason under its own source.');

// --- The stored option is minimal and sanitised -----------------------------
$log = new OptionFailureLog();
$assert($log->last() === null, 'With nothing stored, no problem is reported.');
$log->record('public', ProviderFailure::TIMEOUT, 61);
$stored = $GLOBALS['c6_options'][OptionFailureLog::OPTION_NAME] ?? null;
$assert($stored !== null && $stored['autoload'] === false, 'The failure record must not be autoloaded.');
$assert($stored !== null && array_keys($stored['value']) === ['source', 'reason', 'at', 'elapsed'], 'Only source, reason, time and duration may be stored.');
$last = $log->last();
$assert($last !== null && $last['reason'] === ProviderFailure::TIMEOUT && $last['elapsed'] === 61 && preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $last['at']) === 1, 'The latest failure must read back with a UTC time and duration.');
$log->record('<script>', 'invented reason', 999999);
$last = $log->last();
$assert($last !== null && $last['source'] === 'public' && $last['reason'] === ProviderFailure::UNEXPECTED_ERROR && $last['elapsed'] === 3600, 'Unknown values must be sanitised and the duration bounded.');
$GLOBALS['c6_options'][OptionFailureLog::OPTION_NAME]['value'] = ['reason' => ['x'], 'at' => 5];
$assert($log->last() === null, 'A tampered option must be ignored.');
$GLOBALS['c6_options'][OptionFailureLog::OPTION_NAME]['value'] = ['source' => 'connection_test', 'reason' => '<img onerror=1>', 'at' => '2026-10-15T10:00:00Z', 'elapsed' => 'many'];
$last = $log->last();
$assert($last !== null && $last['reason'] === ProviderFailure::UNEXPECTED_ERROR && $last['elapsed'] === 0 && $last['source'] === 'connection_test', 'Hostile stored text must never be passed on.');

// --- The Diagnostics screen shows the reason, escaped ------------------------
$shown = static function (?array $entry): string {
    $stub = new class($entry) implements FailureLog {
        private ?array $entry;
        public function __construct(?array $entry)
        {
            $this->entry = $entry;
        }
        public function record(string $source, string $reason, int $elapsedSeconds): void {}
        public function last(): ?array
        {
            return $this->entry;
        }
    };
    $page = new SettingsPage(null, null, $stub);
    $method = new ReflectionMethod($page, 'renderLastFailure');
    $method->setAccessible(true);
    ob_start();
    $method->invoke($page);
    return (string) ob_get_clean();
};
$none = $shown(null);
$assert(str_contains($none, 'No provider problem has been recorded'), 'The screen must say when nothing has been recorded.');
$html = $shown(['source' => 'public', 'reason' => ProviderFailure::TIMEOUT, 'at' => '2026-10-15T15:10:14Z', 'elapsed' => 90]);
$assert(str_contains($html, 'did not reply in time') && str_contains($html, 'timeout') && str_contains($html, '2026-10-15T15:10:14Z') && str_contains($html, '90') && str_contains($html, 'What to try'), 'The screen must show the reason, time, duration and advice.');
$assert(str_contains($shown(['source' => 'connection_test', 'reason' => ProviderFailure::HTTP_429, 'at' => '2026-10-15T15:10:14Z', 'elapsed' => 1]), 'connection test'), 'The screen must say when the problem came from the connection test.');
$assert(!str_contains($html, '<script') && !str_contains(strtolower($html), 'openai'), 'The screen must not include markup or provider names from stored data.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C6 failure-diagnostics checks verified\n";
