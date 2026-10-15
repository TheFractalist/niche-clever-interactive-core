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
$assert(str_contains($setup, 'aria-label="Niche Clever Interactive settings"'), 'The public tab navigation should remain labelled.');
$assert(str_contains($setup, 'ncli-admin__help-toggle'), 'Technical fields must expose the accessible question-mark help controls.');
$assert(str_contains($setup, 'aria-controls="ncli-help-enabled"'), 'The availability control must associate its help button with a labelled help note.');
$assert(str_contains($setup, '&lt;unsafe version&gt;'), 'Settings values must remain escaped in the new table layout.');
$assert(!str_contains($setup, 'profile-picker'), 'The Core setup must not render a multi-profile picker.');

$_GET = ['tab' => 'conversation'];
ob_start();
(new SettingsPage())->render();
$conversation = (string) ob_get_clean();
$assert(str_contains($conversation, 'Owner response guidance'), 'The Conversation tab must describe the owner setting as effective response guidance.');
$assert(str_contains($conversation, 'It can shape response style and format only'), 'The Conversation tab must state the bounded owner-guidance scope.');
$assert(str_contains($conversation, 'fresh connection test'), 'The Conversation tab must explain that changed guidance requires retesting.');

$_GET = ['tab' => 'ai-corpus'];
ob_start();
(new SettingsPage())->render();
$aiCorpus = (string) ob_get_clean();
$assert(str_contains($aiCorpus, 'name="model_id"'), 'AI & Corpus must offer the curated model dropdown.');
foreach (['gpt-6-luna', 'gpt-6.1-sol', 'gpt-6-astra', 'gpt-5.6'] as $modelId) {
    $assert(str_contains($aiCorpus, 'value="' . $modelId . '"'), 'The model dropdown must include ' . $modelId . '.');
}
$assert(str_contains($aiCorpus, 'value="gpt-6-astra" data-efforts="low,medium,high,xhigh,max" selected'), 'Astra must be the selected default model.');
$assert(str_contains($aiCorpus, 'aria-controls="ncli-help-model_id"'), 'The model dropdown must have an accessible help control.');
$assert(str_contains($aiCorpus, 'ncli-admin__model-table'), 'The model help must contain the comparison table.');
$assert(str_contains($aiCorpus, 'name="model_reasoning_effort"') && str_contains($aiCorpus, 'name="model_retrieval_depth"') && str_contains($aiCorpus, 'name="model_max_output_tokens"') && str_contains($aiCorpus, 'name="model_timeout_seconds"'), 'AI & Corpus must offer effort, depth, answer-length and timeout settings.');
$assert(str_contains($aiCorpus, '<option value="60" selected>'), 'The timeout must default to 60 seconds.');
$assert(!preg_match('/\$\s?\d/', $aiCorpus), 'The model settings must not show prices.');

$_GET = ['tab' => 'diagnostics'];
ob_start();
(new SettingsPage())->render();
$diagnostics = (string) ob_get_clean();
$assert(str_contains($diagnostics, 'ncli-admin__tab-panel'), 'Read-only Core tabs should share the workspace frame.');
$assert(!str_contains($diagnostics, 'ncli-admin__save-bar'), 'Read-only Core tabs should not render a settings save control.');

$renderHelp = static function (?string $section): string {
    $_GET = ['tab' => 'help'] + ($section === null ? [] : ['help' => $section]);
    ob_start();
    (new SettingsPage())->render();
    return (string) ob_get_clean();
};
$help = $renderHelp(null);
$assert(str_contains($help, 'Help Centre'), 'The Help tab must provide the tailored Help Centre.');
$assert(str_contains($help, 'ncli-admin__subtabs') && str_contains($help, 'aria-label="Help sections"'), 'The Help Centre must offer its own labelled section tabs.');
foreach (['Overview', 'Frequently asked questions', 'NCI Premium'] as $label) {
    $assert(str_contains($help, '>' . $label . '</a>'), 'The Help Centre must offer a "' . $label . '" tab.');
}
$assert(str_contains($help, 'aria-current="page"') && str_contains($help, 'ncli-admin__subtab is-active'), 'The current Help section must be marked for assistive technology.');
$assert(str_contains($help, 'OpenAI File Search documentation'), 'The Help Centre must link to the official provider documentation.');
$assert(!str_contains($help, 'ncli-admin__comparison-table') && !str_contains($help, '<details>'), 'The overview must not carry the FAQs or the Premium comparison.');
$faq = $renderHelp('faq');
$assert(str_contains($faq, '<details>') && str_contains($faq, 'Where does the API key go?') && str_contains($faq, 'Last provider problem') && str_contains($faq, 'question allowance has been reached'), 'The FAQ tab must carry the questions, including the current failure and allowance guidance.');
$assert(!str_contains($faq, 'ncli-admin__model-table') && !str_contains($faq, 'ncli-admin__comparison-table'), 'The FAQ tab must not repeat the overview or the comparison.');
$premium = $renderHelp('premium');
$assert(str_contains($premium, 'ncli-admin__comparison-table'), 'The NCI Premium tab must provide the Core and planned-Premium comparison table.');
$assert(substr_count($premium, 'scope="rowgroup"') === 2 && str_contains($premium, 'Included in NCI Core') && str_contains($premium, 'Planned for NCI Premium'), 'The comparison must be grouped into Core and planned Premium features.');
foreach (['Multiple knowledge profiles', 'Approved public evidence links', 'Chronology records', 'Authored questions and shareable links', 'Ordered question collections', 'Protected profiles', 'Conversation governance', 'Approved-answer cache'] as $feature) {
    $assert(str_contains($premium, $feature), 'The comparison must list the planned Premium feature: ' . $feature);
}
$assert(!str_contains($premium, 'NCI Pro') && !str_contains($help . $faq . $premium, '>Pro<'), 'User-facing Help must call the planned edition NCI Premium.');
$assert(str_contains($premium, 'no availability or pricing claim'), 'The comparison must not imply that NCI Premium is currently available.');
$unknown = $renderHelp('<script>');
$assert(!str_contains($unknown, '<script>') && str_contains($unknown, 'What Niche Clever Interactive is for'), 'An unknown Help section must fall back to the overview.');
$assert(str_contains($help, 'Choosing a model and managing cost'), 'The Help Centre must explain model cost drivers.');
$assert(str_contains($help, 'ncli-admin__model-table') && str_contains($help, 'OpenAI API pricing'), 'The Help Centre must show the model table and link to provider pricing without quoting prices.');
$assert(!preg_match('/\$\s?\d/', $help), 'The Help Centre must not quote prices.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C2 administrator Dashboard-chrome checks verified\n";
