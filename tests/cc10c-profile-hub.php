<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Admin\ProfileFieldRenderer;
use NicheCleverInteractive\Core\Admin\ProfileHub;
use NicheCleverInteractive\Core\Admin\SettingsPage;
use NicheCleverInteractive\Core\Api\V1\Contracts\ProfileEditorV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ProfileResolverV1;
use NicheCleverInteractive\Core\Api\V1\Contracts\ProfileWorkspaceV1;
use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileEditV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileSectionV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileSummaryV1;
use NicheCleverInteractive\Core\Api\V1\Value\ResolvedProfileV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceContextV1;
use NicheCleverInteractive\Core\Configuration\ConnectionFingerprint;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Configuration\SecretResolver;
use NicheCleverInteractive\Core\Configuration\TransportMode;
use NicheCleverInteractive\Core\Profile\ProfileDirectory;
use NicheCleverInteractive\Core\Profile\ProfileTestRecords;
use NicheCleverInteractive\Core\Security\AdminActionGuard;
use NicheCleverInteractive\Core\Security\PromptPolicy;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

if (!defined('NCLI_CORE_MOCK_TRANSPORT')) {
    define('NCLI_CORE_MOCK_TRANSPORT', true);
}
function current_user_can(string $c): bool { return $c === 'manage_options'; }
function get_option(string $n, $d = false) { return $d; }
function admin_url(string $p = ''): string { return 'https://example.test/wp-admin/' . $p; }
function plugins_url(string $p = '', string $f = ''): string { return 'https://example.test/p/' . ltrim($p, '/'); }
function wp_nonce_field(string $a, string $n): void { echo '<input type="hidden" name="' . $n . '" value="nonce-' . $a . '">'; }
function wp_create_nonce(string $a): string { return 'nonce-for-' . $a; }

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$throws = static function (callable $fn, string $class = InvalidArgumentException::class): bool {
    try {
        $fn();
    } catch (Throwable $e) {
        return $e instanceof $class;
    }
    return false;
};

// ---- Value objects -----------------------------------------------------------------------------------------------------
$id = new ProfileIdV1('docs');
$assert((new ProfileSummaryV1($id, ' Docs ', true, false))->label() === 'Docs', 'A summary trims its label.');
foreach (['', str_repeat('x', 81), "ba\x01d"] as $label) {
    $assert($throws(static fn () => new ProfileSummaryV1($id, $label, true, false)), 'A bad summary label is rejected.');
}
$edit = new ProfileEditV1('Docs', true, 'vs_docs', 'c1', 'Guidance', 'balanced', ['title' => 'T'], 'classic', 5);
$assert($edit->label() === 'Docs' && $edit->vectorStoreReference() === 'vs_docs' && $edit->model() === null && $edit->appearance() === null && $edit->presentation() === ['title' => 'T'], 'An edit carries validated values.');
$assert((new ProfileEditV1('Docs', true, null, '', '', 'balanced', [], 'classic', 5, null, null, true))->hasStoredReference(), 'An edit can say a reference is already stored.');
foreach ([
    ['', true, null, '', '', 'balanced', [], 'classic', 5], ['Docs', true, str_repeat('v', 257), '', '', 'balanced', [], 'classic', 5], ['Docs', true, null, '', '', 'creative', [], 'classic', 5],
    ['Docs', true, null, '', '', 'balanced', [], 'neon', 5], ['Docs', true, null, '', '', 'balanced', [], 'classic', 0], ['Docs', true, null, '', '', 'balanced', ['script' => 'x'], 'classic', 5],
    ['Docs', true, null, '', '', 'balanced', [], 'classic', 5, ['id' => 'gpt-6-luna']],
] as $i => $args) {
    $assert($throws(static fn () => new ProfileEditV1(...$args)), "A bad edit #$i is rejected.");
}
$assert((new ProfileSectionV1('setup', 'access', 'Visitor access'))->tab() === 'setup', 'A section names a permitted tab.');
foreach ([['nope', 'access', 'T'], ['setup', 'Bad Key', 'T'], ['setup', 'ab', 'T'], ['setup', 'access', ''], ['setup', 'access', str_repeat('t', 81)]] as $i => $args) {
    $assert($throws(static fn () => new ProfileSectionV1(...$args)), "A bad section #$i is rejected.");
}

// ---- Fakes ---------------------------------------------------------------------------------------------------------------
$records = new class implements ProfileTestRecords {
    /** @var array<string, array{status: string, tested_at: string, fingerprint: string}> */
    public array $data = [];
    public function get(string $profileId): array { return $this->data[$profileId] ?? ['status' => 'never', 'tested_at' => '', 'fingerprint' => '']; }
    public function put(string $profileId, string $status, string $fingerprint, string $testedAt): void { $this->data[$profileId] = ['status' => $status, 'tested_at' => $testedAt, 'fingerprint' => $fingerprint]; }
};
$editor = new class implements ProfileEditorV1 {
    /** @var array<string, ProfileEditV1> */
    public array $items = [];
    public bool $throw = false;
    public int $max = 5;
    /** @var list<string> */
    public array $log = [];
    public function summaries(): array
    {
        if ($this->throw) {
            throw new RuntimeException('down');
        }
        $out = [];
        foreach ($this->items as $key => $item) {
            $out[] = new ProfileSummaryV1(new ProfileIdV1($key), $item->label(), $item->isEnabled(), $key === 'secret');
        }
        return $out;
    }
    public function values(ProfileIdV1 $id): ?ProfileEditV1
    {
        if ($this->throw) {
            throw new RuntimeException('down');
        }
        return $this->items[$id->value()] ?? null;
    }
    public function create(ProfileIdV1 $id, ProfileEditV1 $edit): void
    {
        if (isset($this->items[$id->value()]) || count($this->items) >= $this->max) {
            throw new LogicException('taken');
        }
        $this->items[$id->value()] = $edit;
        $this->log[] = 'create:' . $id->value();
    }
    public function save(ProfileIdV1 $id, ProfileEditV1 $edit): void
    {
        $this->items[$id->value()] = $edit;
        $this->log[] = 'save:' . $id->value();
    }
    public function delete(ProfileIdV1 $id): bool
    {
        $had = isset($this->items[$id->value()]);
        unset($this->items[$id->value()]);
        $this->log[] = 'delete:' . $id->value();
        return $had;
    }
    public function limit(): int
    {
        return $this->max;
    }
};
$editor->items['docs'] = new ProfileEditV1('Docs <b>', true, null, 'docs-1', 'Be formal.', 'detailed', ['title' => 'Docs helper'], 'dark', 7, null, null, true);
$editor->items['secret'] = new ProfileEditV1('Secret', true, null, 'sec-1', '', 'balanced', [], 'classic', 5, null, null, false);
$editor->items['off'] = new ProfileEditV1('Off', false, null, '', '', 'balanced', [], 'classic', 5);

$resolver = new class($editor) implements ProfileResolverV1 {
    private ProfileEditorV1 $editor;
    public function __construct(ProfileEditorV1 $editor) { $this->editor = $editor; }
    public function resolve(ProfileIdV1 $profileId): ?ResolvedProfileV1
    {
        $edit = $this->editor->values($profileId);
        if ($edit === null) {
            return null;
        }
        return ResolvedProfileV1::configured($profileId, $edit->isEnabled(), 'vs_' . $profileId->value(), $edit->corpusVersion(), $edit->guidance(), $edit->preset(), $edit->presentation(), $edit->panelStyle(), $edit->visitorRequestsPerDay(), $edit->model(), $edit->appearance());
    }
};
$workspace = new class implements ProfileWorkspaceV1 {
    public bool $throwSections = false;
    public bool $throwRender = false;
    /** @var list<ProfileSectionV1> */
    public array $sections = [];
    public function sections(ProfileIdV1 $id): array
    {
        if ($this->throwSections) {
            throw new RuntimeException('down');
        }
        return $this->sections;
    }
    public function render(string $sectionKey, ProfileIdV1 $id, WorkspaceContextV1 $context): void
    {
        echo '<p class="body">body ' . $sectionKey . ' for ' . $id->value() . ' nonce ' . $context->nonce() . ' action ' . $context->nonceAction() . '</p>';
        if ($this->throwRender) {
            throw new RuntimeException('secret detail');
        }
    }
};
$workspace->sections = [new ProfileSectionV1('setup', 'access', 'Visitor access'), new ProfileSectionV1('questions', 'questions', 'Questions'), new ProfileSectionV1('cache', 'cache', 'Cached answers')];

$registry = new ExtensionRegistryV1();
$registry->register('profile_resolution', $resolver);
$registry->register('profile_editor', $editor);
$registry->register('profile_workspace', $workspace);
$directory = new ProfileDirectory($registry, $records);
$plain = new ProfileDirectory(new ExtensionRegistryV1(), $records);
$settings = CoreSettings::fromArray(CoreSettings::defaults());

// ---- ProfileDirectory is fail-closed ----------------------------------------------------------------------------------------
$assert(count($directory->summaries()) === 3 && $plain->summaries() === [] && $plain->editor() === null && $plain->sections('docs') === [], 'Summaries and sections need the extension.');
$editor->throw = true;
$assert($directory->summaries() === [], 'A throwing editor yields no profiles.');
$editor->throw = false;
$bad = new class implements ProfileEditorV1 {
    public $list = [];
    public function summaries(): array { return $this->list; }
    public function values(ProfileIdV1 $id): ?ProfileEditV1 { return null; }
    public function create(ProfileIdV1 $id, ProfileEditV1 $edit): void {}
    public function save(ProfileIdV1 $id, ProfileEditV1 $edit): void {}
    public function delete(ProfileIdV1 $id): bool { return false; }
    public function limit(): int { return 5; }
};
$badRegistry = new ExtensionRegistryV1();
$badRegistry->register('profile_editor', $bad);
$badDirectory = new ProfileDirectory($badRegistry, $records);
$bad->list = [new ProfileSummaryV1(new ProfileIdV1('default'), 'Default', true, false)];
$assert($badDirectory->summaries() === [], 'An extension cannot list the default profile.');
$bad->list = [new ProfileSummaryV1(new ProfileIdV1('a'), 'A', true, false), new ProfileSummaryV1(new ProfileIdV1('a'), 'A2', true, false)];
$assert($badDirectory->summaries() === [], 'A repeated id yields no profiles.');
$bad->list = ['not a summary'];
$assert($badDirectory->summaries() === [], 'A malformed entry yields no profiles.');
$workspace->throwSections = true;
$assert($directory->sections('docs') === [], 'A throwing workspace yields no sections.');
$workspace->throwSections = false;
$assert(count($directory->sections('docs')) === 3 && $directory->sections('Bad Id') === [], 'Sections need a valid profile id.');

// Status is Core's own.
$assert($directory->status('default', $settings, false)['state'] === 'disabled', 'The default profile reports its own state.');
$assert($directory->status('off', $settings, false)['state'] === 'disabled' && $directory->status('docs', $settings, true)['state'] !== 'ready', 'A disabled profile is disabled; an untested one is not ready.');
$runtime = $directory->resolve('docs', $settings);
$secrets = new SecretResolver('NCLI_NOT_A_SECRET');
$records->put('docs', 'passed', (string) ConnectionFingerprint::forProfile($runtime, $secrets, new PromptPolicy(), TransportMode::MOCK), '2026-10-16T10:00:00Z');
$ready = $directory->status('docs', $settings, true);
$assert($ready['state'] === 'ready' && $ready['tested_at'] === '2026-10-16T10:00:00Z', 'A profile with a current passing test is ready.');
$editor->items['docs'] = new ProfileEditV1('Docs <b>', true, null, 'docs-2', 'Be formal.', 'detailed', ['title' => 'Docs helper'], 'dark', 7, null, null, true);
$assert($directory->status('docs', $settings, true)['state'] === 'stale_connection_test', 'Changing the corpus version makes the test stale.');
$editor->items['docs'] = new ProfileEditV1('Docs <b>', true, null, 'docs-1', 'Be formal.', 'detailed', ['title' => 'Docs helper'], 'dark', 7, ['id' => 'gpt-6-luna', 'reasoning_effort' => 'low', 'retrieval_depth' => 8, 'max_output_tokens' => 6000, 'timeout_seconds' => 90], null, true);
$assert($directory->status('docs', $settings, true)['state'] === 'stale_connection_test', 'Changing the model makes the test stale.');
$editor->items['docs'] = new ProfileEditV1('Docs <b>', true, null, 'docs-1', 'Be formal.', 'detailed', ['title' => 'Docs helper'], 'dark', 7, null, null, true);
$assert($directory->status('docs', $settings, true)['state'] === 'ready', 'Restoring the settings restores the current test.');

// ---- Hub ---------------------------------------------------------------------------------------------------------------------------
$redirects = [];
$can = true;
$guard = new AdminActionGuard(static function () use (&$can): bool { return $can; }, static fn (string $action, string $nonce): bool => $nonce === 'nonce-' . $action);
$hub = new ProfileHub($directory, $guard, static fn (): CoreSettings => $settings, static function (array $q) use (&$redirects): void { $redirects[] = $q; });
$render = static function (callable $fn): string {
    ob_start();
    $fn();
    return (string) ob_get_clean();
};
$assert($hub->active() && !(new ProfileHub($plain))->active(), 'The hub exists only with an editor.');

$_GET = [];
$html = $render(static fn () => $hub->render());
$assert(str_contains($html, 'Your profiles') && str_contains($html, 'Default profile') && str_contains($html, 'Docs &lt;b&gt;') && !str_contains($html, 'Docs <b>'), 'The Profiles page lists the default profile and each extension profile, escaped.');
$assert(str_contains($html, 'Ready (current test)') && str_contains($html, 'Disabled') && str_contains($html, 'Not tested yet') && str_contains($html, '2026-10-16 10:00 UTC'), 'Each profile shows its own connection-test status and time.');
$assert(preg_match('/<th scope="col">Profile<\/th><th scope="col">Tag<\/th><th scope="col">Availability<\/th><th scope="col">Connection test<\/th><th scope="col">Last tested<\/th><th scope="col">Access<\/th><th scope="col">Actions<\/th>/', $html) === 1 && str_contains($html, '<td><code>docs</code></td>') && str_contains($html, '<td>&mdash;</td>'), 'The table has separate Tag, Connection test and Last tested columns, with a dash where a profile was never tested.');
$assert(str_contains($html, 'Protected') && str_contains($html, 'href="https://example.test/wp-admin/admin.php?page=ncli-core&amp;view=new"') && str_contains($html, 'Add profile'), 'The page shows access and an Add profile link.');
$assert(substr_count($html, 'name="action" value="ncli_test_profile"') === 2 && str_contains($html, 'name="action" value="ncli_test_connection"') && !str_contains($html, 'value="off"'), 'Each enabled profile has a Test connection button (the disabled one does not).');
$assert(str_contains($html, 'maximum') === false, 'Below the limit there is no limit notice.');
$editor->max = 3;
$assert(str_contains($render(static fn () => $hub->render()), 'The maximum number of profiles has been reached.') && !str_contains($render(static fn () => $hub->render()), 'view=new'), 'At the limit the Add profile link is replaced by a notice.');
$editor->max = 5;

$_GET = ['profile' => 'default'];
$assert($render(static fn () => $assert($hub->render() === false, 'The default profile is left to SettingsPage.')) === '', 'Routing the default profile draws nothing itself.');
$_GET = ['tab' => 'setup'];
$assert($render(static fn () => $assert($hub->render() === false, 'A bare tab address still means the default profile.')) === '', 'A bookmarked tab address keeps working.');

$_GET = ['profile' => 'docs', 'tab' => 'setup'];
$html = $render(static fn () => $hub->render());
$assert(str_contains($html, 'Profile workspace') && str_contains($html, 'ncli-admin__titlebar') && str_contains($html, 'Get started') && str_contains($html, 'name="vector_store_reference"') === false, 'A profile opens in the same workspace; the setup tab has its checklist.');
$assert(str_contains($html, 'Authored questions') && str_contains($html, 'Cached answers') && str_contains($html, 'Setup'), 'The tab bar carries the extension tabs.');
$assert(preg_match('/<div class="ncli-admin__titlebar">.*Switch profile.*All profiles.*Profile workspace.*<\/div><\/div><header/s', $html) === 1 && !str_contains(substr($html, strpos($html, '<header')), 'Switch profile'), 'The profile switcher, All profiles and the workspace label sit in the title strip, not below the banner.');
$assert(str_contains($html, 'body access for docs nonce nonce-for-ncli_ext_access action ncli_ext_access'), 'A section is drawn with a Core-issued nonce.');
$assert(str_contains($html, 'name="action" value="ncli_save_profile"') && str_contains($html, 'Delete this profile') && str_contains($html, 'Save this profile'), 'The setup tab has the save form and the delete form.');
$_GET = ['profile' => 'docs', 'tab' => 'setup'];
$html = $render(static fn () => $hub->render());
$assert(substr_count($html, 'name="_ncli_nonce"') === 2 && !str_contains($html, 'id="_ncli_nonce"') && str_contains($html, 'value="nonce-for-ncli_save_profile"') && str_contains($html, 'value="nonce-for-ncli_delete_profile"'), 'The save and delete forms carry their own nonces without a repeated id.');
$_GET = ['profile' => 'docs', 'tab' => 'ai-corpus'];
$html = $render(static fn () => $hub->render());
$assert(str_contains($html, 'name="vector_store_reference"') && str_contains($html, 'Saved. Leave blank to keep it.') && !str_contains($html, 'vs_docs') && str_contains($html, 'name="model_id"'), 'AI & Corpus has a write-only reference field and the model fields.');
$_GET = ['profile' => 'secret', 'tab' => 'ai-corpus'];
$assert(!str_contains($render(static fn () => $hub->render()), 'Saved. Leave blank'), 'A profile without a stored reference does not claim one.');
$_GET = ['profile' => 'docs', 'tab' => 'appearance-limits'];
$html = $render(static fn () => $hub->render());
$assert(str_contains($html, 'name="panel_width"') && str_contains($html, 'name="visitor_requests_per_day"') && !str_contains($html, 'provider_requests_per_day') && !str_contains($html, 'delete_on_uninstall'), 'Appearance & Limits has no site-wide fields.');
$_GET = ['profile' => 'docs', 'tab' => 'conversation'];
$assert(!str_contains($render(static fn () => $hub->render()), 'conversation_log_enabled'), 'Core\'s own conversation log setting is not drawn for an extension profile.');
$_GET = ['profile' => 'docs', 'tab' => 'questions'];
$assert(str_contains($render(static fn () => $hub->render()), 'body questions for docs'), 'An extension-only tab shows its section.');
$workspace->sections = [new ProfileSectionV1('setup', 'access', 'Visitor access')];
$_GET = ['profile' => 'docs', 'tab' => 'questions'];
$html = $render(static fn () => $hub->render());
$assert(!str_contains($html, 'body questions') && str_contains($html, 'Get started'), 'A tab with no sections falls back to Setup.');
$workspace->sections = [new ProfileSectionV1('setup', 'access', 'Visitor access'), new ProfileSectionV1('questions', 'questions', 'Questions'), new ProfileSectionV1('cache', 'cache', 'Cached answers')];
$workspace->throwRender = true;
$_GET = ['profile' => 'docs', 'tab' => 'setup'];
$html = $render(static fn () => $hub->render());
$assert(str_contains($html, 'temporarily unavailable') && !str_contains($html, 'secret detail') && !str_contains($html, 'body access'), 'A throwing section is replaced by a generic notice and its partial output is dropped.');
$workspace->throwRender = false;
$_GET = ['profile' => 'ghost'];
$assert(str_contains($render(static fn () => $hub->render()), 'does not exist') , 'An unknown profile shows a not-found page.');
$editor->throw = true;
$_GET = ['profile' => 'docs'];
$assert(str_contains($render(static fn () => $hub->render()), 'does not exist or cannot be edited'), 'A failing editor shows the same page, not an error.');
$editor->throw = false;
$_GET = ['view' => 'new'];
$html = $render(static fn () => $hub->render());
$assert(str_contains($html, 'name="profile_id"') && str_contains($html, 'name="label"') && str_contains($html, 'name="action" value="ncli_create_profile"') && str_contains($html, 'name="corpus_version"'), 'Add profile shows the identity and setup fields.');

// ---- Saving -----------------------------------------------------------------------------------------------------------------------------
$post = static fn (array $more): array => $more + ['_ncli_nonce' => 'nonce-ncli_save_profile'];
$_POST = $post(['profile_id' => 'docs', 'ncli_tab' => 'profile', 'enabled' => '1', 'label' => 'Docs v2', 'corpus_version' => 'docs-3', 'presentation_title' => 'New title']);
$redirects = [];
$hub->save();
$saved = $editor->items['docs'];
$assert(end($redirects) === ['profile' => 'docs', 'tab' => 'profile', 'ncli_notice' => 'saved'] && $saved->label() === 'Docs v2' && $saved->corpusVersion() === 'docs-3' && $saved->presentation()['title'] === 'New title', 'A tab saves only its own fields into a validated edit.');
$assert($saved->guidance() === 'Be formal.' && $saved->visitorRequestsPerDay() === 7 && $saved->model() === null && $saved->vectorStoreReference() === null && $saved->hasStoredReference(), 'Fields not on the tab are kept, the model stays inherited and the reference is kept.');
$_POST = $post(['profile_id' => 'docs', 'ncli_tab' => 'ai-corpus', 'vector_store_reference' => 'vs_new', 'model_id' => 'gpt-6-luna', 'model_reasoning_effort' => 'low', 'model_retrieval_depth' => '8', 'model_max_output_tokens' => '6000', 'model_timeout_seconds' => '90']);
$hub->save();
$saved = $editor->items['docs'];
$assert($saved->vectorStoreReference() === 'vs_new' && $saved->model() === ['id' => 'gpt-6-luna', 'reasoning_effort' => 'low', 'retrieval_depth' => 8, 'max_output_tokens' => 6000, 'timeout_seconds' => 90], 'Saving AI & Corpus stores a new reference and an explicit model block.');
$_POST = $post(['profile_id' => 'docs', 'ncli_tab' => 'ai-corpus', 'model_id' => 'gpt-6-luna', 'model_reasoning_effort' => 'low', 'model_retrieval_depth' => '7']);
$before = $editor->items['docs'];
$hub->save();
$assert(end($redirects)['ncli_notice'] === 'invalid' && $editor->items['docs'] === $before, 'An invalid model value is rejected and nothing is stored.');
$_POST = $post(['profile_id' => 'docs', 'ncli_tab' => 'appearance-limits', 'panel_width' => 'wide', 'accent_color' => '#ABCDEF', 'show_credit' => '1', 'panel_style' => 'minimal', 'visitor_requests_per_day' => '12']);
$hub->save();
$saved = $editor->items['docs'];
$assert($saved->appearance() === ['panel_width' => 'wide', 'accent_color' => '#abcdef', 'show_credit' => true] && $saved->panelStyle() === 'minimal' && $saved->visitorRequestsPerDay() === 12, 'Saving Appearance & Limits stores the appearance block.');
$_POST = $post(['profile_id' => 'docs', 'ncli_tab' => 'appearance-limits', 'panel_width' => 'wide', 'accent_color' => 'red']);
$hub->save();
$assert(end($redirects)['ncli_notice'] === 'invalid' && $editor->items['docs']->appearance()['accent_color'] === '#abcdef', 'A bad colour is rejected.');
$_POST = $post(['profile_id' => 'docs', 'ncli_tab' => 'appearance-limits', 'panel_width' => 'wide', 'accent_color_default' => '1', 'visitor_requests_per_day' => '0']);
$hub->save();
$assert(end($redirects)['ncli_notice'] === 'invalid', 'A visitor limit of zero is rejected.');
$_POST = $post(['profile_id' => 'ghost', 'ncli_tab' => 'setup']);
$hub->save();
$assert(end($redirects) === ['view' => 'profiles', 'ncli_notice' => 'missing'], 'Saving an unknown profile reports it missing.');
$count = count($editor->log);
$_POST = ['profile_id' => 'docs', 'ncli_tab' => 'setup', '_ncli_nonce' => 'forged'];
$hub->save();
$can = false;
$_POST = $post(['profile_id' => 'docs', 'ncli_tab' => 'setup', 'corpus_version' => 'x']);
$hub->save();
$can = true;
$assert(count($editor->log) === $count && $editor->items['docs']->corpusVersion() === 'docs-3', 'A bad nonce or a missing capability changes nothing.');

// ---- Create and delete --------------------------------------------------------------------------------------------------------------------
$createPost = static fn (array $more): array => $more + ['_ncli_nonce' => 'nonce-ncli_create_profile'];
$_POST = $createPost(['profile_id' => 'fresh', 'label' => 'Fresh', 'enabled' => '1', 'corpus_version' => 'f1', 'vector_store_reference' => 'vs_fresh']);
$hub->create();
$assert(end($redirects) === ['profile' => 'fresh', 'tab' => 'setup', 'ncli_notice' => 'created'] && isset($editor->items['fresh']) && $editor->items['fresh']->vectorStoreReference() === 'vs_fresh' && $editor->items['fresh']->model() === null, 'A new profile is created from validated values and inherits the default model.');
$_POST = $createPost(['profile_id' => 'docs', 'label' => 'Again']);
$hub->create();
$assert(end($redirects)['ncli_notice'] === 'exists', 'A repeated id is reported.');
foreach (['default', 'Bad Id', '', 'x y'] as $bad) {
    $count = count($editor->log);
    $_POST = $createPost(['profile_id' => $bad, 'label' => 'X']);
    $hub->create();
    $assert(end($redirects)['ncli_notice'] === 'invalid' && count($editor->log) === $count, "The id '$bad' is not accepted.");
}
$_POST = $createPost(['profile_id' => 'nolabel', 'label' => '']);
$hub->create();
$assert(end($redirects)['ncli_notice'] === 'invalid' && !isset($editor->items['nolabel']), 'A profile needs a label.');
$delPost = static fn (array $more): array => $more + ['_ncli_nonce' => 'nonce-ncli_delete_profile'];
$_POST = $delPost(['profile_id' => 'fresh']);
$hub->delete();
$assert(isset($editor->items['fresh']) && end($redirects) === ['profile' => 'fresh', 'tab' => 'setup', 'ncli_notice' => 'confirm'], 'Deleting needs the confirmation box, and says so.');
$_POST = $delPost(['profile_id' => 'fresh', 'confirm' => '1']);
$hub->delete();
$assert(!isset($editor->items['fresh']) && end($redirects) === ['view' => 'profiles', 'ncli_notice' => 'deleted'], 'A confirmed delete removes the profile.');
$_POST = $delPost(['profile_id' => 'default', 'confirm' => '1']);
$count = count($editor->log);
$hub->delete();
$assert(!in_array('delete:default', array_slice($editor->log, $count), true) && end($redirects)['ncli_notice'] === 'missing', 'The default profile is never deleted through the hub.');

// ---- Retired pages -----------------------------------------------------------------------------------------------------------------------------
foreach ([
    'ncli-ext-chronology' => ['view' => 'profiles'],
    'ncli-ext-evidence' => ['view' => 'profiles'],
    'ncli-ext-archive' => ['page' => 'ncli-conversations', 'tab' => 'governance'],
    'ncli-ext-settings' => ['page' => 'ncli-settings'],
] as $retired => $target) {
    $redirects = [];
    $_GET = ['page' => $retired];
    $hub->redirectRetiredPages();
    $assert($redirects === [$target], "$retired leads to where its content now lives.");
}
$redirects = [];
$_GET = ['page' => 'ncli-core', 'tab' => 'help', 'help' => 'faq'];
$hub->redirectRetiredPages();
$assert($redirects === [['page' => 'ncli-settings', 'tab' => 'help', 'help' => 'faq']], 'The default profile\'s Help tab leads to Help in Settings, keeping the section.');
$redirects = [];
$_GET = ['page' => 'ncli-core', 'tab' => 'setup'];
$hub->redirectRetiredPages();
$_GET = ['page' => 'ncli-core'];
$hub->redirectRetiredPages();
$_GET = ['page' => ['x']];
$hub->redirectRetiredPages();
$assert($redirects === [], 'Other pages are not redirected.');
$redirects = [];
$_GET = ['page' => 'ncli-ext-chronology'];
(new ProfileHub($plain, $guard, static fn (): CoreSettings => $settings, static function (array $q) use (&$redirects): void { $redirects[] = $q; }))->redirectRetiredPages();
$assert($redirects === [], 'Without an editor nothing is redirected.');

// ---- SettingsPage with the hub ----------------------------------------------------------------------------------------------------------------------
$page = new SettingsPage(null, null, null, $hub);
$_GET = ['tab' => 'setup'];
$html = $render(static fn () => $page->render());
$assert(str_contains($html, 'ncli-admin__titlebar') && str_contains($html, 'Default profile') && str_contains($html, 'Switch profile') && str_contains($html, 'All profiles') && str_contains($html, 'ncli-admin__save-bar'), 'The default profile gets the title strip, switcher and an All profiles link.');
$assert(str_contains($html, 'Authored questions') && str_contains($html, 'Cached answers') && str_contains($html, 'body access for default'), 'The default profile\'s tab bar and tabs carry the extension\'s tabs and sections.');
$assert(!str_contains($html, '>Help</a>'), 'With the hub the default profile\'s tab bar has no Help tab; Help lives in Settings.');
$_GET = ['tab' => 'questions'];
$html = $render(static fn () => $page->render());
$assert(str_contains($html, 'body questions for default') && !str_contains($html, 'name="corpus_version"'), 'An extension tab on the default profile shows only sections.');
$_GET = [];
$html = $render(static fn () => $page->render());
$assert(str_contains($html, 'Your profiles') && !str_contains($html, 'ncli-admin__get-started'), 'With the hub active the bare Core address opens the Profiles page.');
$_GET = ['profile' => 'docs'];
$assert(str_contains($render(static fn () => $page->render()), 'Profile workspace'), 'The Core address with a profile opens that profile.');
$_GET = ['tab' => 'setup'];
$noHub = $render(static fn () => (new SettingsPage())->render());
$assert(!str_contains($noHub, 'ncli-admin__titlebar') && !str_contains($noHub, 'All profiles') && !str_contains($noHub, 'Switch profile'), 'Without an editor the Settings page is exactly as before.');

// ---- Hub mode removes the site-wide rows from the default profile; the Settings page owns them -----------------------------------------------
$_GET = ['tab' => 'appearance-limits'];
$html = $render(static fn () => $page->render());
$assert(!str_contains($html, 'provider_requests_per_day') && str_contains($html, 'visitor_requests_per_day'), 'With the hub the default profile keeps its own limit but not the site-wide provider allowance.');
$_GET = ['tab' => 'conversation'];
$html = $render(static fn () => $page->render());
$assert(!str_contains($html, 'delete_on_uninstall') && str_contains($html, 'conversation_log_enabled'), 'With the hub Delete data on uninstall is not on the default profile\'s Conversation tab.');
$_GET = ['tab' => 'appearance-limits'];
$assert(str_contains($noHubAppearance = $render(static fn () => (new SettingsPage())->render()), 'provider_requests_per_day'), 'Without the hub the provider allowance is where it always was.');

// ---- Site screens are hosted inside Core's pages -------------------------------------------------------------------------------------------
$hosting = new class implements NicheCleverInteractive\Core\Api\V1\Contracts\WorkspaceExtensionV1 {
    public bool $throw = false;
    public function screens(): array
    {
        return [new NicheCleverInteractive\Core\Api\V1\Value\WorkspaceScreenV1('settings'), new NicheCleverInteractive\Core\Api\V1\Value\WorkspaceScreenV1('archive')];
    }
    public function render(string $screenId, WorkspaceContextV1 $context): void
    {
        echo '<p class="hosted">' . $screenId . ' nonce ' . $context->nonce() . ' action ' . $context->nonceAction() . '</p>';
        if ($this->throw) {
            throw new RuntimeException('secret detail');
        }
    }
};
$hostRegistry = new ExtensionRegistryV1();
$hostRegistry->register('profile_editor', $editor);
$hostRegistry->register('workspace_extension', $hosting);
$hostedScreens = new NicheCleverInteractive\Core\Admin\ExtensionScreens($hostRegistry);
$assert($hostedScreens->hosted() && $hostedScreens->declares('settings') && !$hostedScreens->declares('cache'), 'Screens are hosted while the hub is active.');
$assert($hostedScreens->bodyHtml('settings') === '<p class="hosted">settings nonce nonce-for-ncli_ext_settings action ncli_ext_settings</p>' && $hostedScreens->bodyHtml('cache') === '', 'A hosted body carries a Core-issued nonce; an undeclared screen is empty.');
$hosting->throw = true;
$failed = $hostedScreens->bodyHtml('archive');
$assert(str_contains($failed, 'temporarily unavailable') && !str_contains($failed, 'secret detail') && !str_contains($failed, 'hosted'), 'A failing hosted body is replaced by a generic notice.');
$hosting->throw = false;
$menus = [];
$GLOBALS['ncliMenuLog'] = [];
if (!function_exists('add_submenu_page')) {
    function add_submenu_page(string $parent, string $title, string $menu, string $cap, string $slug, $cb = null)
    {
        $GLOBALS['ncliMenuLog'][] = $slug;
        return 'hook-' . $slug;
    }
}
$hostedScreens->addMenus();
$assert($GLOBALS['ncliMenuLog'] === [], 'While the hub is active the extension\'s screens are not menu entries.');

// ---- The Settings page ---------------------------------------------------------------------------------------------------------------------
$siteRedirects = [];
$updated = [];
if (!function_exists('update_option')) {
    function update_option(string $name, $value, $autoload = null): bool
    {
        $GLOBALS['ncliUpdated'][$name] = $value;
        return true;
    }
}
$siteGuard = new AdminActionGuard(static function () use (&$can): bool { return $can; }, static fn (string $action, string $nonce): bool => $nonce === 'nonce-' . $action);
$site = new NicheCleverInteractive\Core\Admin\SiteSettingsPage($hub, $hostedScreens, $siteGuard, static function (array $q) use (&$siteRedirects): void { $siteRedirects[] = $q; });
$_GET = [];
$html = $render(static fn () => $site->render());
$assert(str_contains($html, 'ncli-admin__titlebar') && str_contains($html, 'name="provider_requests_per_day"') && str_contains($html, 'name="delete_on_uninstall"') && str_contains($html, 'name="action" value="ncli_save_site_settings"'), 'Settings > General has the provider allowance and Delete data on uninstall.');
$assert(str_contains($html, '<h2>Extension settings</h2>') && str_contains($html, 'settings nonce nonce-for-ncli_ext_settings'), 'The extension\'s site settings are hosted under General.');
$assert(preg_match('/<a class="nav-tab nav-tab-active"[^>]*>General<\/a><a class="nav-tab"[^>]*>Help<\/a>/', $html) === 1, 'The tab bar has General and Help.');
$_GET = ['tab' => 'help', 'help' => 'faq'];
$html = $render(static fn () => $site->render());
$assert(str_contains($html, 'Help Centre') && str_contains($html, 'Frequently asked questions') && str_contains($html, 'page=ncli-settings&amp;tab=help&amp;help=overview'), 'Settings > Help shows the Help Centre with its own section links.');
$_GET = ['tab' => 'help'];
$siteHelp = $render(static fn () => $site->render());
$assert(!str_contains($siteHelp, 'name="provider_requests_per_day"'), 'The Help tab carries no settings form.');
$_POST = ['_ncli_nonce' => 'nonce-ncli_save_site_settings', 'provider_requests_per_day' => '250', 'delete_on_uninstall' => '1'];
$GLOBALS['ncliUpdated'] = [];
$site->save();
$saved = $GLOBALS['ncliUpdated'][CoreSettings::OPTION_NAME] ?? null;
$assert(end($siteRedirects) === ['tab' => 'general', 'ncli_notice' => 'saved'] && $saved['limits']['provider_requests_per_day'] === 250 && $saved['lifecycle']['delete_on_uninstall'] === true && $saved['profile'] === $settings->toArray()['profile'] && $saved['limits']['visitor_requests_per_day'] === $settings->toArray()['limits']['visitor_requests_per_day'], 'Saving changes the two site settings and nothing else.');
$_POST = ['_ncli_nonce' => 'nonce-ncli_save_site_settings', 'provider_requests_per_day' => '0', 'delete_on_uninstall' => '0'];
$GLOBALS['ncliUpdated'] = [];
$site->save();
$assert(end($siteRedirects)['ncli_notice'] === 'invalid' && $GLOBALS['ncliUpdated'] === [], 'An out-of-range allowance is refused and nothing is stored.');
$_POST = ['_ncli_nonce' => 'forged', 'provider_requests_per_day' => '250', 'delete_on_uninstall' => '1'];
$GLOBALS['ncliUpdated'] = [];
$site->save();
$can = false;
$_POST = ['_ncli_nonce' => 'nonce-ncli_save_site_settings', 'provider_requests_per_day' => '250', 'delete_on_uninstall' => '1'];
$site->save();
$can = true;
$assert($GLOBALS['ncliUpdated'] === [], 'A bad nonce or a missing capability changes nothing.');
$GLOBALS['ncliMenuLog'] = [];
$site->addMenu();
$assert($GLOBALS['ncliMenuLog'] === ['ncli-settings', 'admin.php?page=ncli-settings&tab=help'], 'The menu gets Settings and a Help entry that opens its tab.');
$GLOBALS['ncliMenuLog'] = [];
(new NicheCleverInteractive\Core\Admin\SiteSettingsPage(new ProfileHub($plain), $hostedScreens))->addMenu();
$assert($GLOBALS['ncliMenuLog'] === [], 'Without an editor there is no Settings page.');

// ---- Governance is a tab of Conversations ----------------------------------------------------------------------------------------------------------
$conversationsStore = new class implements NicheCleverInteractive\Core\Conversation\ConversationStore {
    public function record(NicheCleverInteractive\Core\Conversation\ConversationTurn $turn): void {}
    public function listConversations(int $limit, int $offset): array { return []; }
    public function countConversations(): int { return 0; }
    public function turns(string $conversationId): array { return []; }
    public function deleteConversations(array $conversationIds): int { return 0; }
    public function deleteAll(): int { return 0; }
    public function purgeOlderThan(string $cutoffUtc, int $limit): int { return 0; }
};
$conversations = new NicheCleverInteractive\Core\Admin\ConversationsPage($siteGuard, $conversationsStore, $hostedScreens, $hub);
$_GET = ['tab' => 'governance'];
$html = $render(static fn () => $conversations->render());
$assert(str_contains($html, 'archive nonce nonce-for-ncli_ext_archive') && str_contains($html, '>Governance</a>') && str_contains($html, '>Conversation history</a>') && !str_contains($html, 'Conversation settings'), 'Conversations > Governance hosts the extension\'s screen; the tab bar has history and governance.');
$_GET = [];
$html = $render(static fn () => $conversations->render());
$assert(!str_contains($html, 'archive nonce') && str_contains($html, '>Governance</a>'), 'The history tab shows the history, with the Governance tab beside it.');
$plainConversations = $render(static fn () => (new NicheCleverInteractive\Core\Admin\ConversationsPage($siteGuard, $conversationsStore))->render());
$assert(str_contains($plainConversations, 'Conversation settings') && !str_contains($plainConversations, 'Governance'), 'Without the hub the Conversations page is as before.');

// ---- Site screens lead back to the hub --------------------------------------------------------------------------------------------------------
$screens = new NicheCleverInteractive\Core\Admin\ExtensionScreens($registry);
$html = $render(static fn () => $screens->render('settings'));
$assert(str_contains($html, 'view=profiles">Profiles</a>') && !str_contains($html, '>Settings</a>'), 'With a profile editor the extension tab bar leads back to the Profiles page.');

$hubSource = (string) file_get_contents(dirname(__DIR__) . '/src/Admin/ProfileHub.php');
$assert(str_contains($hubSource, "'admin_menu', [\$this, 'redirectRetiredPages'], 99"), 'The retired-page redirect runs while the menu is built, before WordPress denies the page.');

// ---- Wiring -----------------------------------------------------------------------------------------------------------------------------------------
$pluginSource = (string) file_get_contents(dirname(__DIR__) . '/src/Plugin.php');
$assert(str_contains($pluginSource, 'new ProfileHub(') && str_contains($pluginSource, '$this->profileHub->register()') && str_contains($pluginSource, 'new SettingsPage(null, $connectionTest, null, $this->profileHub)'), 'Core wires the hub into the plugin.');
$assert(in_array('profile_hub', (new ExtensionRegistryV1())->capabilities(), true) && in_array('profile_editor', (new ExtensionRegistryV1())->knownSeams(), true) && in_array('profile_workspace', (new ExtensionRegistryV1())->knownSeams(), true), 'The seams and capability are advertised.');
$assert(count((new ReflectionClass(ProfileFieldRenderer::class))->getMethods(ReflectionMethod::IS_PUBLIC)) >= 3, 'The renderer exposes both row sets.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS: CC-10c profile hub checks verified\n";
