<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;

use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Conversation\ConversationId;
use NicheCleverInteractive\Core\Conversation\ConversationStore;
use NicheCleverInteractive\Core\Conversation\WordPressConversationStore;
use NicheCleverInteractive\Core\Security\AdminActionGuard;
use NicheCleverInteractive\Core\Support\Translator;

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- All dynamic markup uses the tested escapeHtml()/escapeAttribute() wrappers, which keep this PHP 8.1-compatible class usable in pure tests.
// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- AdminActionGuard centralises capability and nonce verification before a state-changing request is read; GET parameters only select a read-only view and are validated against fixed patterns.
// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Every request value is unslashed and validated against a strict pattern before use.
final class ConversationsPage
{
    public const PAGE_SLUG = 'ncli-conversations';
    private const DELETE_ACTION = 'ncli_delete_conversations';
    private const PER_PAGE = 20;

    private AdminActionGuard $guard;
    private ConversationStore $store;
    private ?string $hook = null;
    private ?ExtensionScreens $screens;
    private ?ProfileHub $hub;

    public function __construct(?AdminActionGuard $guard = null, ?ConversationStore $store = null, ?ExtensionScreens $screens = null, ?ProfileHub $hub = null)
    {
        $this->screens = $screens;
        $this->hub = $hub;
        $this->guard = $guard ?? AdminActionGuard::forWordPress();
        $this->store = $store ?? new WordPressConversationStore();
    }

    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        // After SettingsPage (priority 10) has created the parent menu.
        add_action('admin_menu', [$this, 'addMenu'], 11);
        add_action('admin_post_' . self::DELETE_ACTION, [$this, 'handleDelete']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenu(): void
    {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        $hook = add_submenu_page(
            SettingsPage::PAGE_SLUG,
            $this->t('Conversations'),
            $this->t('Conversations'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
        $this->hook = is_string($hook) ? $hook : null;
    }

    public function enqueueAssets(string $hook): void
    {
        if ($this->hook === null || $hook !== $this->hook || !function_exists('wp_enqueue_style') || !function_exists('plugins_url')) {
            return;
        }

        wp_enqueue_style('ncli-core-admin', plugins_url('assets/css/admin.css', dirname(__DIR__, 2) . '/niche-clever-interactive-core.php'), [], NCLI_CORE_VERSION);
    }

    public function render(): void
    {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            $this->deny();
            return;
        }

        $settings = $this->settings();

        echo '<div class="wrap ncli-admin ncli-admin--workspace">';
        echo '<h1 class="screen-reader-text">' . $this->escapeHtml($this->t('Niche Clever Interactive conversations')) . '</h1>';
        echo '<header class="ncli-admin__masthead"><img class="ncli-admin__masthead-art" src="' . $this->escapeAttribute($this->assetUrl('assets/images/ncli-admin-banner.png')) . '" alt="" width="1544" height="500"></header>';
        echo '<nav class="nav-tab-wrapper ncli-admin__tabs" aria-label="' . $this->escapeAttribute($this->t('Niche Clever Interactive conversations')) . '">';
        $governance = $this->governanceActive() && $this->requestedTab() === 'governance';
        if (!$this->governanceActive()) {
            echo '<a class="nav-tab" href="' . $this->escapeAttribute($this->adminUrl(['page' => SettingsPage::PAGE_SLUG, 'tab' => 'conversation'])) . '">' . $this->escapeHtml($this->t('Conversation settings')) . '</a>';
        }
        echo '<a class="nav-tab' . ($governance ? '' : ' nav-tab-active') . '" href="' . $this->escapeAttribute($this->adminUrl(['page' => self::PAGE_SLUG])) . '"' . ($governance ? '' : ' aria-current="page"') . '>' . $this->escapeHtml($this->t('Conversation history')) . '</a>';
        if ($this->governanceActive()) {
            echo '<a class="nav-tab' . ($governance ? ' nav-tab-active' : '') . '" href="' . $this->escapeAttribute($this->adminUrl(['page' => self::PAGE_SLUG, 'tab' => 'governance'])) . '"' . ($governance ? ' aria-current="page"' : '') . '>' . $this->escapeHtml($this->t('Governance')) . '</a>';
        }
        echo '</nav>';

        if ($governance) {
            echo '<section class="ncli-admin__tab-panel ncli-admin__extension">' . $this->screens->bodyHtml('archive') . '</section></div>';
            return;
        }

        $this->renderNotice();

        try {
            $conversationId = $this->requestedConversation();
            if ($this->requestedConfirmation()) {
                $this->renderConfirmDeleteAll();
            } elseif ($conversationId !== null) {
                $this->renderConversation($conversationId);
            } else {
                $this->renderList($settings);
            }
        } catch (\Throwable $exception) {
            echo '<section class="ncli-admin__tab-panel"><p>' . $this->escapeHtml($this->t('Conversation history is not available right now. Check that the plugin has been activated and its database table exists.')) . '</p></section>';
        }

        echo '</div>';
    }

    /** Whether the extension's governance screen is hosted here as a tab. */
    private function governanceActive(): bool
    {
        return $this->screens !== null && $this->hub !== null && $this->hub->active() && $this->screens->declares('archive');
    }

    private function requestedTab(): string
    {
        $tab = $_GET['tab'] ?? '';

        return is_string($tab) && $tab === 'governance' ? $tab : '';
    }

    public function handleDelete(): void
    {
        $nonce = $_POST['_ncli_conversations_nonce'] ?? null;
        if (!$this->guard->allows(self::DELETE_ACTION, $nonce)) {
            $this->deny();
            return;
        }

        $mode = isset($_POST['mode']) && is_string($_POST['mode']) ? $_POST['mode'] : '';
        $removed = 0;
        $notice = 'nothing-selected';

        try {
            if ($mode === 'all' && isset($_POST['confirm']) && $_POST['confirm'] === '1') {
                $removed = $this->store->deleteAll();
                $notice = 'deleted';
            } elseif ($mode === 'selected') {
                $ids = $this->postedIds();
                if ($ids !== []) {
                    $removed = $this->store->deleteConversations($ids);
                    $notice = 'deleted';
                }
            }
        } catch (\Throwable $exception) {
            $notice = 'delete-failed';
        }

        $this->redirect(['page' => self::PAGE_SLUG, 'ncli_notice' => $notice, 'ncli_count' => (string) $removed]);
    }

    private function renderList(CoreSettings $settings): void
    {
        $total = $this->store->countConversations();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($this->requestedPage(), $pages);
        $conversations = $this->store->listConversations(self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        echo '<section class="ncli-admin__tab-panel ncli-admin__conversations">';
        echo '<h2>' . $this->escapeHtml($this->t('Conversation history')) . '</h2>';

        if ($settings->conversationLogEnabled()) {
            $retention = $settings->conversationRetentionDays();
            $text = $retention > 0
                ? sprintf($this->t('Recording is on. Conversations are removed automatically after %d days.'), $retention)
                : $this->t('Recording is on. Conversations are kept until you delete them.');
            echo '<p>' . $this->escapeHtml($text) . '</p>';
        } else {
            echo '<p>' . $this->escapeHtml($this->t('Recording is off, so no new conversations are being stored.')) . ' <a href="' . $this->escapeAttribute($this->adminUrl(['page' => SettingsPage::PAGE_SLUG, 'tab' => 'conversation'])) . '">' . $this->escapeHtml($this->t('Change this in Conversation settings.')) . '</a></p>';
        }

        if ($conversations === []) {
            echo '<p>' . $this->escapeHtml($this->t('There are no stored conversations.')) . '</p></section>';
            return;
        }

        echo '<form method="post" action="' . $this->escapeAttribute($this->postUrl()) . '">';
        echo '<input type="hidden" name="action" value="' . self::DELETE_ACTION . '">';
        echo '<input type="hidden" name="mode" value="selected">';
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field(self::DELETE_ACTION, '_ncli_conversations_nonce');
        }
        echo '<table class="widefat striped ncli-admin__conversation-table"><thead><tr>';
        echo '<td class="check-column"><span class="screen-reader-text">' . $this->escapeHtml($this->t('Select')) . '</span></td>';
        echo '<th scope="col">' . $this->escapeHtml($this->t('First question')) . '</th>';
        echo '<th scope="col">' . $this->escapeHtml($this->t('Questions')) . '</th>';
        echo '<th scope="col">' . $this->escapeHtml($this->t('Started (UTC)')) . '</th>';
        echo '<th scope="col">' . $this->escapeHtml($this->t('Last activity (UTC)')) . '</th>';
        echo '<th scope="col">' . $this->escapeHtml($this->t('Corpus')) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($conversations as $conversation) {
            $id = $conversation['conversation_id'];
            $url = $this->adminUrl(['page' => self::PAGE_SLUG, 'conversation' => $id]);
            echo '<tr>';
            echo '<th scope="row" class="check-column"><input type="checkbox" name="conversation_ids[]" value="' . $this->escapeAttribute($id) . '"><span class="screen-reader-text">' . $this->escapeHtml($this->t('Select this conversation')) . '</span></th>';
            echo '<td><a href="' . $this->escapeAttribute($url) . '">' . $this->escapeHtml($this->excerpt($conversation['first_question'])) . '</a></td>';
            echo '<td>' . $this->escapeHtml((string) $conversation['turn_count']) . '</td>';
            echo '<td>' . $this->escapeHtml($conversation['started_at']) . '</td>';
            echo '<td>' . $this->escapeHtml($conversation['last_at']) . '</td>';
            echo '<td>' . $this->escapeHtml((string) ($conversation['corpus_version'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p class="ncli-admin__conversation-actions"><button type="submit" class="button button-secondary">' . $this->escapeHtml($this->t('Delete selected')) . '</button> ';
        echo '<a class="button button-link-delete" href="' . $this->escapeAttribute($this->adminUrl(['page' => self::PAGE_SLUG, 'confirm' => 'delete-all'])) . '">' . $this->escapeHtml($this->t('Delete all conversations…')) . '</a></p>';
        echo '</form>';

        $this->renderPagination($page, $pages, $total);
        echo '</section>';
    }

    private function renderConversation(string $conversationId): void
    {
        $turns = $this->store->turns($conversationId);
        echo '<section class="ncli-admin__tab-panel ncli-admin__conversations">';
        echo '<p><a href="' . $this->escapeAttribute($this->adminUrl(['page' => self::PAGE_SLUG])) . '">&larr; ' . $this->escapeHtml($this->t('All conversations')) . '</a></p>';
        if ($turns === []) {
            echo '<p>' . $this->escapeHtml($this->t('This conversation no longer exists.')) . '</p></section>';
            return;
        }

        echo '<h2>' . $this->escapeHtml($this->t('Conversation')) . '</h2>';
        foreach ($turns as $turn) {
            echo '<article class="ncli-admin__turn">';
            echo '<p class="ncli-admin__turn-meta">' . $this->escapeHtml($turn->createdAt()) . ' UTC' . ($turn->corpusVersion() !== '' ? ' &middot; ' . $this->escapeHtml($this->t('Corpus')) . ' ' . $this->escapeHtml($turn->corpusVersion()) : '') . '</p>';
            echo '<p class="ncli-admin__turn-question"><strong>' . $this->escapeHtml($this->t('Question')) . '</strong><br>' . nl2br($this->escapeHtml($turn->question())) . '</p>';
            echo '<div class="ncli-admin__turn-answer"><strong>' . $this->escapeHtml($this->t('Answer')) . '</strong><br>' . nl2br($this->escapeHtml($turn->answer())) . '</div>';
            echo '</article>';
        }

        echo '<form method="post" action="' . $this->escapeAttribute($this->postUrl()) . '">';
        echo '<input type="hidden" name="action" value="' . self::DELETE_ACTION . '">';
        echo '<input type="hidden" name="mode" value="selected">';
        echo '<input type="hidden" name="conversation_ids[]" value="' . $this->escapeAttribute($conversationId) . '">';
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field(self::DELETE_ACTION, '_ncli_conversations_nonce');
        }
        echo '<p><button type="submit" class="button button-secondary">' . $this->escapeHtml($this->t('Delete this conversation')) . '</button></p>';
        echo '</form></section>';
    }

    private function renderConfirmDeleteAll(): void
    {
        $total = $this->store->countConversations();
        echo '<section class="ncli-admin__tab-panel ncli-admin__conversations"><h2>' . $this->escapeHtml($this->t('Delete all conversations')) . '</h2>';
        echo '<p>' . $this->escapeHtml(sprintf($this->t('This permanently deletes all %d stored conversations. It cannot be undone, although an older database backup may still contain them.'), $total)) . '</p>';
        echo '<form method="post" action="' . $this->escapeAttribute($this->postUrl()) . '">';
        echo '<input type="hidden" name="action" value="' . self::DELETE_ACTION . '">';
        echo '<input type="hidden" name="mode" value="all">';
        echo '<input type="hidden" name="confirm" value="1">';
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field(self::DELETE_ACTION, '_ncli_conversations_nonce');
        }
        echo '<p><button type="submit" class="button button-primary">' . $this->escapeHtml($this->t('Permanently delete everything')) . '</button> ';
        echo '<a class="button" href="' . $this->escapeAttribute($this->adminUrl(['page' => self::PAGE_SLUG])) . '">' . $this->escapeHtml($this->t('Cancel')) . '</a></p>';
        echo '</form></section>';
    }

    private function renderPagination(int $page, int $pages, int $total): void
    {
        if ($pages <= 1) {
            echo '<p class="description">' . $this->escapeHtml(sprintf($this->t('%d conversations'), $total)) . '</p>';
            return;
        }

        echo '<p class="ncli-admin__pagination">' . $this->escapeHtml(sprintf($this->t('Page %1$d of %2$d (%3$d conversations)'), $page, $pages, $total)) . ' ';
        if ($page > 1) {
            echo '<a class="button" href="' . $this->escapeAttribute($this->adminUrl(['page' => self::PAGE_SLUG, 'paged' => (string) ($page - 1)])) . '">' . $this->escapeHtml($this->t('Previous')) . '</a> ';
        }
        if ($page < $pages) {
            echo '<a class="button" href="' . $this->escapeAttribute($this->adminUrl(['page' => self::PAGE_SLUG, 'paged' => (string) ($page + 1)])) . '">' . $this->escapeHtml($this->t('Next')) . '</a>';
        }
        echo '</p>';
    }

    private function renderNotice(): void
    {
        $notice = $_GET['ncli_notice'] ?? '';
        if (!is_string($notice) || !in_array($notice, ['deleted', 'nothing-selected', 'delete-failed'], true)) {
            return;
        }

        $count = isset($_GET['ncli_count']) && is_string($_GET['ncli_count']) && ctype_digit($_GET['ncli_count']) ? (int) $_GET['ncli_count'] : 0;
        $messages = [
            'deleted' => sprintf($this->t('%d stored questions and answers were deleted.'), $count),
            'nothing-selected' => $this->t('No conversation was selected, so nothing was deleted.'),
            'delete-failed' => $this->t('The conversations could not be deleted. Nothing further was changed.'),
        ];
        $class = $notice === 'deleted' ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . $class . '"><p>' . $this->escapeHtml($messages[$notice]) . '</p></div>';
    }

    private function excerpt(string $question): string
    {
        $question = trim((string) preg_replace('/\s+/', ' ', $question));
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($question) > 110 ? mb_substr($question, 0, 107) . '…' : $question;
        }

        return strlen($question) > 110 ? substr($question, 0, 107) . '...' : $question;
    }

    private function requestedConversation(): ?string
    {
        $value = $_GET['conversation'] ?? null;

        return ConversationId::isValid($value) ? $value : null;
    }

    private function requestedConfirmation(): bool
    {
        return ($_GET['confirm'] ?? null) === 'delete-all';
    }

    private function requestedPage(): int
    {
        $value = $_GET['paged'] ?? '1';

        return is_string($value) && ctype_digit($value) && (int) $value >= 1 ? min((int) $value, 100000) : 1;
    }

    /** @return list<string> */
    private function postedIds(): array
    {
        $raw = $_POST['conversation_ids'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            if (is_string($value)) {
                $value = $this->unslash($value);
                if (ConversationId::isValid($value)) {
                    $ids[] = $value;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function settings(): CoreSettings
    {
        $stored = function_exists('get_option') ? get_option(CoreSettings::OPTION_NAME, CoreSettings::defaults()) : CoreSettings::defaults();
        try {
            return CoreSettings::fromArray(is_array($stored) ? $stored : CoreSettings::defaults());
        } catch (\InvalidArgumentException $exception) {
            return CoreSettings::fromArray(CoreSettings::defaults());
        }
    }

    private function redirect(array $query): void
    {
        if (function_exists('wp_safe_redirect')) {
            wp_safe_redirect($this->adminUrl($query));
            exit;
        }
    }

    private function deny(): void
    {
        if (function_exists('wp_die')) {
            wp_die($this->t('You are not permitted to manage NCI conversations.'));
        }
    }

    private function assetUrl(string $path): string
    {
        if (!function_exists('plugins_url')) {
            return '';
        }

        // Include the file's modification time so a replaced image is never served from a stale browser cache.
        $url = plugins_url($path, dirname(__DIR__, 2) . '/niche-clever-interactive-core.php');
        $file = dirname(__DIR__, 2) . '/' . $path;
        $modified = is_file($file) ? (int) filemtime($file) : 0;

        return $modified > 0 ? $url . (str_contains($url, '?') ? '&' : '?') . 'ver=' . $modified : $url;
    }

    private function adminUrl(array $query): string
    {
        if (function_exists('admin_url') && function_exists('add_query_arg')) {
            return add_query_arg($query, admin_url('admin.php'));
        }

        return '/wp-admin/admin.php';
    }

    private function postUrl(): string
    {
        return function_exists('admin_url') ? admin_url('admin-post.php') : '/wp-admin/admin-post.php';
    }

    private function unslash(string $value): string
    {
        return function_exists('wp_unslash') ? wp_unslash($value) : stripslashes($value);
    }

    private function escapeHtml(string $value): string
    {
        return function_exists('esc_html') ? esc_html($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function escapeAttribute(string $value): string
    {
        return function_exists('esc_attr') ? esc_attr($value) : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function t(string $text): string
    {
        return Translator::text($text);
    }
}
