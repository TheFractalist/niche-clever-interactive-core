<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Api\V1\Contracts\ProfileResolverV1;
use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\ResolvedProfileV1;
use NicheCleverInteractive\Core\Configuration\ConnectionFingerprint;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\ModelSettings;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Frontend\InteractivePanel;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;
use NicheCleverInteractive\Core\Profile\ProfileRuntime;
use NicheCleverInteractive\Core\Profile\ProfileTestRecords;
use NicheCleverInteractive\Core\Security\PromptPolicy;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

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

$defaults = CoreSettings::fromArray(CoreSettings::defaults());
$base = $defaults->model();
$block = ['id' => 'gpt-6-luna', 'reasoning_effort' => 'low', 'retrieval_depth' => 8, 'max_output_tokens' => 6000, 'timeout_seconds' => 90];

// ---- ModelSettings is the single definition --------------------------------------------------------------
$assert(ModelSettings::validate([], $base) === $base, 'An empty block takes every default.');
$assert(ModelSettings::validate($block, $base) === $block, 'A valid block passes unchanged.');
$assert(ModelSettings::validate(['retrieval_depth' => 10], $base)['retrieval_depth'] === 10, 'A partial block takes defaults for the rest.');
foreach ([
    ['id' => 'not-a-model'], ['id' => 'gpt-5.6', 'reasoning_effort' => 'high'], ['reasoning_effort' => 'extreme'], ['retrieval_depth' => 7], ['timeout_seconds' => 45],
    ['max_output_tokens' => 999], ['max_output_tokens' => 8001], ['max_output_tokens' => '4000'], ['id' => 5], ['id' => "gpt\x00"],
] as $i => $bad) {
    $assert($throws(static fn () => ModelSettings::validate($bad, $base)), "An invalid model block #$i is rejected.");
}
foreach ([['id' => 'gpt-6-luna', 'reasoning_effort' => 'none', 'retrieval_depth' => 3, 'max_output_tokens' => 1000, 'timeout_seconds' => 30], $block] as $valid) {
    $fromCore = CoreSettings::fromArray(['model' => $valid])->model();
    $assert($fromCore === ModelSettings::validate($valid, $base), 'Core\'s own settings and ModelSettings agree.');
}
$assert($throws(static fn () => CoreSettings::fromArray(['model' => ['retrieval_depth' => 7]])), 'Core\'s own settings still reject a bad block.');

// ---- ResolvedProfileV1 carries the block structurally --------------------------------------------------
$id = new ProfileIdV1('docs');
$make = static fn (?array $model, ?array $appearance = null): ResolvedProfileV1 => ResolvedProfileV1::configured($id, true, 'vs_docs', 'docs-1', '', 'balanced', [], 'classic', 5, $model, $appearance);
$assert($make(null)->configuration()['model'] === null && $make(null)->configuration()['appearance'] === null, 'Without a block the profile inherits.');
$assert($make($block, ['panel_width' => 'wide', 'accent_color' => ' #ABCDEF ', 'show_credit' => true])->configuration()['appearance']['accent_color'] === '#ABCDEF', 'An appearance block is stored trimmed.');
foreach ([['id' => 'gpt-6-luna'], $block + ['extra' => 1], array_merge($block, ['retrieval_depth' => '8']), array_merge($block, ['id' => str_repeat('m', 129)]), array_reverse($block, true)] as $i => $bad) {
    $assert($throws(static fn () => $make($bad)), "A structurally bad model block #$i is rejected.");
}
foreach ([['panel_width' => 3], ['unknown' => 'x'], ['show_credit' => 'yes'], ['accent_color' => str_repeat('#', 17)]] as $i => $bad) {
    $assert($throws(static fn () => $make(null, $bad)), "A bad appearance block #$i is rejected.");
}

// ---- ProfileRuntime builds the right options -----------------------------------------------------------------
$inherit = ProfileRuntime::fromResolved($make(null), $defaults);
$assert($inherit !== null && $inherit->options()->model() === $base['id'] && $inherit->options()->retrievalDepth() === $base['retrieval_depth'], 'A profile with no block inherits the default model settings.');
$own = ProfileRuntime::fromResolved($make($block), $defaults);
$assert($own !== null && $own->options()->model() === 'gpt-6-luna' && $own->options()->reasoningEffort() === 'low' && $own->options()->retrievalDepth() === 8 && $own->options()->maxOutputTokens() === 6000 && $own->options()->timeoutSeconds() === 90, 'A profile with its own block uses it.');
$assert(ProfileRuntime::fromResolved($make(array_merge($block, ['id' => 'gpt-5.6'])), $defaults) === null, 'A model the catalogue does not allow with that effort makes the profile unavailable.');
$assert(ProfileRuntime::fromResolved($make(array_merge($block, ['id' => 'imaginary'])), $defaults) === null, 'An unknown model makes the profile unavailable, not the default model.');
$assert(ProfileRuntime::fromResolved($make(array_merge($block, ['retrieval_depth' => 7])), $defaults) === null, 'An unsupported retrieval depth makes the profile unavailable.');

$secrets = new SecretResolver('NCLI_NOT_A_SECRET');
$policy = new PromptPolicy();
$fingerprint = static fn (ProfileRuntime $p): string => (string) ConnectionFingerprint::forProfile($p, $secrets, $policy, TransportMode::MOCK);
$assert($fingerprint($inherit) !== $fingerprint($own), 'A different model block changes the connection fingerprint, so a new test is needed.');
$assert($fingerprint($own) === $fingerprint(ProfileRuntime::fromResolved($make($block), $defaults)), 'The same block gives the same fingerprint.');
$assert($inherit->answerSourceFingerprint() !== $own->answerSourceFingerprint(), 'A different model block changes the answer-cache source fingerprint.');

// ---- Appearance --------------------------------------------------------------------------------------------------
$assert($inherit->panelWidth() === 'standard' && $inherit->accentColor() === '' && $inherit->showCredit() === false, 'A profile inherits the default appearance.');
$styled = ProfileRuntime::fromResolved($make(null, ['panel_width' => 'wide', 'accent_color' => '#ABCDEF', 'show_credit' => true]), $defaults);
$assert($styled !== null && $styled->panelWidth() === 'wide' && $styled->accentColor() === '#abcdef' && $styled->showCredit() === true, 'A profile can set its own width, accent colour and credit line.');
$assert(ProfileRuntime::fromResolved($make(null, ['panel_width' => 'huge']), $defaults) === null && ProfileRuntime::fromResolved($make(null, ['accent_color' => 'red']), $defaults) === null, 'A bad width or colour makes the profile unavailable.');
$withDefaultStyle = CoreSettings::fromArray(['appearance' => ['panel_width' => 'narrow', 'accent_color' => '#112233', 'show_credit' => true]]);
$partial = ProfileRuntime::fromResolved($make(null, ['panel_width' => 'wide']), $withDefaultStyle);
$assert($partial !== null && $partial->panelWidth() === 'wide' && $partial->accentColor() === '#112233' && $partial->showCredit() === true, 'A profile that sets only some appearance values inherits the rest.');
$assert(ProfileRuntime::fromSettings($withDefaultStyle)->panelWidth() === 'narrow', 'The default profile reports Core\'s own appearance.');

// ---- The public panel uses the profile's appearance ----------------------------------------------------------------
$records = new class implements ProfileTestRecords {
    public function get(string $profileId): array { return ['status' => 'never', 'tested_at' => '', 'fingerprint' => '']; }
    public function put(string $profileId, string $status, string $fingerprint, string $testedAt): void {}
};
$resolver = new class($make(null, ['panel_width' => 'wide', 'accent_color' => '#ABCDEF', 'show_credit' => true])) implements ProfileResolverV1 {
    private ResolvedProfileV1 $profile;
    public function __construct(ResolvedProfileV1 $profile)
    {
        $this->profile = $profile;
    }
    public function resolve(ProfileIdV1 $profileId): ?ResolvedProfileV1
    {
        return $profileId->value() === 'docs' ? $this->profile : null;
    }
};
$registry = new ExtensionRegistryV1();
$registry->register('profile_resolution', $resolver);
$panel = new InteractivePanel(new ProfileDirectory($registry, $records));
$html = $panel->renderInteractive(['profile' => 'docs']);
$assert(str_contains($html, '--ncli-accent:#abcdef') || str_contains($html, '#abcdef'), 'The named profile\'s accent colour reaches its panel.');
$assert(str_contains($html, 'Powered by NC Interactive'), 'The named profile\'s credit line reaches its panel.');
$assert(!str_contains($panel->renderInteractive([]), 'Powered by NC Interactive') && !str_contains($panel->renderInteractive([]), '#abcdef'), 'The default panel keeps Core\'s own appearance.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS: CC-10b per-profile model and appearance checks verified\n";
