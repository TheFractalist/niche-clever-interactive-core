<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\AI\ChatService;
use NicheCleverInteractive\Core\AI\Transport\DeterministicMockTransport;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\ConnectionFingerprint;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Security\PromptPolicy;
use NicheCleverInteractive\Core\Rest\ChatRoute;

$ncliRouteOptions = [];
if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        global $ncliRouteOptions;
        return $ncliRouteOptions[$name] ?? $default;
    }
}

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

if (!defined('NCLI_CORE_MOCK_TRANSPORT')) {
    define('NCLI_CORE_MOCK_TRANSPORT', true);
}

$configured = CoreSettings::fromArray([
    'enabled' => true,
    'profile' => [
        'id' => 'default',
        'corpus_version' => 'fixture-corpus-2026-10',
        'vector_store_reference' => 'vs_fixture_route',
    ],
    'instructions' => '',
    'response_preset' => 'balanced',
    'limits' => ['visitor_requests_per_day' => 10, 'provider_requests_per_day' => 100],
    'appearance' => ['panel_style' => 'classic'],
]);
$ncliRouteOptions[CoreSettings::OPTION_NAME] = $configured->withConnectionTest(
    'passed',
    (string) ConnectionFingerprint::forSettings($configured, new SecretResolver('NCLI_NOT_A_SECRET'), new PromptPolicy(), TransportMode::MOCK),
    '2026-10-12T12:00:00Z'
)->toArray();

$request = new class {
    /** @return array{question: string} */
    public function get_json_params(): array
    {
        return ['question' => 'What is NCI?'];
    }
};

$route = new ChatRoute(new ChatService(new DeterministicMockTransport(), new SecretResolver('NCLI_NOT_A_SECRET')));
$payload = $route->respond($request);
if (!is_array($payload) || ($payload['ok'] ?? false) !== true || !is_string($payload['answer'] ?? null) || array_key_exists('evidence', $payload)) {
    fwrite(STDERR, "FAIL: C3 route must return only its bounded answer payload.\n");
    exit(1);
}

echo "PASS: C3 chat route public payload verified\n";
