<?php

declare(strict_types=1);

use NicheCleverInteractive\Core\Admin\ConversationsPage;
use NicheCleverInteractive\Core\Admin\SettingsPage;
use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Conversation\ConversationStore;
use NicheCleverInteractive\Core\Conversation\ConversationTurn;
use NicheCleverInteractive\Core\Security\AdminActionGuard;

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/autoload.php';

if (!defined('NCLI_CORE_VERSION')) {
    define('NCLI_CORE_VERSION', '1.0.0-rc.9');
}

$ncliOptions = [];
$ncliMenus = [];
$ncliEnqueued = [];
$ncliCan = true;

final class NcliRedirect extends RuntimeException
{
}

function current_user_can(string $capability): bool
{
    global $ncliCan;
    return $ncliCan && $capability === 'manage_options';
}
function get_option(string $name, $default = false)
{
    global $ncliOptions;
    return $ncliOptions[$name] ?? $default;
}
function update_option(string $name, $value, $autoload = null): bool
{
    global $ncliOptions;
    $ncliOptions[$name] = $value;
    return true;
}
function admin_url(string $path = ''): string
{
    return 'https://example.test/wp-admin/' . $path;
}
function add_query_arg(array $query, string $url): string
{
    return $url . '?' . http_build_query($query);
}
function plugins_url(string $path = '', string $plugin = ''): string
{
    return 'https://example.test/plugins/ncli/' . ltrim($path, '/');
}
function wp_nonce_field(string $action, string $name): void
{
    echo '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="test-nonce">';
}
function wp_safe_redirect(string $url): void
{
    throw new NcliRedirect($url);
}
function wp_die($message = ''): void
{
    throw new RuntimeException('wp_die: ' . (is_string($message) ? $message : ''));
}
function add_menu_page(string $pageTitle, string $menuTitle, string $capability, string $slug, $callback = '', string $icon = '', $position = null): string
{
    global $ncliMenus;
    $ncliMenus[] = ['type' => 'top', 'title' => $menuTitle, 'slug' => $slug, 'capability' => $capability];
    return 'toplevel_page_' . $slug;
}
function add_submenu_page(string $parent, string $pageTitle, string $menuTitle, string $capability, string $slug, $callback = ''): string
{
    global $ncliMenus;
    $ncliMenus[] = ['type' => 'sub', 'parent' => $parent, 'title' => $menuTitle, 'slug' => $slug, 'capability' => $capability];
    return 'nc-interactive_page_' . $slug;
}
function wp_enqueue_style(string $handle, string $src = '', array $deps = [], $ver = false): void
{
    global $ncliEnqueued;
    $ncliEnqueued[] = $handle;
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$store = new class implements ConversationStore {
    /** @var list<ConversationTurn> */
    public array $rows = [];
    public function record(ConversationTurn $turn): void { $this->rows[] = $turn; }
    public function listConversations(int $limit, int $offset): array {
        $groups = [];
        foreach ($this->rows as $row) {
            $id = $row->conversationId();
            $groups[$id] = $groups[$id] ?? ['conversation_id' => $id, 'turn_count' => 0, 'started_at' => $row->createdAt(), 'last_at' => $row->createdAt(), 'first_question' => $row->question()];
            ++$groups[$id]['turn_count'];
            $groups[$id]['last_at'] = $row->createdAt();
        }
        return array_slice(array_values($groups), $offset, $limit);
    }
    public function countConversations(): int { return count(array_unique(array_map(static fn ($t) => $t->conversationId(), $this->rows))); }
    public function turns(string $conversationId): array { return array_values(array_filter($this->rows, static fn ($t) => $t->conversationId() === $conversationId)); }
    public function deleteConversations(array $conversationIds): int {
        $before = count($this->rows);
        $this->rows = array_values(array_filter($this->rows, static fn ($t) => !in_array($t->conversationId(), $conversationIds, true)));
        return $before - count($this->rows);
    }
    public function deleteAll(): int { $n = count($this->rows); $this->rows = []; return $n; }
    public function purgeOlderThan(string $cutoffUtc, int $limit): int { return 0; }
};

$idA = str_repeat('a', 32);
$idB = str_repeat('b', 32);
$store->record(new ConversationTurn($idA, 'default', '<script>alert(1)</script> hostile question', 'Answer <b>one</b>', '1.0.0-rc.9', 'c', '', '2026-10-14 09:00:00'));
$store->record(new ConversationTurn($idA, 'default', 'Second question', 'Answer two', '1.0.0-rc.9', 'c', '', '2026-10-14 09:01:00'));
$store->record(new ConversationTurn($idB, 'default', 'Another visitor', 'Another answer', '1.0.0-rc.9', 'c', '', '2026-10-14 10:00:00'));

$allow = new AdminActionGuard(static fn (): bool => true, static fn (string $action, string $nonce): bool => $nonce === 'good-nonce');
$page = new ConversationsPage($allow, $store);

$render = static function (array $get) use ($page): string {
    $_GET = $get;
    ob_start();
    $page->render();
    return (string) ob_get_clean();
};

// Menu structure.
$settingsPage = new SettingsPage($allow);
$settingsPage->addMenu();
$page->addMenu();
$top = array_values(array_filter($ncliMenus, static fn ($m) => $m['type'] === 'top'))[0] ?? [];
$subs = array_values(array_filter($ncliMenus, static fn ($m) => $m['type'] === 'sub'));
$assert(($top['title'] ?? '') === 'NC Interactive', 'The top-level menu must read "NC Interactive", not "NCI Interactive".');
$assert(($subs[0]['title'] ?? '') === 'Settings' && ($subs[0]['slug'] ?? '') === 'ncli-core', 'The first submenu entry must relabel the parent page as Settings.');
$assert(($subs[1]['title'] ?? '') === 'Conversations' && ($subs[1]['parent'] ?? '') === 'ncli-core' && ($subs[1]['slug'] ?? '') === 'ncli-conversations' && ($subs[1]['capability'] ?? '') === 'manage_options', 'Conversations must be an administrator-only submenu of the NCI menu.');
$ncliEnqueued = [];
$page->enqueueAssets('dashboard');
$assert($ncliEnqueued === [], 'The Conversations stylesheet must not load on unrelated screens.');
$page->enqueueAssets('nc-interactive_page_ncli-conversations');
$assert($ncliEnqueued === ['ncli-core-admin'], 'The Conversations stylesheet must load on its own screen, whatever the menu label.');

// List view: off state, escaping, controls.
$list = $render([]);
$assert(str_contains($list, 'Recording is off'), 'The list must say when recording is off.');
$assert(!str_contains($list, '<script>alert(1)</script>'), 'Stored visitor text must be escaped in the list.');
$assert(str_contains($list, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Escaped visitor text must still be readable.');
$assert(str_contains($list, 'name="conversation_ids[]"'), 'The list must offer per-conversation selection.');
$assert(str_contains($list, '_ncli_conversations_nonce'), 'The delete form must carry a nonce.');
$assert(str_contains($list, 'confirm=delete-all') && !str_contains($list, 'name="mode" value="all"'), 'Delete-all must lead to a confirmation page, not delete directly.');
$assert(str_contains($list, 'ncli-admin__masthead-art'), 'The Conversations page must share the workspace masthead.');
$assert(str_contains($list, 'tab=conversation'), 'The page must link back to the Conversation settings.');

$ncliOptions[CoreSettings::OPTION_NAME] = CoreSettings::fromArray(['conversation_log' => ['enabled' => true, 'retention_days' => 30]])->toArray();
$on = $render([]);
$assert(str_contains($on, 'Recording is on. Conversations are removed automatically after 30 days.'), 'The list must state the active retention period.');

// Confirmation page.
$confirm = $render(['confirm' => 'delete-all']);
$assert(str_contains($confirm, 'permanently deletes all 2 stored conversations'), 'The confirmation page must state how many conversations will go.');
$assert(str_contains($confirm, 'name="mode" value="all"') && str_contains($confirm, 'name="confirm" value="1"'), 'Only the confirmation page may carry the delete-all form.');

// Detail view.
$detail = $render(['conversation' => $idA]);
$assert(strpos($detail, 'hostile question') < strpos($detail, 'Second question'), 'Turns must be shown in chronological order.');
$assert(!str_contains($detail, '<b>one</b>') && str_contains($detail, '&lt;b&gt;one&lt;/b&gt;'), 'Stored answers must be escaped.');
$assert(str_contains($detail, 'Delete this conversation'), 'The detail view must offer deletion of that conversation.');
$unknown = $render(['conversation' => str_repeat('d', 32)]);
$assert(str_contains($unknown, 'no longer exists'), 'An unknown conversation must produce a safe message.');
$malformed = $render(['conversation' => "../../etc/passwd'--"]);
$assert(str_contains($malformed, 'Conversation history') && !str_contains($malformed, 'passwd'), 'A malformed identifier must be ignored.');

// Permission failure for rendering.
$ncliCan = false;
$denied = false;
try {
    $render([]);
} catch (RuntimeException $exception) {
    $denied = str_starts_with($exception->getMessage(), 'wp_die');
}
$ncliCan = true;
$assert($denied, 'A user without manage_options must not see the page.');

// Deletion handler.
$post = static function (array $data) use ($page): string {
    $_POST = $data;
    try {
        $page->handleDelete();
    } catch (NcliRedirect $redirect) {
        return $redirect->getMessage();
    }
    return 'no-redirect';
};
$before = count($store->rows);
$noNonce = false;
try {
    $_POST = ['mode' => 'all', 'confirm' => '1'];
    $page->handleDelete();
} catch (RuntimeException $exception) {
    $noNonce = str_starts_with($exception->getMessage(), 'wp_die');
}
$assert($noNonce && count($store->rows) === $before, 'A request without a valid nonce must delete nothing.');
$badNonce = false;
try {
    $_POST = ['mode' => 'all', 'confirm' => '1', '_ncli_conversations_nonce' => 'forged'];
    $page->handleDelete();
} catch (RuntimeException $exception) {
    $badNonce = true;
}
$assert($badNonce && count($store->rows) === $before, 'A forged nonce must delete nothing.');
$noCapability = new AdminActionGuard(static fn (): bool => false, static fn (): bool => true);
$blocked = false;
try {
    $_POST = ['mode' => 'all', 'confirm' => '1', '_ncli_conversations_nonce' => 'good-nonce'];
    (new ConversationsPage($noCapability, $store))->handleDelete();
} catch (RuntimeException $exception) {
    $blocked = true;
}
$assert($blocked && count($store->rows) === $before, 'A user without the capability must delete nothing even with a valid nonce.');

$url = $post(['mode' => 'all', '_ncli_conversations_nonce' => 'good-nonce']);
$assert(str_contains($url, 'ncli_notice=nothing-selected') && count($store->rows) === $before, 'Delete-all without explicit confirmation must delete nothing.');
$url = $post(['mode' => 'selected', 'conversation_ids' => ['x', "'; DROP TABLE x; --", str_repeat('A', 32)], '_ncli_conversations_nonce' => 'good-nonce']);
$assert(str_contains($url, 'ncli_notice=nothing-selected') && count($store->rows) === $before, 'Malformed identifiers must be dropped, leaving nothing to delete.');
$url = $post(['mode' => 'selected', 'conversation_ids' => [$idB, 'bogus'], '_ncli_conversations_nonce' => 'good-nonce']);
$assert(str_contains($url, 'ncli_notice=deleted') && str_contains($url, 'ncli_count=1') && count($store->rows) === $before - 1 && $store->turns($idB) === [] && count($store->turns($idA)) === 2, 'Selected deletion must remove only the chosen valid conversation.');
$url = $post(['mode' => 'all', 'confirm' => '1', '_ncli_conversations_nonce' => 'good-nonce']);
$assert(str_contains($url, 'ncli_notice=deleted') && str_contains($url, 'ncli_count=2') && $store->rows === [], 'Confirmed delete-all must remove everything.');
$_GET = ['ncli_notice' => 'deleted', 'ncli_count' => '2'];
ob_start();
$page->render();
$notice = (string) ob_get_clean();
$assert(str_contains($notice, '2 stored questions and answers were deleted.') && str_contains($notice, 'There are no stored conversations.'), 'The page must report a deletion and then show the empty state.');

// ---- Settings page: new controls and save behaviour.
$ncliOptions = [];
$renderTab = static function (string $tab) use ($settingsPage): string {
    $_GET = ['tab' => $tab];
    ob_start();
    $settingsPage->render();
    return (string) ob_get_clean();
};
$conversation = $renderTab('conversation');
$assert(str_contains($conversation, 'name="conversation_log_enabled"') && str_contains($conversation, 'type="hidden" name="conversation_log_enabled" value="0"'), 'The Conversation tab must offer a recording opt-in that can also be switched off.');
$assert(!preg_match('/name="conversation_log_enabled" value="1" checked/', $conversation), 'Recording must be unchecked by default.');
$assert(str_contains($conversation, 'name="conversation_retention_days"') && str_contains($conversation, '<option value="90" selected>'), 'The default 90-day retention must be selected.');
$assert(str_contains($conversation, 'name="delete_on_uninstall"') && !preg_match('/name="delete_on_uninstall" value="1" checked/', $conversation), 'Delete-on-uninstall must be an unchecked opt-in.');
$assert(str_contains($conversation, 'View conversation history') && str_contains($conversation, 'page=ncli-conversations'), 'The Conversation tab must link to the history page.');
$assert(!str_contains($conversation, 'name="history_turns"'), 'Earlier-turn context must not be offered in the free plugin.');

$appearance = $renderTab('appearance-limits');
$assert(str_contains($appearance, 'name="panel_width"') && str_contains($appearance, '<option value="standard" selected>'), 'Appearance must offer a panel width defaulting to Standard.');
$assert(str_contains($appearance, 'type="color" name="accent_color"') && str_contains($appearance, 'name="accent_color_default" value="1" checked'), 'Appearance must offer an accent colour that defaults to the style colour.');
$assert(str_contains($appearance, 'Powered by NC Interactive') && !preg_match('/name="show_credit" value="1" checked/', $appearance), 'The credit line must be offered but unchecked by default.');
$assert(str_contains($appearance, 'name="visitor_requests_per_day"'), 'The existing limits must remain.');

$profile = $renderTab('profile');
$assert(str_contains($profile, 'name="presentation_unavailable_message"') && str_contains($profile, 'This interactive service is not available yet.'), 'The Profile tab must let the owner edit the unavailable message.');

$help = $renderTab('help');
$assert(str_contains($help, 'earlier questions in the same conversation are not sent'), 'Help must state accurately that only the current question is sent.');
$assert(!str_contains($help, 'relevant conversation context'), 'Help must no longer claim that conversation context is sent.');
$assert(str_contains($help, 'Earlier turns sent as context for follow-up questions'), 'The comparison must record earlier-turn context as a Pro capability.');
$assert(!str_contains($help . $conversation . $appearance, 'NCI Interactive block') && !str_contains($help . $conversation . $appearance, '>NCI Interactive<'), 'User-facing copy must say "NC Interactive", not "NCI Interactive".');

$save = static function (array $data) use ($settingsPage): string {
    $_POST = $data + ['_ncli_nonce' => 'good-nonce', 'action' => 'ncli_save_settings'];
    try {
        $settingsPage->save();
    } catch (NcliRedirect $redirect) {
        return $redirect->getMessage();
    }
    return 'no-redirect';
};
$saved = static fn (): array => CoreSettings::fromArray($GLOBALS['ncliOptions'][CoreSettings::OPTION_NAME] ?? [])->toArray();

$url = $save(['ncli_tab' => 'conversation', 'conversation_log_enabled' => '1', 'conversation_retention_days' => '30', 'delete_on_uninstall' => '1']);
$values = $saved();
$assert(str_contains($url, 'ncli_notice=saved') && $values['conversation_log']['enabled'] === true && $values['conversation_log']['retention_days'] === 30 && $values['lifecycle']['delete_on_uninstall'] === true, 'The Conversation tab must persist recording, retention and uninstall choices.');
$save(['ncli_tab' => 'conversation', 'conversation_log_enabled' => '0', 'conversation_retention_days' => '30', 'delete_on_uninstall' => '0']);
$values = $saved();
$assert($values['conversation_log']['enabled'] === false && $values['lifecycle']['delete_on_uninstall'] === false, 'The unchecked hidden-zero values must switch the options off.');
$save(['ncli_tab' => 'conversation', 'conversation_log_enabled' => '1', 'conversation_retention_days' => '30', 'delete_on_uninstall' => '0']);
$save(['ncli_tab' => 'appearance-limits', 'panel_style' => 'dark', 'panel_width' => 'wide', 'accent_color' => '#C2410C', 'show_credit' => '1', 'visitor_requests_per_day' => '10', 'provider_requests_per_day' => '100']);
$values = $saved();
$assert($values['appearance']['panel_width'] === 'wide' && $values['appearance']['accent_color'] === '#c2410c' && $values['appearance']['show_credit'] === true && $values['appearance']['panel_style'] === 'dark', 'Appearance choices must persist, with the accent normalised to lower case.');
$assert($values['conversation_log']['enabled'] === true, 'Saving another tab must not disturb conversation settings.');
$save(['ncli_tab' => 'appearance-limits', 'panel_style' => 'dark', 'panel_width' => 'wide', 'accent_color' => '#C2410C', 'accent_color_default' => '1', 'visitor_requests_per_day' => '10', 'provider_requests_per_day' => '100']);
$assert($saved()['appearance']['accent_color'] === '', 'Ticking "use the panel style\'s own colour" must clear the accent.');
$before = $GLOBALS['ncliOptions'][CoreSettings::OPTION_NAME];
foreach (['red', '#12345', 'javascript:alert(1)', '#gggggg', 'url(x)'] as $badColour) {
    $url = $save(['ncli_tab' => 'appearance-limits', 'panel_style' => 'dark', 'panel_width' => 'wide', 'accent_color' => $badColour, 'visitor_requests_per_day' => '10', 'provider_requests_per_day' => '100']);
    $assert(str_contains($url, 'ncli_notice=invalid') && $GLOBALS['ncliOptions'][CoreSettings::OPTION_NAME] === $before, 'An invalid accent colour must be rejected without saving: ' . $badColour);
}
$url = $save(['ncli_tab' => 'appearance-limits', 'panel_style' => 'dark', 'panel_width' => 'gigantic', 'visitor_requests_per_day' => '10', 'provider_requests_per_day' => '100']);
$assert(str_contains($url, 'ncli_notice=invalid'), 'An unknown panel width must be rejected.');
$url = $save(['ncli_tab' => 'profile', 'presentation_unavailable_message' => str_repeat('x', 241)]);
$assert(str_contains($url, 'ncli_notice=invalid'), 'An over-long unavailable message must be rejected.');
$save(['ncli_tab' => 'profile', 'presentation_unavailable_message' => 'We are updating our answers. Please come back soon.']);
$assert($saved()['presentation']['unavailable_message'] === 'We are updating our answers. Please come back soon.', 'The unavailable message must persist.');
$url = $save(['ncli_tab' => 'conversation', 'conversation_log_enabled' => '0', 'conversation_retention_days' => '45', 'delete_on_uninstall' => '0']);
$assert(str_contains($url, 'ncli_notice=invalid') && $saved()['conversation_log']['retention_days'] === 30, 'An unsupported retention period must be rejected without changing the stored value.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS: C6 conversations administrator checks verified\n";
