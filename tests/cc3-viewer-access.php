<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\AI\ChatService;
use NicheCleverInteractive\Core\AI\Transport\ResponseTransport;
use NicheCleverInteractive\Core\AI\Transport\TransportRequest;
use NicheCleverInteractive\Core\AI\Transport\TransportResult;
use NicheCleverInteractive\Core\Api\V1\Contracts\ProfileResolverV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ViewerAccessV1;
use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Api\V1\Value\AccessDecisionV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\ResolvedProfileV1;
use NicheCleverInteractive\Core\Budget\BucketStore;
use NicheCleverInteractive\Core\Budget\BudgetGate;
use NicheCleverInteractive\Core\Budget\ClientScopeResolver;
use NicheCleverInteractive\Core\Configuration\ConnectionFingerprint;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Frontend\InteractivePanel;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;
use NicheCleverInteractive\Core\Profile\ProfileRuntime;
use NicheCleverInteractive\Core\Profile\ProfileTestRecords;
use NicheCleverInteractive\Core\Rest\ChatRoute;
use NicheCleverInteractive\Core\Security\PromptPolicy;
use NicheCleverInteractive\Core\Security\PublicError;

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
$throws = static function (callable $fn): bool {
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return true;
    }
    return false;
};

// Decision value.
$assert(AccessDecisionV1::open()->isAllowed() && !AccessDecisionV1::open()->isRestricted(), 'Open is allowed and unrestricted.');
$assert(AccessDecisionV1::granted()->isAllowed() && AccessDecisionV1::granted()->isRestricted(), 'Granted is allowed and restricted.');
$assert(!AccessDecisionV1::denied()->isAllowed() && AccessDecisionV1::denied()->isRestricted() && AccessDecisionV1::denied()->message() === null, 'Denied is refused, restricted and has no message by default.');
$assert(AccessDecisionV1::denied('  Sign in first  ')->message() === 'Sign in first', 'A message is trimmed.');
$assert(AccessDecisionV1::denied('   ')->message() === null, 'A blank message means Core\'s wording.');
$assert($throws(static fn () => AccessDecisionV1::denied(str_repeat('m', 301))), 'An oversized message is rejected.');
$assert($throws(static fn () => AccessDecisionV1::denied("bad\x00message")), 'A message with control characters is rejected.');
$assert(AccessDecisionV1::denied(str_repeat('m', 300))->message() === str_repeat('m', 300), 'A message of exactly 300 characters is accepted.');

// Public error: only access_required carries an owner message, reduced to plain text.
$assert((new PublicError('access_required'))->payload()['message'] === 'Access to this service has not been granted.', 'access_required has a default message.');
$assert((new PublicError('access_required', '<b>Sign</b> in <script>alert(1)</script>'))->payload()['message'] === 'Sign in alert(1)', 'An access message is reduced to plain text.');
$assert(!str_contains((new PublicError('access_required', "<p>x</p>\n\t"))->payload()['message'], '<'), 'Markup never survives.');
$assert((new PublicError('access_required', str_repeat('m', 301)))->payload()['message'] === 'Access to this service has not been granted.', 'An oversized message falls back to the default.');
$assert((new PublicError('profile_unavailable', 'Injected'))->payload()['message'] === 'This interactive service is unavailable.', 'No other code accepts an owner message.');
$statusFor = new ReflectionMethod(ChatRoute::class, 'statusFor');
$statusFor->setAccessible(true);
$assert($statusFor->invoke(new ChatRoute(new ChatService(new NicheCleverInteractive\Core\AI\Transport\UnavailableTransport())), 'access_required') === 403, 'access_required maps to HTTP 403.');

// Registry.
$registry = new ExtensionRegistryV1();
$assert(in_array('viewer_access', $registry->knownSeams(), true) && in_array('viewer_access', $registry->capabilities(), true), 'viewer_access is a known seam and an advertised capability.');
$wrong = false;
try {
    $registry->register('viewer_access', new class {
    });
} catch (InvalidArgumentException $e) {
    $wrong = true;
}
$assert($wrong, 'An object that is not a ViewerAccessV1 cannot be registered for the seam.');

/** Controllable access seam. */
$access = new class implements ViewerAccessV1 {
    public string $mode = 'granted';
    /** @var list<string> */
    public array $asked = [];
    public function decide(ProfileIdV1 $profileId): AccessDecisionV1
    {
        $this->asked[] = $profileId->value();
        switch ($this->mode) {
            case 'open':
                return AccessDecisionV1::open();
            case 'denied':
                return AccessDecisionV1::denied('Members only. <a href="/join">Join</a>');
            case 'denied-default':
                return AccessDecisionV1::denied();
            case 'throw':
                throw new RuntimeException('membership service down');
            case 'null':
                return null; // TypeError against the contract
            default:
                return AccessDecisionV1::granted();
        }
    }
};
$resolver = new class implements ProfileResolverV1 {
    public function resolve(ProfileIdV1 $profileId): ?ResolvedProfileV1
    {
        return $profileId->value() === 'docs'
            ? ResolvedProfileV1::configured($profileId, true, 'vs_docs', 'docs-1', '', 'balanced', ['title' => 'Docs helper'], 'classic', 5)
            : null;
    }
};
$full = new ExtensionRegistryV1();
$full->register('profile_resolution', $resolver);
$full->register('viewer_access', $access);
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
$directory = new ProfileDirectory($full, $records);
$plain = new ProfileDirectory($registry, $records);

$secrets = new SecretResolver('NCLI_NOT_A_SECRET');
$policy = new PromptPolicy();
$settings = CoreSettings::fromArray([
    'enabled' => true,
    'profile' => ['id' => 'default', 'corpus_version' => 'default-1', 'vector_store_reference' => 'vs_default'],
    'limits' => ['visitor_requests_per_day' => 5, 'provider_requests_per_day' => 100],
]);
$settings = $settings->withConnectionTest('passed', (string) ConnectionFingerprint::forSettings($settings, $secrets, $policy, TransportMode::MOCK), '2026-10-15T12:00:00Z');
$docs = $directory->resolve('docs', $settings);
$assert($docs instanceof ProfileRuntime, 'The fixture profile resolves.');
$defaultRuntime = ProfileRuntime::fromSettings($settings);

// Directory: default never reaches the seam; no seam means open; failures deny.
$access->asked = [];
$assert($directory->access($defaultRuntime)->isAllowed() && !$directory->access($defaultRuntime)->isRestricted() && $access->asked === [], 'The default profile is always open and never reaches the seam.');
$assert(!$plain->access($docs)->isRestricted() && $plain->access($docs)->isAllowed(), 'With no access seam registered a named profile is open, as before.');
foreach (['granted' => true, 'open' => true, 'denied' => false, 'denied-default' => false, 'throw' => false, 'null' => false] as $mode => $allowed) {
    $access->mode = $mode;
    $assert($directory->access($docs)->isAllowed() === $allowed, "Seam mode $mode is " . ($allowed ? 'allowed' : 'denied') . '.');
}

// Fixture for the chat path.
$open = new class implements BucketStore {
    /** @var array<string, int> */
    public array $used = [];
    public function reserve(string $kind, string $scopeHash, string $periodStart, int $limit): bool
    {
        $this->used[$kind . $scopeHash] = ($this->used[$kind . $scopeHash] ?? 0) + 1;
        return true;
    }
    public function commit(string $kind, string $scopeHash, string $periodStart): void
    {
    }
    public function release(string $kind, string $scopeHash, string $periodStart): void
    {
    }
};
$gate = new BudgetGate($open, new ClientScopeResolver('NCLI_TEST_UNDEFINED_SCOPE_SECRET', 'NCLI_TEST_UNDEFINED_PROXIES', static fn (): string => 'site-salt'));
$transport = new class implements ResponseTransport {
    public int $calls = 0;
    public function respond(TransportRequest $request): TransportResult
    {
        ++$this->calls;
        return new TransportResult('A protected answer.', true, $request->policyFingerprint(), $request->corpusFingerprint());
    }
};
$chat = new ChatService($transport, $secrets, null, null, $gate, null, null, $directory);
$ask = static fn (array $body): array => $chat->handle($body, $settings)->payload();
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';

// An untested profile that the viewer may not use reports access, not its setup state.
$access->mode = 'denied';
$denied = $ask(['question' => 'What is NCI?', 'profile_id' => 'docs']);
$assert(($denied['error']['code'] ?? '') === 'access_required' && $denied['error']['message'] === 'Members only. Join', 'A denied viewer gets access_required with the owner\'s plain-text message.');
$assert($transport->calls === 0 && $open->used === [], 'A denial makes no provider call and reserves no allowance.');
$access->mode = 'denied-default';
$assert($ask(['question' => 'What is NCI?', 'profile_id' => 'docs'])['error']['message'] === 'Access to this service has not been granted.', 'A denial without a message uses Core\'s wording.');
$access->mode = 'throw';
$assert(($ask(['question' => 'What is NCI?', 'profile_id' => 'docs'])['error']['code'] ?? '') === 'access_required', 'A failing access check denies.');
$access->mode = 'null';
$assert(($ask(['question' => 'What is NCI?', 'profile_id' => 'docs'])['error']['code'] ?? '') === 'access_required', 'An invalid access result denies.');
$assert($transport->calls === 0, 'No failure reached the provider.');

// A permitted viewer still needs the profile tested; once tested, they are answered.
$access->mode = 'granted';
$assert(($ask(['question' => 'What is NCI?', 'profile_id' => 'docs'])['error']['code'] ?? '') === 'profile_unavailable', 'A granted viewer of an untested profile sees it as unavailable.');
$records->put('docs', 'passed', (string) ConnectionFingerprint::forProfile($docs, $secrets, $policy, TransportMode::MOCK), '2026-10-15T12:00:00Z');
$answer = $ask(['question' => 'What is NCI?', 'profile_id' => 'docs']);
$assert($answer['ok'] === true && $answer['answer'] === 'A protected answer.' && $transport->calls === 1, 'A granted viewer of a tested profile is answered.');
$access->mode = 'denied';
$callsBefore = $transport->calls;
$assert(($ask(['question' => 'What is NCI?', 'profile_id' => 'docs'])['error']['code'] ?? '') === 'access_required' && $transport->calls === $callsBefore, 'Revoking access takes effect at once.');

// The default profile is untouched by the seam.
$access->asked = [];
$assert($ask(['question' => 'What is NCI?'])['ok'] === true && $access->asked === [], 'The default profile answers without consulting the access seam.');

// Panels.
$panel = new InteractivePanel($directory);
$assert(!defined('DONOTCACHEPAGE'), 'No page has been marked uncacheable yet.');
$access->mode = 'open';
$openHtml = $panel->renderInteractive(['profile' => 'docs']);
$assert(str_contains($openHtml, 'data-ncli-profile="docs"') && !defined('DONOTCACHEPAGE'), 'An open profile renders normally and stays cacheable.');
$access->mode = 'denied';
$deniedHtml = $panel->renderInteractive(['profile' => 'docs']);
$assert(!str_contains($deniedHtml, 'data-ncli-profile') && str_contains($deniedHtml, ' disabled') && str_contains($deniedHtml, 'Members only. Join') && !str_contains($deniedHtml, '<a href="/join">'), 'A denied profile renders a disabled panel with the plain-text message and no profile marker.');
$assert(defined('DONOTCACHEPAGE') && DONOTCACHEPAGE === true, 'A restricted profile marks the page uncacheable.');
$access->mode = 'throw';
$assert(str_contains($panel->renderInteractive(['profile' => 'docs']), 'Access to this service has not been granted.'), 'A failing access check renders the default access message.');
$access->mode = 'granted';
$grantedHtml = $panel->renderInteractive(['profile' => 'docs']);
$assert(str_contains($grantedHtml, 'data-ncli-profile="docs"') && !str_contains($grantedHtml, ' disabled'), 'A granted, tested profile renders an enabled panel.');
$assert(!str_contains($panel->renderInteractive([]), 'Access to this service'), 'The default panel never shows an access message.');

// Client and nonce wiring.
$js = (string) file_get_contents(dirname(__DIR__) . '/assets/js/public.js');
$assert(str_contains($js, 'X-WP-Nonce') && str_contains($js, 'if (profile && window.NCLI_CORE_CHAT.nonce)'), 'The client sends the REST nonce only for a named profile.');
$source = (string) file_get_contents(dirname(__DIR__) . '/src/Frontend/InteractivePanel.php');
$assert(str_contains($source, "wp_create_nonce('wp_rest')"), 'The panel exposes the REST nonce to the client.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS: CC-3 viewer access checks verified\n";
