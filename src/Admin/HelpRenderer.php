<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;


/**
 * The Help Centre panel (overview, frequently asked questions and the Core and Premium comparison).
 * It draws the same content wherever it is shown; the page that hosts it supplies the address of
 * each section.
 */
final class HelpRenderer
{
    /** Public information page for the separate NCI Premium companion. */
    public const PREMIUM_URL = 'https://www.nicheclever.co.uk/nc-interactive-premium/';

    private ProfileFieldRenderer $fields;
    /** @var callable(string): string */
    private $helpUrl;

    /** @param callable(string): string $helpUrl Address of a help section (`overview`, `faq` or `premium`). */
    public function __construct(ProfileFieldRenderer $fields, callable $helpUrl)
    {
        $this->fields = $fields;
        $this->helpUrl = $helpUrl;
    }

    /** Draws the Help Centre panel for the section chosen in the address (`help=overview|faq|premium`). */
    public function render(): void
    {
        $section = $this->currentHelpSection();
        $sections = [
            'overview' => __('Overview', 'niche-clever-interactive'),
            'faq' => __('Frequently asked questions', 'niche-clever-interactive'),
            'premium' => __('NCI Premium', 'niche-clever-interactive'),
        ];

        echo '<section class="ncli-admin__tab-panel ncli-admin__help"><h2>' . esc_html(__('Help Centre', 'niche-clever-interactive')) . '</h2>';
        echo '<nav class="ncli-admin__subtabs" aria-label="' . esc_attr(__('Help sections', 'niche-clever-interactive')) . '">';
        foreach ($sections as $id => $label) {
            $active = $id === $section;
            $url = call_user_func($this->helpUrl, $id);
            echo '<a class="ncli-admin__subtab' . ($active ? ' is-active' : '') . '"' . ($active ? ' aria-current="page"' : '') . ' href="' . esc_attr($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        if ($section === 'faq') {
            $this->renderHelpFaq();
        } elseif ($section === 'premium') {
            $this->renderHelpPremium();
        } else {
            $this->renderHelpOverview();
        }

        echo '</section>';
    }

    private function currentHelpSection(): string
    {
        $raw = AdminRequest::queryText('help');
        $value = function_exists('wp_unslash') ? (string) wp_unslash($raw) : $raw;
        $value = function_exists('sanitize_key') ? sanitize_key($value) : strtolower($value);

        return in_array($value, ['overview', 'faq', 'premium'], true) ? $value : 'overview';
    }

    private function renderHelpOverview(): void
    {
        $fileSearchUrl = 'https://platform.openai.com/docs/guides/tools-file-search';
        $vectorStoreUrl = 'https://platform.openai.com/docs/api-reference/vector-stores';
        echo '<h3>' . esc_html(__('What Niche Clever Interactive is for', 'niche-clever-interactive')) . '</h3>';
        echo '<p>' . esc_html(__('Niche Clever Interactive adds one configurable question-and-answer panel to a WordPress site. It uses the OpenAI Responses API and File Search against an existing vector store selected by the site administrator. It is designed for material you have prepared for visitors to explore.', 'niche-clever-interactive')) . '</p>';
        echo '<h3>' . esc_html(__('Before you connect', 'niche-clever-interactive')) . '</h3>';
        echo '<ol><li>' . esc_html(__('You need a WordPress site where you can install and configure plugins.', 'niche-clever-interactive')) . '</li><li>' . esc_html(__('You need your own OpenAI API account, billing controls and API key.', 'niche-clever-interactive')) . '</li><li>' . esc_html(__('Keep the API key in server configuration, using NCLI_OPENAI_API_KEY_DEFAULT. Niche Clever Interactive does not provide a field for entering or storing it in WordPress.', 'niche-clever-interactive')) . '</li><li>' . esc_html(__('Prepare an existing File Search vector store containing material suitable for public answers.', 'niche-clever-interactive')) . '</li></ol>';
        echo '<h3>' . esc_html(__('Prepare a useful corpus', 'niche-clever-interactive')) . '</h3>';
        echo '<p>' . esc_html(__('Use material that is accurate, current enough for the questions you expect, and appropriate to share through a public question interface. The plugin does not decide whether a document is suitable, current or complete. Review your source material before you connect it.', 'niche-clever-interactive')) . '</p>';
        echo '<h3>' . esc_html(__('Create and connect a vector store', 'niche-clever-interactive')) . '</h3>';
        echo '<p>' . esc_html(__('Create or select a vector store in your own OpenAI Platform project, add approved files there and wait for indexing to complete. Then enter the existing store reference and a corpus version in AI & Corpus, save the settings and run the connection test.', 'niche-clever-interactive')) . '</p>';
        echo '<p><a href="' . esc_attr($fileSearchUrl) . '" target="_blank" rel="noopener">' . esc_html(__('OpenAI File Search documentation', 'niche-clever-interactive')) . '</a> · <a href="' . esc_attr($vectorStoreUrl) . '" target="_blank" rel="noopener">' . esc_html(__('OpenAI vector store documentation', 'niche-clever-interactive')) . '</a></p>';
        echo '<h3>' . esc_html(__('Choosing a model and managing cost', 'niche-clever-interactive')) . '</h3>';
        echo '<p>' . esc_html(__('Each public question is billed by OpenAI to your own account. The settings in AI & Corpus change how much each answer costs and how long it takes. Core does not estimate or display prices, because they change; check OpenAI pricing for current figures.', 'niche-clever-interactive')) . '</p>';
        echo wp_kses($this->fields->renderModelTable(), ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
        echo '<ul><li><strong>' . esc_html(__('Model:', 'niche-clever-interactive')) . '</strong> ' . esc_html(__('The largest cost lever. Economy models cost far less per answer than Flagship models, and Flagship models suit nuanced questions.', 'niche-clever-interactive')) . '</li>'
            . '<li><strong>' . esc_html(__('Retrieval depth:', 'niche-clever-interactive')) . '</strong> ' . esc_html(__('More retrieved passages give the model more to read, which raises cost and response time and can help broad questions.', 'niche-clever-interactive')) . '</li>'
            . '<li><strong>' . esc_html(__('Maximum answer length:', 'niche-clever-interactive')) . '</strong> ' . esc_html(__('Caps output, including reasoning. Pair it with the Concise, Balanced or Detailed response preset. A very low cap can truncate answers.', 'niche-clever-interactive')) . '</li>'
            . '<li><strong>' . esc_html(__('Reasoning effort:', 'niche-clever-interactive')) . '</strong> ' . esc_html(__('Higher effort can improve hard answers but uses more tokens and takes longer. Leave it at the provider default unless you have a reason to change it.', 'niche-clever-interactive')) . '</li>'
            . '<li><strong>' . esc_html(__('Request timeout:', 'niche-clever-interactive')) . '</strong> ' . esc_html(__('Longer limits suit slower, higher-effort requests. Your hosting limits may stop a request sooner. Core makes one attempt and does not retry.', 'niche-clever-interactive')) . '</li></ul>';
        echo '<p>' . esc_html(__('Example profiles: for lowest cost, choose an Economy model with a small retrieval depth and a Concise preset. For best quality, choose a Flagship model with a larger retrieval depth and a Detailed preset.', 'niche-clever-interactive')) . '</p>';
        echo '<p>' . esc_html(__('Also set a usage limit in your OpenAI dashboard. Core\'s request limits help, but they are not a billing control.', 'niche-clever-interactive')) . ' <a href="https://openai.com/api/pricing/" target="_blank" rel="noopener">' . esc_html(__('OpenAI API pricing', 'niche-clever-interactive')) . '</a></p>';
        echo '<h3>' . esc_html(__('Safe public use', 'niche-clever-interactive')) . '</h3>';
        echo '<p>' . esc_html(__('Each public question is sent to OpenAI to generate an answer; earlier questions in the same conversation are not sent with it by Core itself. The separate NCI Premium information page records a possible companion scope for limited earlier-turn context. The plugin limits visitor and site-wide requests, but those safeguards do not replace monitoring your API account and spend. Do not use the panel as a substitute for human review.', 'niche-clever-interactive')) . '</p>';
        echo '<h3>' . esc_html(__('What it does not do', 'niche-clever-interactive')) . '</h3>';
        echo '<p>' . esc_html(__('Niche Clever Interactive does not create or manage your vector store, upload documents, store your API key in WordPress, or publish provider citations or source links. It keeps no record of visitor conversations unless you switch on the optional conversation history in the Conversation tab.', 'niche-clever-interactive')) . '</p>';
    }

    private function renderHelpFaq(): void
    {
        $groups = [
            __('Setup and connection', 'niche-clever-interactive') => [
                [__('What do I need before I start?', 'niche-clever-interactive'), __('An OpenAI API account with billing controls and an API key, an OpenAI File Search vector store with your approved files indexed, a WordPress 6.5 or later site and PHP 8.1 or later.', 'niche-clever-interactive')],
                [__('Where does the API key go?', 'niche-clever-interactive'), __('Define NCLI_OPENAI_API_KEY_DEFAULT in wp-config.php or equivalent secure server configuration. Never add the key to the plugin settings, page content or a public repository. Defining the key is all the server setup the plugin needs; there is no separate switch to turn on live answers.', 'niche-clever-interactive')],
                [__('Can I switch the provider off without removing the key?', 'niche-clever-interactive'), __('Yes. Set Visitor availability to Unavailable in the Setup tab, or untick Enable the Core profile in the Profile tab. Server administrators can also define NCLI_CORE_LIVE_TRANSPORT as false in wp-config.php to stop all provider requests while the key stays in place.', 'niche-clever-interactive')],
                [__('Does the plugin upload or index my documents?', 'niche-clever-interactive'), __('No. It connects to an existing vector store that you manage through your own OpenAI account.', 'niche-clever-interactive')],
                [__('What is the corpus version for?', 'niche-clever-interactive'), __('It is a label you choose, such as v10, for the material in your vector store. Changing it requires a fresh connection test, so answers are never served against material you have not re-checked. Change it whenever you change the files in the store.', 'niche-clever-interactive')],
                [__('What does Visitor availability do?', 'niche-clever-interactive'), __('The panel answers only when it is enabled and its current configuration has passed the connection test. Otherwise visitors see your unavailable message instead.', 'niche-clever-interactive')],
                [__('Why did my connection test fail?', 'niche-clever-interactive'), __('Check the server-side key, transport configuration, vector store reference, corpus version and your provider account. Diagnostics shows the latest provider problem in plain words and never reveals provider payloads or credentials.', 'niche-clever-interactive')],
                [__('Why is the public panel unavailable after changing settings?', 'niche-clever-interactive'), __('A changed key, vector store, corpus version, response guidance, response preset, model, reasoning effort, retrieval depth, answer length or timeout requires a fresh successful connection test before public answers are available again.', 'niche-clever-interactive')],
            ],
            __('Answers, models and cost', 'niche-clever-interactive') => [
                [__('Who pays for the answers?', 'niche-clever-interactive'), __('Each public question is billed by OpenAI to your own account. Core shows no prices, so check OpenAI pricing and set a usage limit in your OpenAI dashboard as well as Core\'s request limits.', 'niche-clever-interactive')],
                [__('Which model and settings should I choose?', 'niche-clever-interactive'), __('The Overview tab explains each setting. For the lowest cost use an Economy model, a small retrieval depth and the Concise preset. For the best quality use a Flagship model, a larger retrieval depth and the Detailed preset.', 'niche-clever-interactive')],
                [__('What do the response guidance and presets do?', 'niche-clever-interactive'), __('They shape tone, format and level of detail only. Retrieval, safety, privacy and disclosure rules always take priority, and changing them requires a fresh connection test.', 'niche-clever-interactive')],
                [__('Can answers appear progressively?', 'niche-clever-interactive'), __('Yes. Progressive delivery is enabled by default. Visitors first see the processing message you set in Conversation, then answer text can appear as WordPress receives it. If the browser or hosting transport cannot stream, that message remains until the complete validated answer is ready. Turn off Progressively reveal answers in Conversation to use the final-answer view everywhere.', 'niche-clever-interactive')],
                [__('Does it show sources or citations?', 'niche-clever-interactive'), __('No. Core never publishes provider citations or source links. The NCI Premium information page describes an optional companion scope for owner-approved evidence links.', 'niche-clever-interactive')],
                [__('Why did the panel refuse my question?', 'niche-clever-interactive'), __('Core applies fixed safety rules. A question that tries to override its instructions or reveal them is declined with a short refusal.', 'niche-clever-interactive')],
                [__('Why does it say a verified answer is not available?', 'niche-clever-interactive'), __('The answer did not come from a completed search of your corpus. Check the vector store reference and corpus version, then run the connection test again.', 'niche-clever-interactive')],
                [__('Can visitors ask follow-up questions?', 'niche-clever-interactive'), __('Yes, but on its own Core answers each question separately: earlier questions in the same conversation are not sent with it. The NCI Premium information page describes a companion scope for limited earlier-turn context.', 'niche-clever-interactive')],
                [__('Are answers cached?', 'niche-clever-interactive'), __('Core itself does not cache: each question is a fresh request to OpenAI. The NCI Premium information page describes a companion scope for owner-approved answer reuse.', 'niche-clever-interactive')],
                [__('Can visitors ask in other languages?', 'niche-clever-interactive'), __('They can ask in any language the model understands. The interface text is translation-ready, while wording you write in the Profile and Conversation tabs appears exactly as you wrote it.', 'niche-clever-interactive')],
            ],
            __('Visitors, limits and privacy', 'niche-clever-interactive') => [
                [__('What information is sent to OpenAI?', 'niche-clever-interactive'), __('Only the visitor\'s current question, together with your instructions and the vector store reference, is sent when the enabled panel is used. Earlier questions in the same conversation are not sent with it by Core itself. The NCI Premium information page records a possible companion scope for earlier-turn context and chronology records.', 'niche-clever-interactive')],
                [__('Does it send analytics anywhere?', 'niche-clever-interactive'), __('No. The panel announces question and answer milestones inside the visitor\'s browser as an ncli:analytics event, so a consent-managed analytics tool you add yourself can listen for them. Core itself sends nothing to Google or any other analytics service, stores nothing and never includes question or answer text, page paths, query values or browser identifiers. Each event carries only validated profile and authored-question identifiers, whether the question was authored or typed, whether the answer was live or reused and a general error category.', 'niche-clever-interactive')],
                [__('Why does the panel say the question allowance has been reached?', 'niche-clever-interactive'), __('Either one visitor has used their daily questions or the whole site has used its daily provider allowance. Both limits are set in Appearance & Limits, count by UTC day and start again at UTC midnight. Visitors are counted with a privacy-bounded code, not a stored address, and no extra server setup is needed for this.', 'niche-clever-interactive')],
                [__('Does it keep a record of visitors\' conversations?', 'niche-clever-interactive'), __('Not unless you switch on the optional conversation history in the Conversation tab. It is off by default. If you turn it on you choose how long records are kept and can review and delete them under NC Interactive > Conversations.', 'niche-clever-interactive')],
                [__('How do I review or delete stored conversations?', 'niche-clever-interactive'), __('Open NC Interactive > Conversations to view them and to delete one, several or all. Records are removed automatically after the retention period you choose. Deleted records can reappear if you restore an older database backup, so repeat the deletion after a restore.', 'niche-clever-interactive')],
                [__('Can I respond to a privacy request about a visitor?', 'niche-clever-interactive'), __('WordPress\'s personal-data export and erase tools are supported, but Core stores no name, address, account or browser identifier with a conversation, so a record cannot be matched to a person. Use the Conversations screen to remove records you need to remove.', 'niche-clever-interactive')],
                [__('What happens to my data if I delete the plugin?', 'niche-clever-interactive'), __('By default nothing is removed, so a reinstall can pick up your settings, request counters and stored conversations. If you turn on Delete data on uninstall in the Conversation tab, deleting the plugin removes all of them.', 'niche-clever-interactive')],
                [__('What should I put in my privacy policy?', 'niche-clever-interactive'), __('The plugin offers suggested wording in the WordPress privacy policy guide, describing the OpenAI service and the optional history. Review it against your own practices and your OpenAI account terms.', 'niche-clever-interactive')],
                [__('What does Reset conversation do?', 'niche-clever-interactive'), __('It clears the thread in that visitor\'s browser tab and starts a new conversation. Nothing is saved in the browser, and any history you have chosen to keep on the site is not affected.', 'niche-clever-interactive')],
            ],
            __('Appearance and placement', 'niche-clever-interactive') => [
                [__('How do I put the panel on a page?', 'niche-clever-interactive'), __('Add the NC Interactive block to a page, or use the [ncli_interactive] shortcode. The Placement tab shows where Core has detected a published panel.', 'niche-clever-interactive')],
                [__('Can I use more than one panel?', 'niche-clever-interactive'), __('You can place the panel on as many pages as you like, but every placement uses the same single profile. Several knowledge profiles are available in NCI Premium.', 'niche-clever-interactive')],
                [__('How do I change how it looks?', 'niche-clever-interactive'), __('Choose the style, width and accent colour in Appearance & Limits. Edit the title, headline, introduction and unavailable message in Profile, and the placeholder, processing message and footer labels in Conversation. Finer colour control for questions and answers is available in NCI Premium.', 'niche-clever-interactive')],
                [__('Do I have to show a credit link?', 'niche-clever-interactive'), __('No. The optional Powered by NC Interactive link is off by default and appears only if you turn it on in Appearance & Limits.', 'niche-clever-interactive')],
                [__('Is the panel accessible?', 'niche-clever-interactive'), __('The panel and the settings screens are built to work with a keyboard and a screen reader, with labelled controls, visible focus and announcements when an answer arrives, and they respect reduced-motion settings.', 'niche-clever-interactive')],
            ],
            __('Troubleshooting', 'niche-clever-interactive') => [
                [__('The panel says the service is temporarily unavailable. What happened?', 'niche-clever-interactive'), __('The provider did not return a usable answer in time. The most common cause is a timeout when the reasoning effort is high. Open Diagnostics: "Last provider problem" shows the reason, when it happened and what to try, such as lowering the reasoning effort or choosing a faster model. Run the connection test again after changing a model setting.', 'niche-clever-interactive')],
                [__('The panel shows my unavailable message although I enabled it. Why?', 'niche-clever-interactive'), __('Open Diagnostics and read the readiness state. It tells you whether the key, the vector store, the corpus version or a current connection test is missing.', 'niche-clever-interactive')],
                [__('I changed the files in my vector store. What should I do?', 'niche-clever-interactive'), __('Wait for OpenAI to finish indexing, change the corpus version in Profile, save and run the connection test again.', 'niche-clever-interactive')],
                [__('What does Diagnostics show?', 'niche-clever-interactive'), __('The readiness state, the connection test result and the latest provider problem. It never shows a credential, provider payload, file identifier or retrieved content.', 'niche-clever-interactive')],
            ],
            __('Editions', 'niche-clever-interactive') => [
                [__('What is NCI Premium?', 'niche-clever-interactive'), __('NCI Premium is a separate Niche Clever companion described on an informational page. That page records its intended scope, including several knowledge profiles, approved evidence links, authored questions, conversation governance, answer reuse and finer colour control. It makes no purchase, download, pricing or availability promise. This plugin, NCI Core, contains no Premium code, and nothing in Core is locked, limited or time-restricted.', 'niche-clever-interactive')],
            ],
        ];

        echo '<h3>' . esc_html(__('Frequently asked questions', 'niche-clever-interactive')) . '</h3>';
        foreach ($groups as $group => $questions) {
            echo '<h4 class="ncli-admin__faq-group">' . esc_html($group) . '</h4>';
            foreach ($questions as $question) {
                echo '<details><summary>' . esc_html($question[0]) . '</summary><p>' . esc_html($question[1]) . '</p></details>';
            }
        }
    }

    private function renderHelpPremium(): void
    {
        echo '<h3>' . esc_html(__('NCI Core and NCI Premium', 'niche-clever-interactive')) . '</h3>';
        echo '<p>' . esc_html(__('NCI Core is complete for one focused question-and-answer panel. The separate NCI Premium information page records a companion scope for sites that may need several knowledge profiles, curated evidence, authored questions, deeper conversation governance and answer reuse. The table records intended scope only; it is not a purchase, download, pricing or availability statement.', 'niche-clever-interactive')) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_attr(self::PREMIUM_URL) . '" target="_blank" rel="noopener">' . esc_html(__('Learn about NCI Premium', 'niche-clever-interactive')) . '</a></p>';
        $this->renderComparisonTable();
        echo '<p class="ncli-admin__comparison-note">' . esc_html(__('NCI Premium is a separate informational companion; its status and delivery are not represented here. NCI Core contains no Premium code, and nothing in Core is locked, limited or time-restricted. Core never publishes provider citations or source links; the recorded companion scope would use titles and addresses that an owner approves.', 'niche-clever-interactive')) . '</p>';
    }

    private function renderComparisonTable(): void
    {
        $groups = [
            __('Included in NCI Core', 'niche-clever-interactive') => [
                [__('One knowledge profile and public question panel', 'niche-clever-interactive'), __('Answers visitors\' questions from your own OpenAI File Search vector store, on as many pages as you like.', 'niche-clever-interactive'), true, true],
                [__('Guided setup and connection test', 'niche-clever-interactive'), __('A step-by-step checklist, with a test that must pass again after any change that affects answers.', 'niche-clever-interactive'), true, true],
                [__('Model, reasoning and cost controls', 'niche-clever-interactive'), __('Choose the model, reasoning effort, retrieval depth, answer length and timeout, with no prices shown.', 'niche-clever-interactive'), true, true],
                [__('Response guidance and presets', 'niche-clever-interactive'), __('Your own bounded guidance plus Concise, Balanced or Detailed answers, inside fixed safety rules.', 'niche-clever-interactive'), true, true],
                [__('Conversation thread', 'niche-clever-interactive'), __('Questions and answers build up as a thread with a Reset conversation control; nothing is kept in the browser.', 'niche-clever-interactive'), true, true],
                [__('Safe answer formatting', 'niche-clever-interactive'), __('Headings, lists, emphasis, quotes and code display cleanly; provider text is never run as markup and no provider links are published.', 'niche-clever-interactive'), true, true],
                [__('Panel styles, width and accent colour', 'niche-clever-interactive'), __('Three built-in styles, three widths and your own accent colour.', 'niche-clever-interactive'), true, true],
                [__('Editable panel wording', 'niche-clever-interactive'), __('Title, headline, introduction, placeholder, footer labels and unavailable message.', 'niche-clever-interactive'), true, true],
                [__('Daily request limits', 'niche-clever-interactive'), __('A per-visitor and a site-wide daily allowance, with no stored addresses and no extra setup.', 'niche-clever-interactive'), true, true],
                [__('Block and shortcode placement', 'niche-clever-interactive'), __('The NC Interactive block or the [ncli_interactive] shortcode, with placement detection.', 'niche-clever-interactive'), true, true],
                [__('Optional conversation history', 'niche-clever-interactive'), __('Off by default; when on, you can view and delete records and set how long they are kept.', 'niche-clever-interactive'), true, true],
                [__('Privacy tools', 'niche-clever-interactive'), __('Suggested privacy-policy wording, WordPress export and erase support, and an opt-in to delete data on uninstall.', 'niche-clever-interactive'), true, true],
                [__('Diagnostics', 'niche-clever-interactive'), __('Readiness, connection-test result and the latest provider problem in plain words.', 'niche-clever-interactive'), true, true],
                [__('Accessible, translation-ready interface', 'niche-clever-interactive'), __('Labelled controls, keyboard operation and translatable text across the panel and the settings screens.', 'niche-clever-interactive'), true, true],
            ],
            __('Recorded NCI Premium scope: knowledge and access', 'niche-clever-interactive') => [
                [__('Multiple knowledge profiles', 'niche-clever-interactive'), __('Separate profiles, each with its own vector store, wording, style and request limits.', 'niche-clever-interactive'), false, true],
                [__('Protected profiles', 'niche-clever-interactive'), __('Limit a profile to visitors who have been granted access, with an editable access-required message.', 'niche-clever-interactive'), false, true],
                [__('Contextual placement and reusable content routes', 'niche-clever-interactive'), __('Place questions in context and route visitors to them from other content.', 'niche-clever-interactive'), false, true],
            ],
            __('Recorded NCI Premium scope: answers and evidence', 'niche-clever-interactive') => [
                [__('Approved public evidence links', 'niche-clever-interactive'), __('Titles and links you have approved, shown under answers; never raw provider citations.', 'niche-clever-interactive'), false, true],
                [__('Chronology records', 'niche-clever-interactive'), __('Verified, dated summaries so answers about history stay within what your records support.', 'niche-clever-interactive'), false, true],
                [__('Earlier turns sent as context for follow-up questions', 'niche-clever-interactive'), __('Lets follow-ups build on the conversation so far, with a setting for how many earlier turns to include; Core answers each question on its own.', 'niche-clever-interactive'), false, true],
                [__('Approved-answer cache', 'niche-clever-interactive'), __('Reuses an approved answer to a repeated first question to save cost and time, and does not use a live allowance when it does.', 'niche-clever-interactive'), false, true],
            ],
            __('Recorded NCI Premium scope: authored questions', 'niche-clever-interactive') => [
                [__('Authored questions and shareable links', 'niche-clever-interactive'), __('A catalogue of reusable questions with stable keys, shortcodes and links to share.', 'niche-clever-interactive'), false, true],
                [__('Ordered question collections', 'niche-clever-interactive'), __('Group authored questions into a sequence and place it where it fits.', 'niche-clever-interactive'), false, true],
            ],
            __('Recorded NCI Premium scope: conversation governance', 'niche-clever-interactive') => [
                [__('Conversation export', 'niche-clever-interactive'), __('Export one or more conversations for review, filtered by profile.', 'niche-clever-interactive'), false, true],
                [__('Governance controls', 'niche-clever-interactive'), __('Per-profile scope, retention schedules and audited bulk erasure on top of Core\'s history.', 'niche-clever-interactive'), false, true],
            ],
            __('Recorded NCI Premium scope: presentation', 'niche-clever-interactive') => [
                [__('Response colour controls', 'niche-clever-interactive'), __('Set the colours of question and answer blocks, not only the accent colour.', 'niche-clever-interactive'), false, true],
            ],
        ];

        echo '<table class="widefat ncli-admin__comparison-table"><caption class="screen-reader-text">' . esc_html(__('NCI Core compared with NCI Premium', 'niche-clever-interactive')) . '</caption>';
        echo '<thead><tr><th scope="col">' . esc_html(__('Feature', 'niche-clever-interactive')) . '</th><th scope="col">' . esc_html(__('What it does', 'niche-clever-interactive')) . '</th><th scope="col">' . esc_html(__('NCI Core', 'niche-clever-interactive')) . '</th><th scope="col">' . esc_html(__('NCI Premium', 'niche-clever-interactive')) . '</th></tr></thead><tbody>';
        foreach ($groups as $group => $rows) {
            echo '<tr class="ncli-admin__comparison-group"><th scope="rowgroup" colspan="4">' . esc_html($group) . '</th></tr>';
            foreach ($rows as $row) {
                echo '<tr><th scope="row">' . esc_html($row[0]) . '</th><td class="ncli-admin__comparison-what">' . esc_html($row[1]) . '</td>';
                foreach ([$row[2], $row[3]] as $included) {
                    echo '<td class="' . ($included ? 'ncli-admin__comparison-yes' : 'ncli-admin__comparison-no') . '"><span aria-hidden="true">' . ($included ? '&#10003;' : '&#10005;') . '</span><span class="screen-reader-text">' . esc_html($included ? __('Included', 'niche-clever-interactive') : __('Not included', 'niche-clever-interactive')) . '</span></td>';
                }
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
    }
}
