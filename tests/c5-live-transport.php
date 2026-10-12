<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\AI\ChatService;
use NicheCleverInteractive\Core\AI\Transport\OpenAiResponsesFileSearchTransport;
use NicheCleverInteractive\Core\AI\Transport\ResponsesHttpClient;
use NicheCleverInteractive\Core\AI\Transport\ResponsesHttpResponse;
use NicheCleverInteractive\Core\AI\Transport\TransportRequest;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\SecretResolver;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

if (!defined('NCLI_TEST_PROVIDER_KEY')) {
    define('NCLI_TEST_PROVIDER_KEY', 'fixture-provider-key');
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$fileId = 'file_fixture_123';
$token = OpenAiResponsesFileSearchTransport::annotationTokenForFileId($fileId);
$responsePayload = [
    'output' => [
        ['type' => 'file_search_call', 'status' => 'completed'],
        [
            'type' => 'message',
            'content' => [[
                'type' => 'output_text',
                'text' => 'Grounded fixture answer.',
                'annotations' => [['type' => 'file_citation', 'file_id' => $fileId, 'filename' => 'private-fixture.txt']],
            ]],
        ],
    ],
];

$http = new class(json_encode($responsePayload, JSON_THROW_ON_ERROR)) implements ResponsesHttpClient {
    /** @var array<string, string> */
    public array $headers = [];
    public string $url = '';
    public string $body = '';
    private string $response;

    public function __construct(string $response)
    {
        $this->response = $response;
    }

    /** @param array<string, string> $headers */
    public function post(string $url, array $headers, string $body, int $timeout): ResponsesHttpResponse
    {
        $this->url = $url;
        $this->headers = $headers;
        $this->body = $body;
        return new ResponsesHttpResponse(200, $this->response);
    }
};

$transport = new OpenAiResponsesFileSearchTransport(new SecretResolver('NCLI_TEST_PROVIDER_KEY'), $http);
$request = new TransportRequest('What is the fixture?', 'Use approved sources only.', 'balanced', str_repeat('a', 64), str_repeat('b', 64), 'vs_fixture_store');
$result = $transport->respond($request);
$assert($http->url === 'https://api.openai.com/v1/responses', 'The live transport must use only the fixed Responses endpoint.');
$assert(($http->headers['Authorization'] ?? '') === 'Bearer fixture-provider-key', 'The provider key must be sent only as the HTTP authorization header.');
$body = json_decode($http->body, true, 512, JSON_THROW_ON_ERROR);
$assert(($body['tools'][0]['type'] ?? null) === 'file_search', 'The live transport must request File Search.');
$assert(($body['tools'][0]['vector_store_ids'][0] ?? null) === 'vs_fixture_store', 'The request must use the current Core vector-store reference.');
$assert(!array_key_exists('include', $body), 'The live transport must not request retrieved search-result text.');
$assert($result->completedFileSearch(), 'A completed provider File Search call must be recognised.');
$assert($result->annotationTokens() === [$token], 'Provider file IDs must be transformed into opaque Core annotation tokens.');
$assert(!in_array($fileId, $result->annotationTokens(), true), 'Provider file IDs must not be retained as public annotation tokens.');
$assert(!str_contains(json_encode($result, JSON_THROW_ON_ERROR), $fileId), 'The transport result must not expose a provider file identifier.');

$settings = CoreSettings::fromArray([
    'enabled' => true,
    'profile' => [
        'id' => 'default',
        'corpus_version' => 'fixture-corpus-2026-10',
        'vector_store_reference' => 'vs_fixture_store',
        'approved_evidence' => [$token => ['title' => 'Approved fixture source', 'url' => 'https://example.test/approved-source']],
    ],
    'instructions' => 'Use only current approved evidence.',
    'response_preset' => 'balanced',
    'limits' => ['visitor_requests_per_day' => 10, 'provider_requests_per_day' => 100],
    'appearance' => ['panel_style' => 'classic'],
]);
$outcome = (new ChatService($transport, new SecretResolver('NCLI_TEST_PROVIDER_KEY')))->handle(['question' => 'What is the fixture?'], $settings)->payload();
$assert($outcome['ok'] === true, 'A completed File Search response with mapped evidence must be public-safe and accepted.');
$assert(!str_contains(json_encode($outcome, JSON_THROW_ON_ERROR), $fileId), 'A public payload must not expose a provider file identifier.');

$incomplete = new OpenAiResponsesFileSearchTransport(
    new SecretResolver('NCLI_TEST_PROVIDER_KEY'),
    new class implements ResponsesHttpClient {
        /** @param array<string, string> $headers */
        public function post(string $url, array $headers, string $body, int $timeout): ResponsesHttpResponse
        {
            return new ResponsesHttpResponse(200, json_encode(['output' => [['type' => 'file_search_call', 'status' => 'in_progress'], ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'Incomplete.', 'annotations' => []]]]]], JSON_THROW_ON_ERROR));
        }
    }
);
$incompleteOutcome = (new ChatService($incomplete, new SecretResolver('NCLI_TEST_PROVIDER_KEY')))->handle(['question' => 'What is the fixture?'], $settings)->payload();
$assert(($incompleteOutcome['error']['code'] ?? null) === 'evidence_failure', 'An incomplete File Search call must fail as public evidence failure.');

$providerFailure = new OpenAiResponsesFileSearchTransport(
    new SecretResolver('NCLI_TEST_PROVIDER_KEY'),
    new class implements ResponsesHttpClient {
        /** @param array<string, string> $headers */
        public function post(string $url, array $headers, string $body, int $timeout): ResponsesHttpResponse
        {
            return new ResponsesHttpResponse(504, 'provider diagnostic content');
        }
    }
);
$providerOutcome = (new ChatService($providerFailure, new SecretResolver('NCLI_TEST_PROVIDER_KEY')))->handle(['question' => 'What is the fixture?'], $settings)->payload();
$assert(($providerOutcome['error']['code'] ?? null) === 'provider_failure', 'A provider error must remain a closed public provider failure.');
$assert(!str_contains(json_encode($providerOutcome, JSON_THROW_ON_ERROR), 'diagnostic'), 'A public provider failure must not expose provider response content.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C5 live Responses/File Search transport checks verified\n";
