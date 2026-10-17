<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Admin\ExtensionScreens;
use NicheCleverInteractive\Core\Api\V1\Contracts\WorkspaceExtensionV1;
use NicheCleverInteractive\Core\Api\V1\Support\ExtensionRegistryV1;
use NicheCleverInteractive\Core\Api\V1\Value\CachedAnswerV1;
use NicheCleverInteractive\Core\Api\V1\Value\ChronologyFactV1;
use NicheCleverInteractive\Core\Api\V1\Value\CompletedTurnV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceContextV1;
use NicheCleverInteractive\Core\Api\V1\Value\WorkspaceScreenV1;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

$ncliCan = true;
$ncliMenus = [];

function current_user_can(string $capability): bool
{
    global $ncliCan;
    return $ncliCan && $capability === 'manage_options';
}
function add_submenu_page(string $parent, string $title, string $menu, string $cap, string $slug, callable $cb)
{
    global $ncliMenus;
    $ncliMenus[$slug] = ['parent' => $parent, 'title' => $title, 'cap' => $cap, 'cb' => $cb];
    return 'hook-' . $slug;
}
function wp_create_nonce(string $action): string
{
    return 'nonce-for-' . $action;
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

final class FakeExtension implements WorkspaceExtensionV1
{
    /** @var list<mixed> */
    public array $screens;
    public bool $throwOnRender = false;
    public bool $throwOnScreens = false;
    /** @var list<WorkspaceContextV1> */
    public array $contexts = [];

    public function __construct(array $screens)
    {
        $this->screens = $screens;
    }

    public function screens(): array
    {
        if ($this->throwOnScreens) {
            throw new RuntimeException('boom');
        }
        return $this->screens;
    }

    public function render(string $screenId, WorkspaceContextV1 $context): void
    {
        $this->contexts[] = $context;
        echo '<p class="ext">partial output</p>';
        if ($this->throwOnRender) {
            throw new RuntimeException('secret detail');
        }
        echo '<p class="ext-ok">screen ' . $screenId . ' nonce ' . $context->nonce() . '</p>';
    }
}

$host = static function (?object $extension) {
    $registry = new ExtensionRegistryV1();
    if ($extension !== null) {
        $registry->register('workspace_extension', $extension);
    }
    return new ExtensionScreens($registry);
};
$render = static function (ExtensionScreens $h, string $id): string {
    ob_start();
    $h->render($id);
    return (string) ob_get_clean();
};

// No extension: no screens, no menus, direct render is a generic notice.
$none = $host(null);
$assert($none->screenIds() === [], 'No extension means no screens.');
$none->addMenus();
$assert($ncliMenus === [], 'No extension means no submenu pages.');
$assert(str_contains($render($none, 'profiles'), 'temporarily unavailable'), 'A screen request without an extension is a generic notice.');

// Registered extension: Core-owned slugs, titles and capability.
$ext = new FakeExtension([new WorkspaceScreenV1('profiles'), new WorkspaceScreenV1('archive'), new WorkspaceScreenV1('profiles')]);
$good = $host($ext);
$assert($good->screenIds() === ['profiles', 'archive'], 'Screens are de-duplicated and ordered.');
$good->addMenus();
$assert(array_keys($ncliMenus) === ['ncli-ext-profiles', 'ncli-ext-archive'], 'One Core-owned page per screen.');
$assert($ncliMenus['ncli-ext-profiles']['parent'] === NicheCleverInteractive\Core\Admin\SettingsPage::PAGE_SLUG, 'The parent is the NC Interactive menu.');
$assert($ncliMenus['ncli-ext-profiles']['cap'] === 'manage_options', 'Pages require manage_options.');
$assert($ncliMenus['ncli-ext-profiles']['title'] === 'Profiles', 'The title comes from Core.');

$out = $render($good, 'profiles');
$assert(str_contains($out, '<h1 class="screen-reader-text">Profiles</h1>') && str_contains($out, 'screen profiles nonce nonce-for-ncli_ext_profiles'), 'The extension body renders with a Core-issued nonce.');
// CC-9: the same chrome as Core's own screens.
$assert(str_contains($out, 'class="ncli-admin__masthead"') && str_contains($out, 'class="nav-tab-wrapper ncli-admin__tabs"'), 'An extension screen carries Core\'s masthead and tab bar.');
$assert(str_contains($out, 'nav-tab nav-tab-active" href="admin.php?page=ncli-ext-profiles" aria-current="page">Profiles</a>') && str_contains($out, 'href="admin.php?page=ncli-ext-archive">Conversation governance</a>') && str_contains($out, 'href="admin.php?page=ncli-core">Settings</a>'), 'The tab bar links back to Settings, lists every screen and marks the current one.');
$assert(str_contains($out, '<section class="ncli-admin__tab-panel ncli-admin__extension">') && substr_count($out, '<section') === 1, 'The extension body sits in Core\'s tab panel.');
$assert(!str_contains($render($none, 'profiles'), '<section'), 'An unavailable screen shows no panel.');
$ctx = $ext->contexts[0];
$assert($ctx->isCoreAuthorised() && $ctx->screenId() === 'profiles' && $ctx->nonceAction() === 'ncli_ext_profiles', 'The context carries the screen id and nonce action.');

// CC-10d: a site-level screen id, and the way back follows the hub.
$assert((new NicheCleverInteractive\Core\Api\V1\Value\WorkspaceScreenV1('settings'))->id() === 'settings' && NicheCleverInteractive\Core\Admin\ExtensionScreens::label('settings') === 'Extension settings', 'The settings screen id is permitted and labelled by Core.');
$assert(str_contains($out, 'href="admin.php?page=ncli-core">Settings</a>') && !str_contains($out, 'view=profiles'), 'Without a profile editor the tab bar leads back to Settings.');
// Callbacks registered with WordPress render the same.
ob_start();
call_user_func($ncliMenus['ncli-ext-archive']['cb']);
$viaCallback = (string) ob_get_clean();
$assert(str_contains($viaCallback, 'screen archive'), 'The registered page callback renders its screen.');

// A screen the extension did not declare is refused without calling it.
$before = count($ext->contexts);
$assert(str_contains($render($good, 'cache'), 'temporarily unavailable') && count($ext->contexts) === $before, 'An undeclared screen is never delegated.');
$assert(!str_contains($render($good, 'not-a-screen'), 'ext-ok'), 'An unknown id is never delegated.');

// Capability is checked before the extension is called.
$ncliCan = false;
$before = count($ext->contexts);
$denied = $render($good, 'profiles');
$assert(str_contains($denied, 'permission') && count($ext->contexts) === $before && !str_contains($denied, 'ext-ok'), 'Without manage_options the extension is not called.');
$ncliCan = true;

// Fail closed: throwing render discards partial output and hides the exception.
$ext->throwOnRender = true;
$failed = $render($good, 'profiles');
$assert(str_contains($failed, 'temporarily unavailable') && !str_contains($failed, 'partial output') && !str_contains($failed, 'secret detail'), 'A throwing render shows a generic notice only.');
$assert(ob_get_level() === 0, 'A throwing render leaves no open output buffer.');
$ext->throwOnRender = false;

// Fail closed: throwing or malformed screens() hides all screens.
$ext->throwOnScreens = true;
$assert($good->screenIds() === [], 'A throwing screens() yields no screens.');
$ext->throwOnScreens = false;
$bad = $host(new FakeExtension(['profiles']));
$assert($bad->screenIds() === [], 'A non-screen entry rejects the whole list.');
$assert($host(new class implements WorkspaceExtensionV1 {
    public function screens(): array
    {
        return [new WorkspaceScreenV1('profiles')];
    }
    public function render(string $screenId, WorkspaceContextV1 $context): void
    {
    }
})->screenIds() === ['profiles'], 'A well-formed extension is accepted.');

// Title table covers every permitted id and falls back safely.
foreach (['profiles', 'chronology', 'questions', 'collections', 'cache', 'archive', 'evidence'] as $id) {
    $assert(ExtensionScreens::label($id) !== 'Extension', "Screen $id has a Core label.");
}

// Context validation.
$throws = static function (callable $fn) use ($assert): bool {
    try {
        $fn();
    } catch (InvalidArgumentException $e) {
        return true;
    }
    return false;
};
$assert($throws(static fn () => new WorkspaceContextV1(true, 'n', 'unknown-screen')), 'A context rejects a non-permitted screen id.');
$assert($throws(static fn () => new WorkspaceContextV1(true, 'n', 'profiles', 'Bad Action!')), 'A context rejects a malformed nonce action.');
$assert($throws(static fn () => new WorkspaceContextV1(false, 'n')), 'A context needs Core authorisation.');
$assert($throws(static fn () => new WorkspaceContextV1(true, '')), 'A context needs a nonce.');

// Accessors.
$profile = new ProfileIdV1('profile_1');
$fact = new ChronologyFactV1('2026-10-12', 'Label', 'Summary', 'rev1');
$assert($fact->date() === '2026-10-12' && $fact->label() === 'Label' && $fact->summary() === 'Summary' && $fact->sourceRevision() === 'rev1', 'Chronology accessors return the stored values.');
$assert((new CachedAnswerV1('Answer'))->renderedAnswer() === 'Answer', 'Cached answer accessor.');
$at = new DateTimeImmutable('2026-10-12T00:00:00+00:00');
$turn = new CompletedTurnV1('c1', $profile, $at, 'Q', 'A', '1.0.0', null, 'corpus', str_repeat('a', 64), str_repeat('b', 64), 'cache');
$assert($turn->question() === 'Q' && $turn->answer() === 'A' && $turn->sourceKind() === 'cache' && $turn->proVersion() === null && $turn->profileId()->value() === 'profile_1' && $turn->completedAt() === $at && $turn->conversationId() === 'c1' && $turn->coreVersion() === '1.0.0' && $turn->corpusVersion() === 'corpus', 'Completed-turn accessors return the stored values.');

// Capability list is truthful.
$assert((new ExtensionRegistryV1())->capabilities() === ['handshake', 'workspace_hosting', 'profile_resolution', 'viewer_access', 'supplementary_chronology', 'eligible_answer_cache', 'completed_public_turn', 'conversation_context', 'evidence_citations', 'evidence_screen', 'panel_colours', 'panel_events', 'conversation_governance', 'profile_hub', 'site_pages'], 'workspace_hosting is advertised now that Core hosts screens.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS: CC-1 workspace hosting checks verified\n";
