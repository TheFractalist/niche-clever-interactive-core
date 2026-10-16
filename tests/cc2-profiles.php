<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Admin\ProfileConnectionTest;
use NicheCleverInteractive\Core\AI\ChatService;
use NicheCleverInteractive\Core\AI\ConnectionTestService;
use NicheCleverInteractive\Core\AI\Transport\DeterministicMockTransport;
use NicheCleverInteractive\Core\AI\Transport\ResponseTransport;
use NicheCleverInteractive\Core\AI\Transport\TransportRequest;
use NicheCleverInteractive\Core\AI\Transport\TransportResult;
use NicheCleverInteractive\Core\Api\V1\Contracts\ProfileResolverV1;
use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\ResolvedProfileV1;
use NicheCleverInteractive\Core\Budget\BucketStore;
use NicheCleverInteractive\Core\Budget\BudgetGate;
use NicheCleverInteractive\Core\Budget\ClientScopeResolver;
use NicheCleverInteractive\Core\Configuration\ConnectionFingerprint;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Conversation\ConversationRecorder;
use NicheCleverInteractive\Core\Conversation\ConversationStore;
use NicheCleverInteractive\Core\Conversation\ConversationTurn;
use NicheCleverInteractive\Core\Frontend\InteractivePanel;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;
use NicheCleverInteractive\Core\Profile\ProfileTestRecords;
use NicheCleverInteractive\Core\Security\AdminActionGuard;
use NicheCleverInteractive\Core\Security\PolicyFingerprint;
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

$secrets = new SecretResolver('NCLI_NOT_A_SECRET');
$policy = new PromptPolicy();

$settings = CoreSettings::fromArray([
    'enabled' => true,
    'profile' => ['id' => 'default', 'corpus_version' => 'default-corpus-1', 'vector_store_reference' => 'vs_default'],
    'instructions' => 'Default guidance.',
    'response_preset' => 'balanced',
    'limits' => ['visitor_requests_per_day' => 5, 'provider_requests_per_day' => 100],
    'conversation_log' => ['enabled' => true, 'retention_days' => 90],
]);
$settings = $settings->withConnectionTest(
    'passed',
    (string) ConnectionFingerprint::forSettings($settings, $secrets, $policy, TransportMode::MOCK),
    '2026-10-15T12:00:00Z'
);

/** In-memory bucket store honouring per-kind limits. */
$store = new class implements BucketStore {
    /** @var array<string, int> */
    public array $used = [];
    public function reserve(string $kind, string $scopeHash, string $periodStart, int $limit): bool
    {
        $key = $kind . ':' . $scopeHash;
        if (($this->used[$key] ?? 0) >= $limit) {
            return false;
        }
        $this->used[$key] = ($this->used[$key] ?? 0) + 1;
        return true;
    }
    public function commit(string $kind, string $scopeHash, string $periodStart): void
    {
    }
    public function release(string $kind, string $scopeHash, string $periodStart): void
    {
        $key = $kind . ':' . $scopeHash;
        $this->used[$key] = max(0, ($this->used[$key] ?? 0) - 1);
    }
};
$gate = new BudgetGate($store, new ClientScopeResolver('NCLI_TEST_UNDEFINED_SCOPE_SECRET', 'NCLI_TEST_UNDEFINED_PROXIES', static fn (): string => 'site-salt'));
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';

$records = new class implements ProfileTestRecords {
    /** @var array<string, array{status: string, tested_at: string, fingerprint: string}> */
    public array $rows = [];
    public function get(string $profileId): array
    {
        return $this->rows[$profileId] ?? ['status' => 'never', 'tested_at' => '', 'fingerprint' => ''];
    }
    public function put(string $profileId, string $status, string $fingerprint, string $testedAt): void
    {
        $this->rows[$profileId] = ['status' => $status, 'tested_at' => $testedAt, 'fingerprint' => $fingerprint];
    }
};

/** Mutable resolver whose behaviour each case changes. */
$resolver = new class implements ProfileResolverV1 {
    public int $calls = 0;
    /** @var list<string> */
    public array $asked = [];
    public string $mode = 'docs';
    public string $vectorStore = 'vs_docs';
    public bool $enabled = true;
    public int $limit = 2;
    public function resolve(ProfileIdV1 $profileId): ?ResolvedProfileV1
    {
        ++$this->calls;
        $this->asked[] = $profileId->value();
        switch ($this->mode) {
            case 'throw':
                throw new RuntimeException('resolver failed');
            case 'wrong-id':
                return ResolvedProfileV1::configured(new ProfileIdV1('other'), true, 'vs_x', 'c', '', 'balanced', [], 'classic', 5);
            case 'legacy':
                return new ResolvedProfileV1($profileId, true, str_repeat('a', 64), str_repeat('b', 64), 'balanced');
            case 'none':
                return null;
        }
        if ($profileId->value() !== 'docs') {
            return null;
        }
        return ResolvedProfileV1::configured(
            $profileId,
            $this->enabled,
            $this->vectorStore,
            'docs-1',
            'Speak formally about documentation.',
            'detailed',
            ['title' => 'Docs helper', 'unavailable_message' => 'Docs are offline.'],
            'dark',
            $this->limit
        );
    }
};

$registry = new ExtensionRegistryV1();
$registry->register('profile_resolution', $resolver);
$directory = new ProfileDirectory($registry, $records);
$plainDirectory = new ProfileDirectory(new ExtensionRegistryV1(), $records);

$capture = new class implements ResponseTransport {
    public ?TransportRequest $request = null;
    public int $calls = 0;
    public function respond(TransportRequest $request): TransportResult
    {
        ++$this->calls;
        $this->request = $request;
        return new TransportResult('A profile answer.', true, $request->policyFingerprint(), $request->corpusFingerprint());
    }
};
$loggingStore = new class implements ConversationStore {
    /** @var list<ConversationTurn> */
    public array $rows = [];
    public function record(ConversationTurn $turn): void { $this->rows[] = $turn; }
    public function listConversations(int $limit, int $offset): array { return []; }
    public function countConversations(): int { return 0; }
    public function turns(string $conversationId): array { return []; }
    public function deleteConversations(array $conversationIds): int { return 0; }
    public function deleteAll(): int { return 0; }
    public function purgeOlderThan(string $cutoffUtc, int $limit): int { return 0; }
};
$service = static fn (?ProfileDirectory $d, ?BudgetGate $b = null, ?ConversationRecorder $r = null): ChatService => new ChatService($capture, $secrets, null, null, $b, $r, null, $d);
$ask = static fn (ChatService $s, array $body): array => $s->handle($body, $settings)->payload();
$code = static fn (array $p): string => (string) ($p['error']['code'] ?? 'ok');

// Without a resolver a named profile is invalid; the default still works. No fallback ever.
$assert($code($ask($service($plainDirectory), ['question' => 'What is NCI?', 'profile_id' => 'docs'])) === 'invalid_request', 'Without a resolver a named profile is rejected.');
$assert($ask($service($plainDirectory), ['question' => 'What is NCI?'])['ok'] === true, 'The default profile is unaffected when no resolver is registered.');
$assert($capture->request !== null && $capture->request->vectorStoreReference() === 'vs_default', 'The default profile answers from Core\'s own settings.');

// Malformed ids are rejected before any resolver call.
$before = $resolver->calls;
foreach (['Docs', '../x', '', str_repeat('a', 65), ['docs'], 7] as $badId) {
    $assert($code($ask($service($directory), ['question' => 'What is NCI?', 'profile_id' => $badId])) === 'invalid_request', 'A malformed profile id is invalid_request.');
}
$assert($resolver->calls === $before, 'A malformed id never reaches the resolver.');

// A resolved but untested profile is unavailable.
$assert($code($ask($service($directory), ['question' => 'What is NCI?', 'profile_id' => 'docs'])) === 'profile_unavailable', 'A named profile needs its own passing connection test.');
$assert($capture->calls === 1, 'An untested profile makes no provider call.');

// Core runs the connection test and stores the result itself.
$testService = new ConnectionTestService($capture, $secrets, $gate, $policy, TransportMode::MOCK);
$outcomes = [];
$guard = new AdminActionGuard(static fn (): bool => true, static fn (string $action, string $nonce): bool => $action === 'ncli_ext_profiles' && $nonce === 'good-nonce');
$handler = new ProfileConnectionTest($directory, $testService, $guard, static fn (): CoreSettings => $settings, static function (string $outcome) use (&$outcomes): void {
    $outcomes[] = $outcome;
});
$_POST = ['_ncli_ext_nonce' => 'bad', 'profile_id' => 'docs'];
$handler->handle();
$assert($outcomes === [] && $records->rows === [], 'A request with a wrong nonce does nothing.');
$_POST = ['_ncli_ext_nonce' => 'good-nonce', 'profile_id' => 'default'];
$handler->handle();
$assert($outcomes === ['unavailable'] && $records->rows === [], 'The default profile cannot be tested through the extension handler.');
$_POST = ['_ncli_ext_nonce' => 'good-nonce', 'profile_id' => 'nope'];
$handler->handle();
$assert(end($outcomes) === 'unavailable', 'An unknown profile is unavailable.');
$_POST = ['_ncli_ext_nonce' => 'good-nonce', 'profile_id' => 'docs'];
$handler->handle();
$assert(end($outcomes) === 'passed' && ($records->rows['docs']['status'] ?? '') === 'passed', 'Core records a passing test for the profile.');
$assert($capture->request !== null && $capture->request->vectorStoreReference() === 'vs_docs', 'The test uses the profile\'s own vector store.');
$assert(!str_contains(json_encode($records->rows), 'vs_docs'), 'The stored record holds no vector-store reference.');
$assert($settings->connectionTestStatus() === 'passed', 'The default profile\'s test record is untouched.');

// Now the profile answers with its own store, guidance, preset and Core-computed fingerprints.
$answered = $ask($service($directory, $gate, new ConversationRecorder($loggingStore)), ['question' => 'What is NCI?', 'profile_id' => 'docs']);
$assert($answered['ok'] === true && $answered['answer'] === 'A profile answer.', 'A tested named profile answers.');
$request = $capture->request;
$assert($request !== null && $request->vectorStoreReference() === 'vs_docs', 'The named profile uses its own vector store.');
$assert($request !== null && $request->responsePreset() === 'detailed' && str_contains($request->instructions(), 'Speak formally about documentation.') && !str_contains($request->instructions(), 'Default guidance.'), 'The named profile uses its own guidance and preset.');
$assert($request !== null && $request->corpusFingerprint() === hash('sha256', 'docs-1') && $request->policyFingerprint() === PolicyFingerprint::create($policy, 'Speak formally about documentation.', 'detailed'), 'Fingerprints are computed by Core.');
$assert($request !== null && $request->corpusFingerprint() !== hash('sha256', 'default-corpus-1'), 'The corpus fingerprint is the profile\'s, not the default\'s.');
$assert(count($loggingStore->rows) === 1 && $loggingStore->rows[0]->profileId() === 'docs' && $loggingStore->rows[0]->corpusVersion() === 'docs-1', 'History records the profile id and its corpus version.');
$assert(!str_contains(json_encode($answered), 'vs_docs') && !str_contains(json_encode($answered), 'docs-1'), 'The public payload carries no profile configuration.');

// A client may repeat the profile's preset but not change it.
$assert($ask($service($directory), ['question' => 'What is NCI?', 'profile_id' => 'docs', 'response_preset' => 'detailed'])['ok'] === true, 'Repeating the profile preset is accepted.');
$assert($code($ask($service($directory), ['question' => 'What is NCI?', 'profile_id' => 'docs', 'response_preset' => 'concise'])) === 'invalid_request', 'Changing the profile preset is refused.');

// Fail closed in every resolver failure mode; never fall back to the default corpus.
$callsBefore = $capture->calls;
foreach (['throw', 'wrong-id', 'legacy', 'none'] as $mode) {
    $resolver->mode = $mode;
    $assert($code($ask($service($directory), ['question' => 'What is NCI?', 'profile_id' => 'docs'])) === 'profile_unavailable', "Resolver mode $mode is unavailable.");
    $assert($directory->resolve('docs', $settings) === null, "Resolver mode $mode never yields a runtime profile.");
}
$resolver->mode = 'docs';
$resolver->enabled = false;
$assert($code($ask($service($directory), ['question' => 'What is NCI?', 'profile_id' => 'docs'])) === 'profile_unavailable', 'A disabled profile is unavailable.');
$resolver->enabled = true;
$assert($capture->calls === $callsBefore, 'No failure mode reached the provider.');
$assert($code($ask($service($directory), ['question' => 'What is NCI?', 'profile_id' => 'unknown'])) === 'profile_unavailable', 'An unknown id is unavailable, not the default corpus.');

// The resolver can never supply the default profile.
$resolver->asked = [];
$ask($service($directory), ['question' => 'What is NCI?']);
$ask($service($directory), ['question' => 'What is NCI?', 'profile_id' => 'default']);
$assert(!in_array('default', $resolver->asked, true), 'The default profile is never resolved through an extension.');
$assert($directory->resolve('default', $settings)?->vectorStoreReference() === 'vs_default', 'The default profile always comes from Core settings.');

// Changing what a profile answers from makes its test stale.
$resolver->vectorStore = 'vs_docs_changed';
$assert($code($ask($service($directory), ['question' => 'What is NCI?', 'profile_id' => 'docs'])) === 'profile_unavailable', 'Changing the vector store invalidates the profile\'s connection test.');
$resolver->vectorStore = 'vs_docs';
$assert($ask($service($directory), ['question' => 'What is NCI?', 'profile_id' => 'docs'])['ok'] === true, 'Restoring it restores the passing record.');

// Per-profile visitor limit, counted separately from the default and from other profiles.
$store->used = [];
$limited = $service($directory, $gate);
$one = $ask($limited, ['question' => 'What is NCI?', 'profile_id' => 'docs']);
$two = $ask($limited, ['question' => 'What is NCI?', 'profile_id' => 'docs']);
$three = $ask($limited, ['question' => 'What is NCI?', 'profile_id' => 'docs']);
$assert($one['ok'] === true && $two['ok'] === true && $code($three) === 'allowance_exceeded', 'The profile\'s own visitor limit (2) applies.');
$defaultStill = $ask($limited, ['question' => 'What is NCI?']);
$assert($defaultStill['ok'] === true, 'Using up a profile\'s allowance does not consume the default profile\'s.');
$visitorKeys = array_filter(array_keys($store->used), static fn (string $k): bool => str_starts_with($k, 'visitor:'));
$assert(count($visitorKeys) === 2, 'Visitor allowance is kept in a separate bucket per profile.');
$assert(count(array_filter(array_keys($store->used), static fn (string $k): bool => str_starts_with($k, 'provider:'))) === 1, 'The site-wide provider allowance stays shared.');
$assert(!str_contains(implode(',', array_keys($store->used)), '203.0.113.10'), 'No bucket key contains the visitor address.');

// Profile validation.
$throws = static function (callable $fn): bool {
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return true;
    }
    return false;
};
$id = new ProfileIdV1('p1');
$assert($throws(static fn () => ResolvedProfileV1::configured($id, true, str_repeat('v', 257), 'c', '', 'balanced', [], 'classic', 5)), 'An oversized vector-store reference is rejected.');
$assert($throws(static fn () => ResolvedProfileV1::configured($id, true, 'v', 'c', str_repeat('g', 4001), 'balanced', [], 'classic', 5)), 'Oversized guidance is rejected.');
$assert($throws(static fn () => ResolvedProfileV1::configured($id, true, 'v', 'c', '', 'creative', [], 'classic', 5)), 'An unknown preset is rejected.');
$assert($throws(static fn () => ResolvedProfileV1::configured($id, true, 'v', 'c', '', 'balanced', [], 'neon', 5)), 'An unknown style is rejected.');
$assert($throws(static fn () => ResolvedProfileV1::configured($id, true, 'v', 'c', '', 'balanced', [], 'classic', 0)), 'A zero visitor limit is rejected.');
$assert($throws(static fn () => ResolvedProfileV1::configured($id, true, 'v', 'c', '', 'balanced', ['script' => 'x'], 'classic', 5)), 'An unknown wording key is rejected.');
$assert($throws(static fn () => ResolvedProfileV1::configured($id, true, 'v', 'c', '', 'balanced', ['title' => str_repeat('t', 121)], 'classic', 5)), 'Oversized wording is rejected.');
$assert($throws(static fn () => ResolvedProfileV1::configured($id, true, "v\x00", 'c', '', 'balanced', [], 'classic', 5)), 'Control characters are rejected.');
$assert((new ResolvedProfileV1($id, true, 'a', 'b', 'balanced'))->configuration() === null, 'A legacy-constructed profile carries no configuration.');

// Panels: a named profile gets its own wording, style and a profile marker; unknown ids render disabled.
$panel = new InteractivePanel($directory);
$html = $panel->renderInteractive(['profile' => 'docs']);
$assert(str_contains($html, 'data-ncli-profile="docs"') && str_contains($html, 'ncli-panel--dark') && str_contains($html, 'Docs helper') && !str_contains($html, ' disabled'), 'A ready named profile renders its own panel.');
$unknown = $panel->renderInteractive(['profile' => 'ghost']);
$assert(!str_contains($unknown, 'data-ncli-profile') && str_contains($unknown, ' disabled') && !str_contains($unknown, 'ghost'), 'An unresolved profile renders a disabled panel without echoing the id.');
$resolver->vectorStore = 'vs_stale';
$stale = $panel->renderInteractive(['profile' => 'docs']);
$assert(str_contains($stale, 'Docs are offline.') && str_contains($stale, ' disabled'), 'A profile without a current passing test renders disabled with its own message.');
$resolver->vectorStore = 'vs_docs';
$assert(!str_contains($panel->renderInteractive([]), 'data-ncli-profile'), 'The default panel carries no profile marker.');
$assert(!str_contains($panel->renderInteractive(['profile' => 'default']), 'data-ncli-profile'), 'An explicit default profile carries no marker.');
$assert(!str_contains($panel->renderInteractive(['profile' => '"><script>']), '<script>'), 'A hostile profile attribute is never echoed.');

// Client and block wiring.
$js = (string) file_get_contents(dirname(__DIR__) . '/assets/js/public.js');
$assert(str_contains($js, "data-ncli-profile") && str_contains($js, 'profile_id'), 'The public client sends profile_id from the panel.');
$block = (string) file_get_contents(dirname(__DIR__) . '/blocks/interactive/block.json');
$assert(str_contains($block, '"profile"'), 'The block declares a profile attribute.');
$assert(str_contains((string) file_get_contents(dirname(__DIR__) . '/src/Plugin.php'), 'new ChatService(') && str_contains((string) file_get_contents(dirname(__DIR__) . '/src/Plugin.php'), '$failureLog, $profiles)'), 'Plugin binds the profile directory into ChatService.');

// Capability list.
$assert((new ExtensionRegistryV1())->capabilities() === ['handshake', 'workspace_hosting', 'profile_resolution', 'viewer_access', 'supplementary_chronology', 'eligible_answer_cache', 'completed_public_turn', 'conversation_context', 'evidence_citations', 'evidence_screen', 'panel_colours', 'panel_events', 'conversation_governance', 'profile_hub', 'site_pages'], 'profile_resolution is advertised.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS: CC-2 profile wiring checks verified\n";
