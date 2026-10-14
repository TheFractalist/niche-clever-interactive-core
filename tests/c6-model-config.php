<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Admin\SettingsPage;
use NicheCleverInteractive\Core\AI\Transport\OpenAiResponsesFileSearchTransport;
use NicheCleverInteractive\Core\AI\Transport\RequestOptions;
use NicheCleverInteractive\Core\AI\Transport\ResponsesHttpClient;
use NicheCleverInteractive\Core\AI\Transport\ResponsesHttpResponse;
use NicheCleverInteractive\Core\AI\Transport\TransportRequest;
use NicheCleverInteractive\Core\Configuration\ConnectionFingerprint;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\ModelCatalogue;
use NicheCleverInteractive\Core\Configuration\Readiness;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Security\PromptPolicy;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

if (!defined('NCLI_CORE_MOCK_TRANSPORT')) {
    define('NCLI_CORE_MOCK_TRANSPORT', true);
}
if (!defined('NCLI_TEST_PROVIDER_KEY')) {
    define('NCLI_TEST_PROVIDER_KEY', 'fixture-provider-key');
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$rejects = static function (callable $callback): bool {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return true;
    }

    return false;
};

// Catalogue.
$assert(ModelCatalogue::ids() === ['gpt-6-luna', 'gpt-6.1-sol', 'gpt-6-astra', 'gpt-5.6'], 'The catalogue must list Economy, Balanced, Premium and the retained previous default in order.');
$assert(ModelCatalogue::DEFAULT_MODEL === 'gpt-6-astra', 'Astra must remain the default model.');
$assert(ModelCatalogue::validEffort('gpt-6-luna', 'none') && !ModelCatalogue::validEffort('gpt-6-astra', 'none'), '"none" must be valid only where the model offers it.');
$assert(!ModelCatalogue::supportsReasoning('gpt-5.6') && ModelCatalogue::validEffort('gpt-5.6', 'default') && !ModelCatalogue::validEffort('gpt-5.6', 'low'), 'The retained legacy model must not accept a reasoning effort.');
foreach (ModelCatalogue::all() as $entry) {
    $assert(!preg_match('/\d\s?(usd|\$|dollar)|\$\s?\d/i', $entry['cost_note'] . $entry['best_for']), 'Catalogue guidance must be qualitative and carry no prices.');
}

// Defaults and tolerant load of settings stored before the model group existed.
$defaults = CoreSettings::defaults();
$assert($defaults['model'] === ['id' => 'gpt-6-astra', 'reasoning_effort' => 'default', 'retrieval_depth' => 5, 'max_output_tokens' => 4000, 'timeout_seconds' => 60], 'Model settings must default to current behaviour.');
$legacyStored = $defaults;
unset($legacyStored['model']);
$assert(CoreSettings::fromArray($legacyStored)->model() === $defaults['model'], 'Settings stored without the model group must load with defaults.');

// Round trip and validation.
$custom = ['id' => 'gpt-6-luna', 'reasoning_effort' => 'none', 'retrieval_depth' => 3, 'max_output_tokens' => 2000, 'timeout_seconds' => 90];
$assert(CoreSettings::fromArray(array_replace($defaults, ['model' => $custom]))->model() === $custom, 'Valid model settings must round trip.');
$with = static fn(array $change): array => array_replace($defaults, ['model' => array_replace($defaults['model'], $change)]);
$assert($rejects(static fn() => CoreSettings::fromArray($with(['id' => 'gpt-unknown']))), 'An unknown model must be rejected.');
$assert($rejects(static fn() => CoreSettings::fromArray($with(['reasoning_effort' => 'none']))), 'An effort invalid for the model must be rejected.');
$assert($rejects(static fn() => CoreSettings::fromArray($with(['id' => 'gpt-5.6', 'reasoning_effort' => 'low']))), 'The legacy model must reject a reasoning effort.');
$assert($rejects(static fn() => CoreSettings::fromArray($with(['retrieval_depth' => 4]))), 'An unlisted retrieval depth must be rejected.');
$assert($rejects(static fn() => CoreSettings::fromArray($with(['max_output_tokens' => 999]))) && $rejects(static fn() => CoreSettings::fromArray($with(['max_output_tokens' => 8001]))), 'Output tokens must stay within bounds.');
$assert($rejects(static fn() => CoreSettings::fromArray($with(['timeout_seconds' => 45]))), 'An unlisted timeout must be rejected.');

// Request options and payload.
$http = new class implements ResponsesHttpClient {
    public string $body = '';
    public int $timeout = 0;

    /** @param array<string, string> $headers */
    public function post(string $url, array $headers, string $body, int $timeout): ResponsesHttpResponse
    {
        $this->body = $body;
        $this->timeout = $timeout;
        return new ResponsesHttpResponse(200, json_encode(['output' => [['type' => 'file_search_call', 'status' => 'completed']]], JSON_THROW_ON_ERROR));
    }
};
$transport = new OpenAiResponsesFileSearchTransport(new SecretResolver('NCLI_TEST_PROVIDER_KEY'), $http);
$instructions = (new PromptPolicy())->effectiveInstructions('', 'balanced');
$send = static function (RequestOptions $options) use ($transport, $http, $instructions): array {
    $transport->respond(new TransportRequest('Question?', $instructions, 'balanced', str_repeat('a', 64), str_repeat('b', 64), 'vs_fixture_store', $options));
    return json_decode($http->body, true, 512, JSON_THROW_ON_ERROR);
};

$body = $send(new RequestOptions());
$assert($body['model'] === 'gpt-6-astra' && $body['tools'][0]['max_num_results'] === 5 && $body['max_output_tokens'] === 4000 && !array_key_exists('reasoning', $body) && $http->timeout === 60, 'Default options must reproduce current behaviour plus the answer-length cap.');

$body = $send(RequestOptions::fromSettings(CoreSettings::fromArray(array_replace($defaults, ['model' => ['id' => 'gpt-6-luna', 'reasoning_effort' => 'low', 'retrieval_depth' => 10, 'max_output_tokens' => 1500, 'timeout_seconds' => 120]]))));
$assert($body['model'] === 'gpt-6-luna' && $body['reasoning'] === ['effort' => 'low'] && $body['tools'][0]['max_num_results'] === 10 && $body['max_output_tokens'] === 1500 && $http->timeout === 120, 'Configured options must reach the provider request and timeout.');
$assert(!array_key_exists('include', $body), 'Configuration must not request retrieved search-result text.');

$body = $send(new RequestOptions('gpt-5.6', 'low'));
$assert($body['model'] === 'gpt-5.6' && !array_key_exists('reasoning', $body), 'The legacy model must never be sent a reasoning parameter.');

// Fingerprint: every model setting invalidates a passing connection test.
$secrets = new SecretResolver('NCLI_TEST_PROVIDER_KEY');
$policy = new PromptPolicy();
$enabled = array_replace($defaults, [
    'enabled' => true,
    'profile' => ['id' => 'default', 'corpus_version' => 'v1', 'vector_store_reference' => 'vs_fixture_store'],
]);
$base = CoreSettings::fromArray($enabled);
$fingerprint = (string) ConnectionFingerprint::forSettings($base, $secrets, $policy, TransportMode::MOCK);
$passed = $base->withConnectionTest('passed', $fingerprint, '2026-10-14T12:00:00Z');
$assert(Readiness::evaluate($passed, $secrets)->isReady(), 'A freshly tested model configuration must be ready.');
$changes = [
    ['id' => 'gpt-6.1-sol'],
    ['reasoning_effort' => 'high'],
    ['retrieval_depth' => 8],
    ['max_output_tokens' => 3000],
    ['timeout_seconds' => 90],
];
foreach ($changes as $change) {
    $changed = CoreSettings::fromArray(array_replace_recursive($passed->toArray(), ['model' => $change]));
    $assert(Readiness::evaluate($changed, $secrets)->state() === Readiness::STALE_CONNECTION_TEST, 'Changing ' . key($change) . ' must make the connection test stale.');
}

// Save-path normalisation: a model switch drops an effort the new model cannot accept.
$page = new SettingsPage();
$modelInput = new ReflectionMethod($page, 'modelInput');
$modelInput->setAccessible(true);
$_POST = ['model_id' => 'gpt-5.6', 'model_reasoning_effort' => 'high'];
$normalised = $modelInput->invoke($page, $defaults['model']);
$assert($normalised['id'] === 'gpt-5.6' && $normalised['reasoning_effort'] === 'default', 'A model switch must reset an effort the new model does not offer.');
$_POST = ['model_id' => 'gpt-6-luna', 'model_reasoning_effort' => 'none', 'model_retrieval_depth' => '8', 'model_max_output_tokens' => '2500', 'model_timeout_seconds' => '30'];
$accepted = $modelInput->invoke($page, $defaults['model']);
$assert($accepted === ['id' => 'gpt-6-luna', 'reasoning_effort' => 'none', 'retrieval_depth' => 8, 'max_output_tokens' => 2500, 'timeout_seconds' => 30], 'Posted model settings must be read into the settings structure.');
$_POST = [];

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C6 model-cost configuration checks verified\n";
