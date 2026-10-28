<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;

use NicheCleverInteractive\Core\Configuration\CoreSettings;
use NicheCleverInteractive\Core\Conversation\ConversationId;
use NicheCleverInteractive\Core\Conversation\ConversationStore;
use NicheCleverInteractive\Core\Conversation\WordPressConversationStore;
use NicheCleverInteractive\Core\Security\AdminActionGuard;

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
            __('Conversations', 'niche-clever-interactive'),
            __('Conversations', 'niche-clever-interactive'),
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
        // The help "?" notes on this page, including the governance tab an extension hosts, need the same script as Settings.
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script('ncli-core-admin', plugins_url('assets/js/admin.js', dirname(__DIR__, 2) . '/niche-clever-interactive-core.php'), [], NCLI_CORE_VERSION, true);
        }
    }

    public function render(): void
    {
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            $this->deny();
            return;
        }

        $settings = $this->settings();

        echo '<div class="wrap ncli-admin ncli-admin--workspace">';
        echo '<h1 class="screen-reader-text">' . esc_html(__('Niche Clever Interactive conversations', 'niche-clever-interactive')) . '</h1>';
        echo '<header class="ncli-admin__masthead"><img class="ncli-admin__masthead-art" src="' . esc_attr($this->assetUrl('assets/images/ncli-admin-banner.png')) . '" alt="" width="1544" height="500"></header>';
        echo '<nav class="nav-tab-wrapper ncli-admin__tabs" aria-label="' . esc_attr(__('Niche Clever Interactive conversations', 'niche-clever-interactive')) . '">';
        $governance = $this->governanceActive() && $this->requestedTab() === 'governance';
        if (!$this->governanceActive()) {
            echo '<a class="nav-tab" href="' . esc_attr($this->adminUrl(['page' => SettingsPage::PAGE_SLUG, 'tab' => 'conversation'])) . '">' . esc_html(__('Conversation settings', 'niche-clever-interactive')) . '</a>';
        }
        echo '<a class="nav-tab' . ($governance ? '' : ' nav-tab-active') . '" href="' . esc_attr($this->adminUrl(['page' => self::PAGE_SLUG])) . '"' . ($governance ? '' : ' aria-current="page"') . '>' . esc_html(__('Conversation history', 'niche-clever-interactive')) . '</a>';
        if ($this->governanceActive()) {
            echo '<a class="nav-tab' . ($governance ? ' nav-tab-active' : '') . '" href="' . esc_attr($this->adminUrl(['page' => self::PAGE_SLUG, 'tab' => 'governance'])) . '"' . ($governance ? ' aria-current="page"' : '') . '>' . esc_html(__('Governance', 'niche-clever-interactive')) . '</a>';
        }
        echo '</nav>';

        if ($governance) {
            echo '<section class="ncli-admin__tab-panel ncli-admin__extension">' . wp_kses($this->screens->bodyHtml('archive'), ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols()) . '</section></div>';
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
            echo '<section class="ncli-admin__tab-panel"><p>' . esc_html(__('Conversation history is not available right now. Check that the plugin has been activated and its database table exists.', 'niche-clever-interactive')) . '</p></section>';
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
        $tab = AdminRequest::queryText('tab');

        return $tab === 'governance' ? $tab : '';
    }

    public function handleDelete(): void
    {
        $nonce = AdminRequest::postText('_ncli_conversations_nonce');
        if (!$this->guard->allows(self::DELETE_ACTION, $nonce)) {
            $this->deny();
            return;
        }

        $mode = AdminRequest::postText('mode');
        $removed = 0;
        $notice = 'nothing-selected';

        try {
            if ($mode === 'all' && AdminRequest::postCheckbox('confirm')) {
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
        echo '<h2>' . esc_html(__('Conversation history', 'niche-clever-interactive')) . '</h2>';

        if ($settings->conversationLogEnabled()) {
            $retention = $settings->conversationRetentionDays();
            $text = $retention > 0
                ? /* translators: %d: number of days that conversations are retained. */ sprintf(__('Recording is on. Conversations are removed automatically after %d days.', 'niche-clever-interactive'), $retention)
                : __('Recording is on. Conversations are kept until you delete them.', 'niche-clever-interactive');
            echo '<p>' . esc_html($text) . '</p>';
        } else {
            echo '<p>' . esc_html(__('Recording is off, so no new conversations are being stored.', 'niche-clever-interactive')) . ' <a href="' . esc_attr($this->adminUrl(['page' => SettingsPage::PAGE_SLUG, 'tab' => 'conversation'])) . '">' . esc_html(__('Change this in Conversation settings.', 'niche-clever-interactive')) . '</a></p>';
        }

        if ($conversations === []) {
            echo '<p>' . esc_html(__('There are no stored conversations.', 'niche-clever-interactive')) . '</p></section>';
            return;
        }

        echo '<form method="post" action="' . esc_attr($this->postUrl()) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::DELETE_ACTION) . '">';
        echo '<input type="hidden" name="mode" value="selected">';
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field(self::DELETE_ACTION, '_ncli_conversations_nonce');
        }
        echo '<table class="widefat striped ncli-admin__conversation-table"><thead><tr>';
        echo '<td class="check-column"><span class="screen-reader-text">' . esc_html(__('Select', 'niche-clever-interactive')) . '</span></td>';
        echo '<th scope="col">' . esc_html(__('First question', 'niche-clever-interactive')) . '</th>';
        echo '<th scope="col">' . esc_html(__('Questions', 'niche-clever-interactive')) . '</th>';
        echo '<th scope="col">' . esc_html(__('Started (UTC)', 'niche-clever-interactive')) . '</th>';
        echo '<th scope="col">' . esc_html(__('Last activity (UTC)', 'niche-clever-interactive')) . '</th>';
        echo '<th scope="col">' . esc_html(__('Corpus', 'niche-clever-interactive')) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($conversations as $conversation) {
            $id = $conversation['conversation_id'];
            $url = $this->adminUrl(['page' => self::PAGE_SLUG, 'conversation' => $id]);
            echo '<tr>';
            echo '<th scope="row" class="check-column"><input type="checkbox" name="conversation_ids[]" value="' . esc_attr($id) . '"><span class="screen-reader-text">' . esc_html(__('Select this conversation', 'niche-clever-interactive')) . '</span></th>';
            echo '<td><a href="' . esc_attr($url) . '">' . esc_html($this->excerpt($conversation['first_question'])) . '</a></td>';
            echo '<td>' . esc_html((string) $conversation['turn_count']) . '</td>';
            echo '<td>' . esc_html($conversation['started_at']) . '</td>';
            echo '<td>' . esc_html($conversation['last_at']) . '</td>';
            echo '<td>' . esc_html((string) ($conversation['corpus_version'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p class="ncli-admin__conversation-actions"><button type="submit" class="button button-secondary">' . esc_html(__('Delete selected', 'niche-clever-interactive')) . '</button> ';
        echo '<a class="button button-link-delete" href="' . esc_attr($this->adminUrl(['page' => self::PAGE_SLUG, 'confirm' => 'delete-all'])) . '">' . esc_html(__('Delete all conversations…', 'niche-clever-interactive')) . '</a></p>';
        echo '</form>';

        $this->renderPagination($page, $pages, $total);
        echo '</section>';
    }

    private function renderConversation(string $conversationId): void
    {
        $turns = $this->store->turns($conversationId);
        echo '<section class="ncli-admin__tab-panel ncli-admin__conversations">';
        echo '<p><a href="' . esc_attr($this->adminUrl(['page' => self::PAGE_SLUG])) . '">&larr; ' . esc_html(__('All conversations', 'niche-clever-interactive')) . '</a></p>';
        if ($turns === []) {
            echo '<p>' . esc_html(__('This conversation no longer exists.', 'niche-clever-interactive')) . '</p></section>';
            return;
        }

        echo '<h2>' . esc_html(__('Conversation', 'niche-clever-interactive')) . '</h2>';
        foreach ($turns as $turn) {
            echo '<article class="ncli-admin__turn">';
            echo '<p class="ncli-admin__turn-meta">' . esc_html($turn->createdAt()) . ' UTC' . ($turn->corpusVersion() !== '' ? ' &middot; ' . esc_html(__('Corpus', 'niche-clever-interactive')) . ' ' . esc_html($turn->corpusVersion()) : '') . '</p>';
            echo '<p class="ncli-admin__turn-question"><strong>' . esc_html(__('Question', 'niche-clever-interactive')) . '</strong><br>' . nl2br(esc_html($turn->question())) . '</p>';
            echo '<div class="ncli-admin__turn-answer"><strong>' . esc_html(__('Answer', 'niche-clever-interactive')) . '</strong><br>' . nl2br(esc_html($turn->answer())) . '</div>';
            echo '</article>';
        }

        echo '<form method="post" action="' . esc_attr($this->postUrl()) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::DELETE_ACTION) . '">';
        echo '<input type="hidden" name="mode" value="selected">';
        echo '<input type="hidden" name="conversation_ids[]" value="' . esc_attr($conversationId) . '">';
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field(self::DELETE_ACTION, '_ncli_conversations_nonce');
        }
        echo '<p><button type="submit" class="button button-secondary">' . esc_html(__('Delete this conversation', 'niche-clever-interactive')) . '</button></p>';
        echo '</form></section>';
    }

    private function renderConfirmDeleteAll(): void
    {
        $total = $this->store->countConversations();
        echo '<section class="ncli-admin__tab-panel ncli-admin__conversations"><h2>' . esc_html(__('Delete all conversations', 'niche-clever-interactive')) . '</h2>';
        /* translators: %d: number of stored conversations that will be deleted. */
        echo '<p>' . esc_html(sprintf(__('This permanently deletes all %d stored conversations. It cannot be undone, although an older database backup may still contain them.', 'niche-clever-interactive'), $total)) . '</p>';
        echo '<form method="post" action="' . esc_attr($this->postUrl()) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::DELETE_ACTION) . '">';
        echo '<input type="hidden" name="mode" value="all">';
        echo '<input type="hidden" name="confirm" value="1">';
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field(self::DELETE_ACTION, '_ncli_conversations_nonce');
        }
        echo '<p><button type="submit" class="button button-primary">' . esc_html(__('Permanently delete everything', 'niche-clever-interactive')) . '</button> ';
        echo '<a class="button" href="' . esc_attr($this->adminUrl(['page' => self::PAGE_SLUG])) . '">' . esc_html(__('Cancel', 'niche-clever-interactive')) . '</a></p>';
        echo '</form></section>';
    }

    private function renderPagination(int $page, int $pages, int $total): void
    {
        if ($pages <= 1) {
            /* translators: %d: total number of stored conversations. */
            echo '<p class="description">' . esc_html(sprintf(__('%d conversations', 'niche-clever-interactive'), $total)) . '</p>';
            return;
        }

        /* translators: 1: current page number, 2: total pages, 3: total stored conversations. */
        echo '<p class="ncli-admin__pagination">' . esc_html(sprintf(__('Page %1$d of %2$d (%3$d conversations)', 'niche-clever-interactive'), $page, $pages, $total)) . ' ';
        if ($page > 1) {
            echo '<a class="button" href="' . esc_attr($this->adminUrl(['page' => self::PAGE_SLUG, 'paged' => (string) ($page - 1)])) . '">' . esc_html(__('Previous', 'niche-clever-interactive')) . '</a> ';
        }
        if ($page < $pages) {
            echo '<a class="button" href="' . esc_attr($this->adminUrl(['page' => self::PAGE_SLUG, 'paged' => (string) ($page + 1)])) . '">' . esc_html(__('Next', 'niche-clever-interactive')) . '</a>';
        }
        echo '</p>';
    }

    private function renderNotice(): void
    {
        $notice = AdminRequest::queryText('ncli_notice');
        if (!in_array($notice, ['deleted', 'nothing-selected', 'delete-failed'], true)) {
            return;
        }

        $count = AdminRequest::queryPositiveInt('ncli_count', 0);
        $messages = [
            'deleted' => /* translators: %d: number of stored questions and answers deleted. */ sprintf(__('%d stored questions and answers were deleted.', 'niche-clever-interactive'), $count),
            'nothing-selected' => __('No conversation was selected, so nothing was deleted.', 'niche-clever-interactive'),
            'delete-failed' => __('The conversations could not be deleted. Nothing further was changed.', 'niche-clever-interactive'),
        ];
        $class = $notice === 'deleted' ? 'notice-success' : 'notice-error';
        echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($messages[$notice]) . '</p></div>';
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
        $value = AdminRequest::queryText('conversation');

        return ConversationId::isValid($value) ? $value : null;
    }

    private function requestedConfirmation(): bool
    {
        return AdminRequest::queryText('confirm') === 'delete-all';
    }

    private function requestedPage(): int
    {
        return min(AdminRequest::queryPositiveInt('paged'), 100000);
    }

    /** @return list<string> */
    private function postedIds(): array
    {
        $ids = [];
        foreach (AdminRequest::postList('conversation_ids') as $value) {
            if (ConversationId::isValid($value)) {
                $ids[] = $value;
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
            wp_die(esc_html(__('You are not permitted to manage NCI conversations.', 'niche-clever-interactive')));
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

    private function t(string $text): string
    {
        return $text;
    }
}
