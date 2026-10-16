<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\AI\ChatService;
use NicheCleverInteractive\Core\AI\Transport\OpenAiResponsesFileSearchTransport;
use NicheCleverInteractive\Core\AI\Transport\ResponseTransport;
use NicheCleverInteractive\Core\AI\Transport\ResponsesHttpClient;
use NicheCleverInteractive\Core\AI\Transport\ResponsesHttpResponse;
use NicheCleverInteractive\Core\AI\Transport\TransportRequest;
use NicheCleverInteractive\Core\AI\Transport\TransportResult;
use NicheCleverInteractive\Core\Api\V1\Contracts\AnswerCacheV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\EvidenceCitationsV1;
use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Api\V1\Value\ApprovedLinkV1;
use NicheCleverInteractive\Core\Api\V1\Value\CacheKeyV1;
use NicheCleverInteractive\Core\Api\V1\Value\CachedAnswerV1;
use NicheCleverInteractive\Core\Api\V1\Value\EvidenceCitationV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Configuration\ConnectionFingerprint;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;
use NicheCleverInteractive\Core\Profile\ProfileTestRecords;
use NicheCleverInteractive\Core\Security\PromptPolicy;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

if (!defined('NCLI_TEST_PROVIDER_KEY')) {
    define('NCLI_TEST_PROVIDER_KEY', 'fixture-provider-key');
}
if (!defined('NCLI_CORE_LIVE_TRANSPORT')) {
    define('NCLI_CORE_LIVE_TRANSPORT', true);
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$throws = static function (callable $fn): bool {
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return true;
    }
    return false;
};

// ---- EvidenceCitationV1 -------------------------------------------------------------------
$citation = new EvidenceCitationV1('file-abc_123.x:y', "  report.pdf ");
$assert($citation->reference() === 'file-abc_123.x:y' && $citation->filename() === 'report.pdf', 'A citation keeps its reference and trimmed file name.');
$assert((new EvidenceCitationV1('file-1'))->filename() === '', 'The file name is optional.');
foreach (['', 'has space', 'semi;colon', str_repeat('a', 129), "new\nline", '<b>'] as $bad) {
    $assert($throws(static fn () => new EvidenceCitationV1($bad)), 'Citation reference "' . addcslashes($bad, "\n") . '" is rejected.');
}
$assert($throws(static fn () => new EvidenceCitationV1('file-1', str_repeat('n', 256))) && $throws(static fn () => new EvidenceCitationV1('file-1', "a\x00b")), 'An oversized or control-character file name is rejected.');

// ---- ApprovedLinkV1 -----------------------------------------------------------------------
$good = new ApprovedLinkV1('  Annual report  ', 'https://example.com/path/report.pdf?year=2026#top');
$assert($good->title() === 'Annual report' && $good->url() === 'https://example.com/path/report.pdf?year=2026#top', 'A public https link is accepted and its title trimmed.');
$assert((new ApprovedLinkV1('Sub', 'https://docs.example.co.uk:8443/a'))->url() === 'https://docs.example.co.uk:8443/a', 'Subdomains and ports are accepted.');
$badUrls = [
    'http://example.com/', 'javascript:alert(1)', 'data:text/html,hi', 'ftp://example.com/', '//example.com/', 'https:///nohost', 'https://user@example.com/',
    'https://user:pw@example.com/', 'https://localhost/', 'https://127.0.0.1/', 'https://192.168.1.5/x', 'https://[::1]/', 'https://printer.local/', 'https://app.internal/',
    'https://exa mple.com/', 'https://example.com/a b', 'https://example.com/"x', 'https://example.com/<x>', "https://example.com/\x01", 'https://example', 'https://-bad.example.com/',
    'https://exämple.com/', 'HTTPS://EXAMPLE.COM/' . str_repeat('a', 2000), '',
];
foreach ($badUrls as $url) {
    $assert(!ApprovedLinkV1::isPublicHttpsUrl($url) && $throws(static fn () => new ApprovedLinkV1('T', $url)), 'URL "' . substr(addcslashes($url, "\x00..\x1F"), 0, 40) . '" is rejected.');
}
$assert(ApprovedLinkV1::isPublicHttpsUrl('HTTPS://Example.COM/Path'), 'The scheme and host are case-insensitive.');
foreach (['', str_repeat('t', 161), "T\nitle", '<b>Bold</b>', 'a<script>'] as $title) {
    $assert($throws(static fn () => new ApprovedLinkV1($title, 'https://example.com/')), 'Link title "' . addcslashes($title, "\n") . '" is rejected.');
}

// ---- Transport keeps only bounded descriptors ----------------------------------------------------
$annotations = [
    ['type' => 'file_citation', 'file_id' => 'file_one', 'filename' => 'one.pdf', 'index' => 12],
    ['type' => 'file_citation', 'file_id' => 'file_one', 'filename' => 'one.pdf', 'index' => 40],
    ['type' => 'file_citation', 'file_id' => 'file_two', 'filename' => 'two.txt'],
    ['type' => 'url_citation', 'url' => 'https://evil.example/', 'title' => 'Not a file'],
    ['type' => 'file_citation', 'file_id' => 'bad id!', 'filename' => 'x'],
    ['type' => 'file_citation', 'filename' => 'no id'],
    'garbage',
];
for ($i = 3; $i <= 14; $i++) {
    $annotations[] = ['type' => 'file_citation', 'file_id' => 'file_extra' . $i, 'filename' => 'e' . $i];
}
$payload = ['output' => [
    ['type' => 'file_search_call', 'status' => 'completed'],
    ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => "Grounded answer. \u{E200}filecite\u{E202}turn0file2\u{E201}", 'annotations' => $annotations]]],
]];
$http = new class(json_encode($payload, JSON_THROW_ON_ERROR)) implements ResponsesHttpClient {
    private string $response;
    public function __construct(string $response)
    {
        $this->response = $response;
    }
    /** @param array<string, string> $headers */
    public function post(string $url, array $headers, string $body, int $timeout): ResponsesHttpResponse
    {
        return new ResponsesHttpResponse(200, $this->response);
    }
};
$live = new OpenAiResponsesFileSearchTransport(new SecretResolver('NCLI_TEST_PROVIDER_KEY'), $http);
$result = $live->respond(new TransportRequest('Q?', (new PromptPolicy())->effectiveInstructions('', 'balanced'), 'balanced', str_repeat('a', 64), str_repeat('b', 64), 'vs_fixture_store'));
$refs = array_map(static fn (EvidenceCitationV1 $c): string => $c->reference(), $result->citations());
$assert(count($refs) === 10 && $refs[0] === 'file_one' && $refs[1] === 'file_two' && count(array_unique($refs)) === 10, 'At most ten distinct file citations are kept, in order.');
$assert(!in_array('bad id!', $refs, true) && !in_array('https://evil.example/', $refs, true), 'Malformed, URL and non-file annotations are ignored.');
$assert($result->answer() === 'Grounded answer.', 'The answer text is unchanged by citation collection.');

// ---- Wiring ---------------------------------------------------------------------------------------
$secrets = new SecretResolver('NCLI_TEST_PROVIDER_KEY');
$policy = new PromptPolicy();
$settings = CoreSettings::fromArray([
    'enabled' => true,
    'profile' => ['id' => 'default', 'corpus_version' => 'default-1', 'vector_store_reference' => 'vs_fixture_store'],
    'limits' => ['visitor_requests_per_day' => 500, 'provider_requests_per_day' => 5000],
]);
$settings = $settings->withConnectionTest('passed', (string) ConnectionFingerprint::forSettings($settings, $secrets, $policy, TransportMode::LIVE), '2026-10-16T12:00:00Z');
$records = new class implements ProfileTestRecords {
    public function get(string $profileId): array
    {
        return ['status' => 'never', 'tested_at' => '', 'fingerprint' => ''];
    }
    public function put(string $profileId, string $status, string $fingerprint, string $testedAt): void
    {
    }
};
$provider = new class implements EvidenceCitationsV1 {
    public string $mode = 'ok';
    /** @var list<list<EvidenceCitationV1>> */
    public array $seen = [];
    public function approvedLinks(ProfileIdV1 $profileId, array $citations): array
    {
        $this->seen[] = $citations;
        switch ($this->mode) {
            case 'throw':
                throw new RuntimeException('mapping store down');
            case 'wrong-type':
                return [new ApprovedLinkV1('Fine', 'https://example.com/fine'), 'oops'];
            case 'many':
                return array_map(static fn (int $i): ApprovedLinkV1 => new ApprovedLinkV1('Link ' . $i, 'https://example.com/' . $i), range(1, 9));
            case 'duplicates':
                return [new ApprovedLinkV1('A', 'https://example.com/same'), new ApprovedLinkV1('B', 'https://example.com/same'), new ApprovedLinkV1('C', 'https://example.com/other')];
            case 'none':
                return [];
            default:
                return [new ApprovedLinkV1('Annual report', 'https://example.com/report.pdf')];
        }
    }
};
$cache = new class implements AnswerCacheV1 {
    public int $stores = 0;
    public function lookup(CacheKeyV1 $key): ?CachedAnswerV1
    {
        return null;
    }
    public function store(CacheKeyV1 $key, CachedAnswerV1 $answer, string $question = ''): void
    {
        ++$this->stores;
    }
};
$registry = new ExtensionRegistryV1();
$registry->register('evidence_citations', $provider);
$chat = new ChatService($live, $secrets, null, null, null, null, null, new ProfileDirectory($registry, $records));
$bare = new ChatService($live, $secrets, null, null, null, null, null, new ProfileDirectory(new ExtensionRegistryV1(), $records));
$ask = static fn (ChatService $s): array => $s->handle(['question' => 'What is in the report?'], $GLOBALS['settings'])->payload();

$plain = $ask($bare);
$assert(array_keys($plain) === ['ok', 'answer'] && $plain['answer'] === 'Grounded answer.', 'Without the seam the payload is exactly as before: no links, no citations.');
$withLinks = $ask($chat);
$assert($withLinks['ok'] === true && $withLinks['links'] === [['title' => 'Annual report', 'url' => 'https://example.com/report.pdf']], 'Approved links are added to a successful payload.');
$assert(array_keys($withLinks) === ['ok', 'answer', 'links'], 'Only the links key is added.');
$assert(count($provider->seen[0]) === 10 && $provider->seen[0][0]->reference() === 'file_one', 'The seam receives the bounded descriptors.');
$encoded = json_encode($withLinks, JSON_THROW_ON_ERROR);
$assert(!str_contains($encoded, 'file_one') && !str_contains($encoded, 'one.pdf') && !str_contains($encoded, 'filecite') && !str_contains($encoded, "\u{E200}"),'The public payload carries no provider file reference, file name or citation marker.');

foreach (['throw', 'wrong-type', 'none'] as $mode) {
    $provider->mode = $mode;
    $res = $ask($chat);
    $assert($res['ok'] === true && !array_key_exists('links', $res) && $res['answer'] === 'Grounded answer.', "Seam mode $mode yields the plain answer with no links, never a partly trusted list.");
}
$provider->mode = 'many';
$assert(count($ask($chat)['links']) === 5, 'At most five links are shown.');
$provider->mode = 'duplicates';
$links = $ask($chat)['links'];
$assert(count($links) === 2 && $links[0]['title'] === 'A' && $links[1]['url'] === 'https://example.com/other', 'Links with the same address are shown once.');

// An answer with no citations never calls the seam.
$noCites = new OpenAiResponsesFileSearchTransport($secrets, new class implements ResponsesHttpClient {
    /** @param array<string, string> $headers */
    public function post(string $url, array $headers, string $body, int $timeout): ResponsesHttpResponse
    {
        return new ResponsesHttpResponse(200, json_encode(['output' => [['type' => 'file_search_call', 'status' => 'completed'], ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'No sources.', 'annotations' => []]]]]], JSON_THROW_ON_ERROR));
    }
});
$calls = count($provider->seen);
$provider->mode = 'ok';
$res = (new ChatService($noCites, $secrets, null, null, null, null, null, new ProfileDirectory($registry, $records)))->handle(['question' => 'Q?'], $settings)->payload();
$assert(!array_key_exists('links', $res) && count($provider->seen) === $calls, 'An answer that cited nothing makes no links and never reaches the seam.');

// Failed validation never reaches the seam or shows links.
$incomplete = new class implements ResponseTransport {
    public function respond(TransportRequest $request): TransportResult
    {
        return new TransportResult('Unverified.', false, $request->policyFingerprint(), $request->corpusFingerprint(), [new EvidenceCitationV1('file_x', 'x.pdf')]);
    }
};
$calls = count($provider->seen);
$res = (new ChatService($incomplete, $secrets, null, null, null, null, null, new ProfileDirectory($registry, $records)))->handle(['question' => 'Q?'], $settings)->payload();
$assert(($res['error']['code'] ?? '') === 'evidence_failure' && !array_key_exists('links', $res) && count($provider->seen) === $calls, 'A failed evidence check shows no links and never asks the seam.');

// A cache hit cannot show links (it has no citations) and never asks the seam; offering to the cache is unaffected.
$registryWithCache = new ExtensionRegistryV1();
$registryWithCache->register('evidence_citations', $provider);
$registryWithCache->register('eligible_answer_cache', $cache);
$cached = new ChatService($live, $secrets, null, null, null, null, null, new ProfileDirectory($registryWithCache, $records));
$cached->handle(['question' => 'Offer me?'], $settings);
$assert($cache->stores === 1, 'A link-bearing answer is still offered to the answer cache.');

// Capability and docs.
$assert(in_array('evidence_citations', (new ExtensionRegistryV1())->capabilities(), true) && in_array('evidence_citations', (new ExtensionRegistryV1())->knownSeams(), true), 'The seam is known and the capability advertised.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS: CC-6 evidence-citation checks verified\n";
