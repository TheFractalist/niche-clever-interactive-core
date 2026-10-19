<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\AI\ChatService;
use NicheCleverInteractive\Core\AI\Transport\OpenAiResponsesFileSearchTransport;
use NicheCleverInteractive\Core\AI\Transport\ProviderFailure;
use NicheCleverInteractive\Core\AI\Transport\ResponsesHttpResponse;
use NicheCleverInteractive\Core\AI\Transport\ResponsesStreamReader;
use NicheCleverInteractive\Core\AI\Transport\ResponseTransport;
use NicheCleverInteractive\Core\AI\Transport\StreamingResponsesHttpClient;
use NicheCleverInteractive\Core\AI\Transport\StreamingResponseTransport;
use NicheCleverInteractive\Core\AI\Transport\TransportRequest;
use NicheCleverInteractive\Core\AI\Transport\TransportResult;
use NicheCleverInteractive\Core\Configuration\ConnectionFingerprint;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Rest\ChatRoute;
use NicheCleverInteractive\Core\Rest\EventStream;
use NicheCleverInteractive\Core\Security\PromptPolicy;

$ncliStreamOptions = [];
if (!function_exists('get_option')) {
    function get_option($name, $default = false)
    {
        global $ncliStreamOptions;

        return $ncliStreamOptions[$name] ?? $default;
    }
}

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

if (!defined('NCLI_TEST_PROVIDER_KEY')) {
    define('NCLI_TEST_PROVIDER_KEY', 'fixture-provider-key');
}
if (!defined('NCLI_CORE_MOCK_TRANSPORT')) {
    define('NCLI_CORE_MOCK_TRANSPORT', true);
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

// ---- Event helpers -------------------------------------------------------------------------------
$sse = static fn (array $event): string => 'event: ' . $event['type'] . "\ndata: " . json_encode($event, JSON_UNESCAPED_UNICODE) . "\n\n";
$searchDone = ['type' => 'response.output_item.done', 'item' => ['type' => 'file_search_call', 'status' => 'completed']];
$delta = static fn (string $text): array => ['type' => 'response.output_text.delta', 'delta' => $text];
$final = static fn (string $text): array => ['type' => 'response.completed', 'response' => ['output' => [
    ['type' => 'file_search_call', 'status' => 'completed'],
    ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $text, 'annotations' => []]]],
]]];

$run = static function (string $stream, int $slice, int $max = 20000): array {
    $got = [];
    $reader = new ResponsesStreamReader(static function (string $t) use (&$got): void {
        $got[] = $t;
    }, $max);
    foreach (str_split($stream, $slice) as $part) {
        $reader->feed($part);
    }
    $reader->finish();

    return ['text' => implode('', $got), 'reader' => $reader, 'pieces' => count($got)];
};

// ---- Reader: chunking, gate, markers, cap --------------------------------------------------------
$stream = $sse($searchDone) . $sse($delta('Hello ')) . $sse($delta('wor')) . $sse($delta('ld.')) . $sse($final('Hello world.'));
foreach ([1, 7, 64, 100000] as $slice) {
    $r = $run($stream, $slice);
    $assert($r['text'] === 'Hello world.', 'Streamed text must be identical whatever the chunk size (slice ' . $slice . ').');
    $assert($r['reader']->completedResponse() !== null, 'The completed response must be kept (slice ' . $slice . ').');
}
$assert($run(str_replace("\n", "\r\n", $stream), 5)['text'] === 'Hello world.', 'CRLF event framing must be read the same way.');

$early = $sse($delta('Early ')) . $sse($delta('text.')) . $sse($searchDone) . $sse($delta(' Later.')) . $sse($final('Early text. Later.'));
$r = $run($early, 3);
$assert($r['text'] === 'Early text. Later.', 'Text that arrives before retrieval completes is held, then released once it has.');

$neverSearched = $run($sse($delta('Ungrounded text.')) . $sse($final('Ungrounded text.')), 4);
$assert($neverSearched['text'] === '', 'No text may be shown when retrieval never completed.');

$marker = "A fact. \u{E200}filecite\u{E202}turn0file2\u{E202}turn0file4\u{E201} Next fact.";
foreach ([1, 5, 1000] as $slice) {
    $pieces = [];
    foreach (str_split($marker, 6) as $p) {
        $pieces[] = $sse($delta($p));
    }
    $r = $run($sse($searchDone) . implode('', $pieces) . $sse($final('A fact. Next fact.')), $slice);
    $assert(strpos($r['text'], "\u{E200}") === false && strpos($r['text'], 'filecite') === false && strpos($r['text'], 'turn0file') === false, 'Provider citation markers must never reach the browser, even when split across chunks (slice ' . $slice . ').');
    $assert(strpos($r['text'], 'A fact.') === 0 && strpos($r['text'], 'Next fact.') !== false, 'Text around a removed marker must be kept.');
}
$assert($run($sse($searchDone) . $sse($delta("\n\n  Leading space.")) . $sse($final('x')), 9)['text'] === 'Leading space.', 'Leading whitespace is trimmed from the first text.');

$long = str_repeat('x', 30);
$r = $run($sse($searchDone) . $sse($delta($long)) . $sse($delta($long)) . $sse($final('x')), 10, 50);
$assert(strlen($r['text']) <= 50 && $r['text'] !== '', 'Streamed display text is capped at the configured byte limit.');

$failedStream = $run($sse($searchDone) . $sse($delta('Part')) . $sse(['type' => 'response.failed']), 6);
$assert($failedStream['reader']->failed() && $failedStream['reader']->completedResponse() === null, 'A provider failure is recorded and no completed response is kept.');
$assert($run("data: not json\n\n" . $sse($searchDone) . $sse($delta('ok')) . "data: [DONE]\n\n", 3)['text'] === 'ok', 'Malformed and terminator events are ignored.');

// ---- Transport: streaming and non-streaming give the same result ---------------------------------
$fileId = 'file_fixture_123';
$fullPayload = ['output' => [
    ['type' => 'file_search_call', 'status' => 'completed'],
    ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => "Grounded answer. \u{E200}filecite\u{E202}turn0file2\u{E201}", 'annotations' => [['type' => 'file_citation', 'file_id' => $fileId, 'filename' => 'f.txt']]]]],
]];

$completed = ['type' => 'response.completed', 'response' => $fullPayload];
$client = new class ($sse($searchDone) . $sse($delta('Grounded ')) . $sse($delta('answer. ')) . $sse($completed), $fullPayload) implements StreamingResponsesHttpClient {
    public string $body = '';
    /** @var array<string, string> */
    public array $headers = [];
    public int $status = 200;
    public string $errorBody = '';
    public string $override = '';
    private string $sseData;
    /** @var array<string, mixed> */
    private array $payload;

    /** @param array<string, mixed> $payload */
    public function __construct(string $sseData, array $payload)
    {
        $this->sseData = $sseData;
        $this->payload = $payload;
    }

    public function post(string $url, array $headers, string $body, int $timeout): ResponsesHttpResponse
    {
        return new ResponsesHttpResponse(200, json_encode($this->payload, JSON_THROW_ON_ERROR));
    }

    public function postStream(string $url, array $headers, string $body, int $timeout, callable $onChunk): ResponsesHttpResponse
    {
        $this->body = $body;
        $this->headers = $headers;
        if ($this->status < 200 || $this->status >= 300) {
            return new ResponsesHttpResponse($this->status, $this->errorBody);
        }
        foreach (str_split($this->override !== '' ? $this->override : $this->sseData, 13) as $piece) {
            $onChunk($piece);
        }

        return new ResponsesHttpResponse($this->status, '');
    }
};

$transport = new OpenAiResponsesFileSearchTransport(new SecretResolver('NCLI_TEST_PROVIDER_KEY'), $client);
$request = new TransportRequest('What is the fixture?', 'Instructions.', 'balanced', str_repeat('a', 64), str_repeat('b', 64), 'vs_fixture_store');
$shown = [];
$streamed = $transport->respondStreaming($request, static function (string $t) use (&$shown): void {
    $shown[] = $t;
});
$plain = $transport->respond($request);
$assert($streamed->answer() === $plain->answer() && $streamed->answer() === 'Grounded answer.', 'The streamed result must equal the ordinary result: the completed response, with citation markers removed.');
$assert($streamed->completedFileSearch() && $streamed->policyFingerprint() === $plain->policyFingerprint() && $streamed->corpusFingerprint() === $plain->corpusFingerprint(), 'A streamed result keeps the retrieval flag and both fingerprints.');
$assert(count($streamed->citations()) === 1, 'Citations are still taken from the completed response only.');
$assert(implode('', $shown) === 'Grounded answer. ', 'Display text is passed to the sink as it is read.');
$decoded = json_decode($client->body, true, 512, JSON_THROW_ON_ERROR);
$assert(($decoded['stream'] ?? null) === true && ($decoded['tools'][0]['type'] ?? '') === 'file_search', 'The streamed request is the ordinary request plus stream:true, with File Search.');
$assert(($client->headers['Accept'] ?? '') === 'text/event-stream' && ($client->headers['Authorization'] ?? '') === 'Bearer fixture-provider-key', 'The key goes only in the authorization header and the stream is requested.');

$client->status = 429;
$client->errorBody = '{"error":{"message":"rate"}}';
$thrown = null;
$shown = [];
try {
    $transport->respondStreaming($request, static function (string $t) use (&$shown): void {
        $shown[] = $t;
    });
} catch (ProviderFailure $e) {
    $thrown = $e;
}
$assert($thrown !== null && $shown === [], 'A non-2xx provider response fails with a closed reason and shows no text.');
$client->status = 200;

$client->override = $sse($searchDone) . $sse($delta('Half an ans')) . $sse(['type' => 'response.failed']);
$thrown = null;
$shown = [];
try {
    $transport->respondStreaming($request, static function (string $t) use (&$shown): void {
        $shown[] = $t;
    });
} catch (ProviderFailure $e) {
    $thrown = $e;
}
$assert($thrown !== null, 'A stream that ends in a provider failure is an incomplete response, never an answer.');
$client->override = '';

$plainOnly = new class implements \NicheCleverInteractive\Core\AI\Transport\ResponsesHttpClient {
    public function post(string $url, array $headers, string $body, int $timeout): ResponsesHttpResponse
    {
        return new ResponsesHttpResponse(200, json_encode(['output' => [
            ['type' => 'file_search_call', 'status' => 'completed'],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Whole answer.', 'annotations' => []]]],
        ]], JSON_THROW_ON_ERROR));
    }
};
$shown = [];
$fallback = (new OpenAiResponsesFileSearchTransport(new SecretResolver('NCLI_TEST_PROVIDER_KEY'), $plainOnly))->respondStreaming($request, static function (string $t) use (&$shown): void {
    $shown[] = $t;
});
$assert($fallback->answer() === 'Whole answer.' && $shown === [], 'Without a streaming client the transport answers normally and reports no early text.');

// ---- ChatService: validation and outcome are unchanged by streaming ------------------------------
$settings = CoreSettings::fromArray([
    'enabled' => true,
    'profile' => ['id' => 'default', 'corpus_version' => 'fixture-corpus-2026-10', 'vector_store_reference' => 'vs_fixture_retrieval'],
    'instructions' => 'Use the configured corpus only.',
    'response_preset' => 'balanced',
    'limits' => ['visitor_requests_per_day' => 10, 'provider_requests_per_day' => 100],
    'appearance' => ['panel_style' => 'classic'],
]);
$settings = $settings->withConnectionTest(
    'passed',
    (string) ConnectionFingerprint::forSettings($settings, new SecretResolver('NCLI_NOT_A_SECRET'), new PromptPolicy(), TransportMode::MOCK),
    '2026-10-12T12:00:00Z'
);

$scripted = new class implements StreamingResponseTransport {
    public string $mode = 'ok';
    public int $streamedCalls = 0;
    public int $plainCalls = 0;

    public function respond(TransportRequest $request): TransportResult
    {
        $this->plainCalls++;

        return new TransportResult('Final answer.', true, $request->policyFingerprint(), $request->corpusFingerprint());
    }

    public function respondStreaming(TransportRequest $request, callable $onText): TransportResult
    {
        $this->streamedCalls++;
        $onText('Final ');
        $onText('answer.');
        if ($this->mode === 'fail') {
            throw new ProviderFailure(ProviderFailure::INCOMPLETE_RESPONSE);
        }
        if ($this->mode === 'unverified') {
            return new TransportResult('Final answer.', false, $request->policyFingerprint(), $request->corpusFingerprint());
        }
        if ($this->mode === 'mismatch') {
            return new TransportResult('Final answer.', true, str_repeat('0', 64), $request->corpusFingerprint());
        }

        return new TransportResult('Final answer.', true, $request->policyFingerprint(), $request->corpusFingerprint());
    }
};
$service = new ChatService($scripted, new SecretResolver('NCLI_NOT_A_SECRET'));
$got = [];
$outcome = $service->handle(['question' => 'Stream this?'], $settings, [], static function (string $t) use (&$got): void {
    $got[] = $t;
});
$assert($outcome->isSuccess() && $outcome->payload()['answer'] === 'Final answer.' && implode('', $got) === 'Final answer.' && $scripted->streamedCalls === 1, 'A streamed answer is delivered as text, then returned as the same validated outcome.');
$plainOutcome = $service->handle(['question' => 'No stream?'], $settings);
$assert($plainOutcome->payload() === $outcome->payload() && $scripted->plainCalls === 1, 'Without a sink the ordinary path is used and the payload is identical.');
foreach (['fail' => 'provider_failure', 'unverified' => 'evidence_failure', 'mismatch' => 'evidence_failure'] as $mode => $code) {
    $scripted->mode = $mode;
    $got = [];
    $bad = $service->handle(['question' => 'Stream ' . $mode . '?'], $settings, [], static function (string $t) use (&$got): void {
        $got[] = $t;
    });
    $assert(!$bad->isSuccess() && $bad->errorCode() === $code, 'A streamed answer that fails validation (' . $mode . ') ends in the public error ' . $code . ', even after text was shown.');
}
$scripted->mode = 'ok';

$ncliStreamOptions[CoreSettings::OPTION_NAME] = $settings->toArray();

// ---- Route: event order and fallbacks ------------------------------------------------------------
$lines = [];
$route = new ChatRoute($service, static function () use (&$lines): EventStream {
    return new EventStream(static function (string $line) use (&$lines): void {
        $lines[] = $line;
    });
});
$_SERVER['HTTP_ACCEPT'] = 'text/event-stream, application/json';
$request = new class {
    /** @return array<string, string> */
    public function get_json_params(): array
    {
        return ['question' => 'Route stream question?'];
    }

    public function get_header(string $name): string
    {
        return $name === 'accept' ? 'text/event-stream, application/json' : '';
    }
};
$route->respond($request);
$events = array_map(static fn (string $l): array => json_decode(substr(trim($l), 6), true), $lines);
$types = array_column($events, 'type');
$assert($types === ['delta', 'delta', 'done'], 'A streamed route response is the text events followed by exactly one final event.');
$assert(($events[2]['ok'] ?? null) === true && ($events[2]['answer'] ?? '') === 'Final answer.', 'The final event carries the complete validated answer.');

$scripted->mode = 'fail';
$lines = [];
$route->respond($request);
$events = array_map(static fn (string $l): array => json_decode(substr(trim($l), 6), true), $lines);
$last = end($events);
$assert(($last['type'] ?? '') === 'error' && ($last['ok'] ?? null) === false && isset($last['error']['code']), 'A failure after text was sent ends with one error event carrying the public error.');
$scripted->mode = 'ok';

$lines = [];
$invalid = new class {
    /** @return array<string, string> */
    public function get_json_params(): array
    {
        return ['question' => ''];
    }

    public function get_header(string $name): string
    {
        return $name === 'accept' ? 'text/event-stream' : '';
    }
};
$response = $route->respond($invalid);
$assert($lines === [] && is_array($response) && ($response['ok'] ?? null) === false, 'A request rejected before any text is an ordinary JSON error, not a stream.');

$lines = [];
$plainRequest = new class {
    /** @return array<string, string> */
    public function get_json_params(): array
    {
        return ['question' => 'Plain JSON question?'];
    }

    public function get_header(string $name): string
    {
        return $name === 'accept' ? 'application/json' : '';
    }
};
unset($_SERVER['HTTP_ACCEPT']);
$before = $scripted->streamedCalls;
$plainResponse = $route->respond($plainRequest);
$assert($lines === [] && $scripted->streamedCalls === $before && is_array($plainResponse) && ($plainResponse['ok'] ?? null) === true, 'A client that does not ask for a stream gets the ordinary JSON answer from the non-streaming path.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C8 streaming reader, transport, service and route verified\n";
