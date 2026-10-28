<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\AI\ChatOutcome;
use NicheCleverInteractive\Core\Api\V1\Value\CompletedTurnV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\RequestContextV1;
use NicheCleverInteractive\Core\Budget\ClientScopeResolver;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Rest\RequestEnvironment;
use NicheCleverInteractive\Core\Security\ChatRequestValidator;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

// ---- Cached-answer flag ---------------------------------------------------------------------
$assert(ChatOutcome::success('A')->payload() === ['ok' => true, 'answer' => 'A'], 'A provider answer carries no cached flag.');
$assert(ChatOutcome::success('A', [], true)->payload() === ['ok' => true, 'answer' => 'A', 'cached' => true], 'A reused answer is flagged.');
$assert(!array_key_exists('cached', ChatOutcome::failure('provider_failure')->payload()), 'A failure is never flagged.');

// ---- Optional visitor identifier ------------------------------------------------------------
$validator = new ChatRequestValidator();
$settings = CoreSettings::fromArray(CoreSettings::defaults());
$id = str_repeat('ab', 16);
$ok = $validator->validate(['question' => 'Hello there?', 'visitor_id' => $id], $settings);
$assert($ok !== null && $ok['visitor_id'] === $id, 'A well-formed visitor identifier is passed through.');
$assert($validator->validate(['question' => 'Hello there?'], $settings)['visitor_id'] === null, 'An absent visitor identifier is null.');
foreach (['short', strtoupper($id), $id . 'a', 12, ['x']] as $bad) {
    $assert($validator->validate(['question' => 'Hello there?', 'visitor_id' => $bad], $settings) === null, 'A malformed visitor identifier invalidates the request.');
}

// ---- Client address uses the existing trusted-proxy rule -----------------------------------
$resolver = new ClientScopeResolver();
$assert($resolver->clientAddress(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7']) === '203.0.113.9', 'Forwarded headers are ignored without a trusted proxy.');
$assert($resolver->clientAddress(['REMOTE_ADDR' => 'not-an-ip']) === null && $resolver->clientAddress([]) === null, 'An invalid or missing address is null.');
$assert($resolver->clientAddress(['REMOTE_ADDR' => '2001:db8::1']) === '2001:db8::1', 'IPv6 addresses are accepted.');

// ---- Minimal request environment ------------------------------------------------------------
$environment = RequestEnvironment::fromServer([
    'REMOTE_ADDR' => '203.0.113.9',
    'HTTP_X_FORWARDED_FOR' => '198.51.100.7',
    'HTTP_USER_AGENT' => "  Agent/1.0\r\nInjected  ",
    'HTTP_ACCEPT' => 'application/json, text/event-stream',
    'HTTP_COOKIE' => 'must-not-cross-the-seam',
    'SCRIPT_FILENAME' => '/private/path.php',
]);
$assert($environment->clientScopeServer() === ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'], 'Only address-resolution fields cross the request-environment boundary.');
$assert($environment->userAgent() === 'Agent/1.0Injected', 'A User-Agent is normalised once and cannot retain CRLF controls.');
$assert($environment->acceptsEventStream(), 'The normalised Accept header still enables streaming negotiation.');
$assert(RequestEnvironment::fromServer(['HTTP_USER_AGENT' => ["not-a-header"]])->userAgent() === null, 'A non-scalar User-Agent is refused.');
$assert(RequestEnvironment::fromServer(['HTTP_USER_AGENT' => "bad\xFFagent"])->userAgent() === null, 'An invalid UTF-8 User-Agent is refused.');
$longAgent = str_repeat('a', RequestEnvironment::USER_AGENT_BYTES - 1) . "\xE2\x82\xAC";
$boundedAgent = RequestEnvironment::fromServer(['HTTP_USER_AGENT' => $longAgent])->userAgent();
$assert($boundedAgent !== null && strlen($boundedAgent) <= RequestEnvironment::USER_AGENT_BYTES && preg_match('//u', $boundedAgent) === 1, 'A long User-Agent is byte-bounded without emitting invalid UTF-8.');
$assert(!RequestEnvironment::fromServer(['HTTP_ACCEPT' => ['array']])->acceptsEventStream(), 'A malformed server header cannot alter streaming behaviour.');

// ---- Request context value ---------------------------------------------------------------
$context = new RequestContextV1($id, '203.0.113.9', 'Agent/1.0');
$assert($context->visitorId() === $id && $context->clientAddress() === '203.0.113.9' && $context->userAgent() === 'Agent/1.0', 'The request context returns what it was given.');
$threw = 0;
foreach ([['xyz', null, null], [null, '999.1.1.1', null], [null, null, str_repeat('a', 513)]] as $args) {
    try {
        new RequestContextV1(...$args);
    } catch (InvalidArgumentException $e) {
        $threw++;
    }
}
$assert($threw === 3, 'Invalid request-context values are refused.');

$turn = new CompletedTurnV1('c1', new ProfileIdV1('default'), new DateTimeImmutable('now', new DateTimeZone('UTC')), 'Q', 'A', '1.0.0', null, 'corpus', str_repeat('a', 64), str_repeat('b', 64), 'cache', $context);
$assert($turn->requestContext() === $context, 'A completed turn carries the optional in-memory request context.');
$bare = new CompletedTurnV1('c1', new ProfileIdV1('default'), new DateTimeImmutable('now', new DateTimeZone('UTC')), 'Q', 'A', '1.0.0', null, 'corpus', str_repeat('a', 64), str_repeat('b', 64), 'provider');
$assert($bare->requestContext() === null, 'Existing callers that pass no context still work.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS: CC-12 request context checks verified\n";
