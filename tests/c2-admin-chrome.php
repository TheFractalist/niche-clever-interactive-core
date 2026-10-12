<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Admin\SettingsPage;
use NicheCleverInteractive\Core\Configuration\CoreSettings;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

$ncliAdminChromeOptions = [
    CoreSettings::OPTION_NAME => [
        'enabled' => false,
        'profile' => [
            'id' => 'default',
            'corpus_version' => '<unsafe version>',
            'vector_store_reference' => '',
        ],
        'instructions' => '',
        'response_preset' => 'balanced',
        'limits' => ['visitor_requests_per_day' => 10, 'provider_requests_per_day' => 100],
        'appearance' => ['panel_style' => 'classic'],
        'presentation' => CoreSettings::defaults()['presentation'],
    ],
];

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return $capability === 'manage_options';
    }
}

if (!function_exists('get_option')) {
    function get_option(string $name, $default = false)
    {
        global $ncliAdminChromeOptions;
        return $ncliAdminChromeOptions[$name] ?? $default;
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return 'https://example.test/wp-admin/' . $path;
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(array $query, string $url): string
    {
        return $url . '?' . http_build_query($query);
    }
}

if (!function_exists('plugins_url')) {
    function plugins_url(string $path = '', string $plugin = ''): string
    {
        return 'https://example.test/wp-content/plugins/niche-clever-interactive-core/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field(string $action, string $name): void
    {
        echo '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="test-nonce">';
    }
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$_GET = ['tab' => 'setup'];
ob_start();
(new SettingsPage())->render();
$setup = (string) ob_get_clean();

$assert(str_contains($setup, 'ncli-admin--workspace'), 'The administrator root should identify the NCI workspace.');
$assert(str_contains($setup, 'ncli-admin__masthead-art'), 'The standalone masthead art should render.');
$assert(str_contains($setup, 'ncli-admin__get-started-count'), 'The Setup tab should render its dashboard-style checklist summary.');
$assert(str_contains($setup, '4 Core steps'), 'The Setup tab should use the reconstructed four-step Core workflow.');
$assert(str_contains($setup, 'NCLI_OPENAI_API_KEY_DEFAULT'), 'The Setup tab may name the server constant without rendering a secret.');
$assert(str_contains($setup, 'form="ncli-connection-test"'), 'The Setup tab should expose the dedicated administrator-only connection test action.');
$assert(str_contains($setup, 'Visitor availability'), 'The Setup tab should retain a Core-safe availability control.');
$assert(str_contains($setup, 'ncli-admin__form-table'), 'The Setup tab should render grouped field rows.');
$assert(str_contains($setup, 'ncli-admin__save-bar'), 'The Setup tab should render the blue save treatment.');
$assert(str_contains($setup, 'aria-label="NCI Core settings"'), 'The Core tab navigation should remain labelled.');
$assert(str_contains($setup, '&lt;unsafe version&gt;'), 'Settings values must remain escaped in the new table layout.');
$assert(!str_contains($setup, 'profile-picker'), 'The Core setup must not render a multi-profile picker.');

$_GET = ['tab' => 'diagnostics'];
ob_start();
(new SettingsPage())->render();
$diagnostics = (string) ob_get_clean();
$assert(str_contains($diagnostics, 'ncli-admin__tab-panel'), 'Read-only Core tabs should share the workspace frame.');
$assert(!str_contains($diagnostics, 'ncli-admin__save-bar'), 'Read-only Core tabs should not render a settings save control.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C2 administrator Dashboard-chrome checks verified\n";
