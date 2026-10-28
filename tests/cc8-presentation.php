<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Api\V1\Contracts\PanelColoursProviderV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ProfileResolverV1;
use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Api\V1\Value\PanelColoursV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\ResolvedProfileV1;
use NicheCleverInteractive\Core\Configuration\ConnectionFingerprint;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\PanelAppearance;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Frontend\InteractivePanel;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;
use NicheCleverInteractive\Core\Profile\ProfileTestRecords;
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
$throws = static function (callable $fn): bool {
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return true;
    }
    return false;
};

// ---- PanelColoursV1 -----------------------------------------------------------------------
$colours = new PanelColoursV1(['question_background' => '#ABCDEF', 'answer_text' => '#112233']);
$assert($colours->colour('question_background') === '#abcdef' && $colours->colour('answer_text') === '#112233' && $colours->colour('answer_border') === null && !$colours->isEmpty(), 'Colours are lower-cased and unset ones are null.');
$assert((new PanelColoursV1([]))->isEmpty(), 'An empty set is empty.');
foreach ([['question_background' => 'red'], ['question_background' => '#abc'], ['question_background' => '#abcdeg'], ['question_background' => 'url(x)'], ['question_background' => '#abcdef; background:url(x)'], ['unknown' => '#abcdef'], ['answer_text' => 5], [0 => '#abcdef']] as $bad) {
    $assert($throws(static fn () => new PanelColoursV1($bad)), 'Colour set ' . json_encode($bad) . ' is rejected.');
}

// ---- Readability safeguard ----------------------------------------------------------------------
$style = static fn (string $s, array $c): string => PanelAppearance::colourStyle($s, $c);
$assert($style('classic', []) === '', 'No colours means no declarations.');
$assert($style('classic', ['question_background' => '#fff8dc', 'question_text' => '#222222', 'question_border' => '#cc9900']) === '--ncli-q-bg:#fff8dc;--ncli-q-ink:#222222;--ncli-q-rule:#cc9900', 'A readable pair and a border are all used.');
$assert($style('classic', ['answer_background' => '#fafafa', 'answer_text' => '#fdfdfd']) === '', 'Text that cannot be read on its background drops both.');
$assert($style('classic', ['answer_background' => '#fafafa', 'answer_text' => '#fdfdfd', 'answer_border' => '#336699']) === '--ncli-a-rule:#336699', 'The border survives when the pair is dropped.');
$assert($style('classic', ['question_background' => '#111111']) === '', 'A dark background alone is dropped when the style\'s own dark-on-light text could not be read on it.');
$assert($style('dark', ['question_background' => '#111111']) === '--ncli-q-bg:#111111', 'The same background is fine on the dark style, whose text is light.');
$assert($style('classic', ['answer_text' => '#ffffff']) === '', 'White text alone is dropped on the classic style\'s white answer block.');
$assert($style('dark', ['answer_text' => '#ffffff']) === '--ncli-a-ink:#ffffff', 'White text is fine on the dark style\'s dark answer block.');
$assert($style('classic', ['question_background' => 'url(x)', 'answer_text' => '#000000']) === '', 'Any invalid value makes Core ignore the whole set.');
$assert($style('nonsense', ['answer_text' => '#000000']) === '--ncli-a-ink:#000000', 'An unknown style falls back to the classic defaults.');
$assert(!str_contains($style('classic', ['question_border' => '#abcdef']), ';;') && !preg_match('/[^a-z0-9#:;-]/', $style('classic', ['question_background' => '#fff8dc', 'question_text' => '#222222', 'answer_border' => '#336699'])), 'Output contains only fixed property names and hex values.');

// ---- Seam and panel -------------------------------------------------------------------------------
$provider = new class implements PanelColoursProviderV1 {
    public string $mode = 'set';
    /** @var list<string> */
    public array $asked = [];
    public function coloursFor(ProfileIdV1 $profileId): ?PanelColoursV1
    {
        $this->asked[] = $profileId->value();
        switch ($this->mode) {
            case 'throw':
                throw new RuntimeException('colour store down');
            case 'none':
                return null;
            case 'empty':
                return new PanelColoursV1([]);
            case 'unreadable':
                return new PanelColoursV1(['question_background' => '#fafafa', 'question_text' => '#fdfdfd', 'answer_border' => '#336699']);
            default:
                return $profileId->value() === 'docs'
                    ? new PanelColoursV1(['question_background' => '#e8f0ff', 'question_text' => '#102040', 'answer_background' => '#fffbe6'])
                    : new PanelColoursV1(['question_background' => '#fff8dc', 'question_text' => '#222222', 'question_border' => '#cc9900']);
        }
    }
};
$resolver = new class implements ProfileResolverV1 {
    public function resolve(ProfileIdV1 $profileId): ?ResolvedProfileV1
    {
        return $profileId->value() === 'docs'
            ? ResolvedProfileV1::configured($profileId, true, 'vs_docs', 'docs-1', '', 'balanced', ['title' => 'Docs', 'processing_message' => 'Preparing & checking <Docs>'], 'classic', 50)
            : null;
    }
};
$registry = new ExtensionRegistryV1();
$registry->register('panel_colours', $provider);
$registry->register('profile_resolution', $resolver);
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
$directory = new ProfileDirectory($registry, $records);
$secrets = new SecretResolver('NCLI_NOT_A_SECRET');
$policy = new PromptPolicy();
$settings = CoreSettings::fromArray([
    'enabled' => true,
    'profile' => ['id' => 'default', 'corpus_version' => 'default-1', 'vector_store_reference' => 'vs_default'],
]);
$assert($settings->progressiveStreaming() && $settings->processingMessage() === CoreSettings::DEFAULT_PROCESSING_MESSAGE, 'Progressive delivery defaults on and the default profile supplies its processing message.');
$disabledStreaming = CoreSettings::fromArray(['streaming' => ['progressive' => false], 'presentation' => ['processing_message' => '']]);
$assert(!$disabledStreaming->progressiveStreaming() && $disabledStreaming->processingMessage() === CoreSettings::DEFAULT_PROCESSING_MESSAGE, 'Progressive delivery can be disabled while an empty saved message falls back safely.');
$settings = $settings->withConnectionTest('passed', (string) ConnectionFingerprint::forSettings($settings, $secrets, $policy, TransportMode::MOCK), '2026-10-16T12:00:00Z');
$GLOBALS['ncliOptions'] = [CoreSettings::OPTION_NAME => $settings->toArray()];
function get_option(string $name, $default = false)
{
    return $GLOBALS['ncliOptions'][$name] ?? $default;
}

$docs = $directory->resolve('docs', $settings);
$records->put('docs', 'passed', (string) ConnectionFingerprint::forProfile($docs, $secrets, $policy, TransportMode::MOCK), '2026-10-16T12:00:00Z');
$panel = new InteractivePanel($directory);
$bare = new InteractivePanel(new ProfileDirectory(new ExtensionRegistryV1(), $records));

$defaultHtml = $panel->renderInteractive([]);
$assert(str_contains($defaultHtml, 'data-ncli-panel="default"') && str_contains($defaultHtml, 'data-ncli-processing-message="Receiving response…"') && str_contains($defaultHtml, '--ncli-q-bg:#fff8dc;--ncli-q-ink:#222222;--ncli-q-rule:#cc9900'), 'The default panel marks itself, safely exposes its processing message and gets the colours supplied for it.');
$docsHtml = $panel->renderInteractive(['profile' => 'docs']);
$assert(str_contains($docsHtml, 'data-ncli-panel="docs"') && str_contains($docsHtml, 'data-ncli-processing-message="Preparing &amp; checking &lt;Docs&gt;"') && str_contains($docsHtml, '--ncli-q-bg:#e8f0ff') && str_contains($docsHtml, '--ncli-a-bg:#fffbe6') && !str_contains($docsHtml, '#cc9900'), 'A named profile gets its own escaped processing message and colours, not the default\'s.');
$ghost = $panel->renderInteractive(['profile' => 'ghost']);
$assert(str_contains($ghost, 'data-ncli-panel="unavailable"') && !str_contains($ghost, 'ghost') && !str_contains($ghost, '--ncli-q-'), 'An unresolved profile is marked unavailable, echoes nothing and gets no colours.');
$plain = $bare->renderInteractive([]);
$assert(str_contains($plain, 'data-ncli-panel="default"') && !str_contains($plain, '--ncli-q-') && !str_contains($plain, '--ncli-a-'), 'Without the seam no colour declarations are emitted.');
$provider->mode = 'unreadable';
$unreadable = $panel->renderInteractive([]);
$assert(!str_contains($unreadable, '--ncli-q-bg') && !str_contains($unreadable, '--ncli-q-ink') && str_contains($unreadable, '--ncli-a-rule:#336699'), 'Unreadable text/background pairs are dropped by Core; valid borders stay.');
foreach (['throw', 'none', 'empty'] as $mode) {
    $provider->mode = $mode;
    $html = $panel->renderInteractive([]);
    $assert(!str_contains($html, '--ncli-q-') && !str_contains($html, '--ncli-a-') && str_contains($html, 'ncli-panel__form'), "Seam mode $mode leaves the style's own colours and still renders the panel.");
}
$provider->mode = 'set';
$withAccent = CoreSettings::fromArray(array_replace_recursive($settings->toArray(), ['appearance' => ['accent_color' => '#336699', 'panel_width' => 'wide']]));
$GLOBALS['ncliOptions'][CoreSettings::OPTION_NAME] = $withAccent->toArray();
$combined = $panel->renderInteractive([]);
$assert(preg_match('/style="([^"]*)"/', $combined, $m) === 1 && str_contains($m[1], '--ncli-accent:#336699') && str_contains($m[1], '--ncli-max:64rem') && str_contains($m[1], ';--ncli-q-bg:#fff8dc') && !str_contains($m[1], ';;'), 'Accent, width and colours combine into one well-formed style attribute.');

// ---- Static surface -----------------------------------------------------------------------------
$css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/public.css');
foreach (['--ncli-q-bg', '--ncli-q-ink', '--ncli-q-rule', '--ncli-a-bg', '--ncli-a-ink', '--ncli-a-rule'] as $var) {
    $assert(str_contains($css, 'var(' . $var . ','), "The stylesheet reads $var with a fallback.");
}
$js = (string) file_get_contents(dirname(__DIR__) . '/assets/js/public.js');
$assert(str_contains($js, "'ncli:ask'") && str_contains($js, "'ncli:prefill'") && str_contains($js, 'function processingMessage') && str_contains($js, 'progressiveStreaming !== false'), 'The client listens for documented panel events and respects the owner\'s progressive-delivery preference.');
$caps = (new ExtensionRegistryV1())->capabilities();
$assert(in_array('panel_colours', $caps, true) && in_array('panel_events', $caps, true) && in_array('panel_colours', (new ExtensionRegistryV1())->knownSeams(), true), 'The seam and both capabilities are advertised.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS: CC-8 presentation and panel-event checks verified\n";
