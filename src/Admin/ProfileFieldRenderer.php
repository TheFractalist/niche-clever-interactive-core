<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Admin;

use NicheCleverInteractive\Core\Configuration\ModelCatalogue;
use NicheCleverInteractive\Core\Configuration\PanelAppearance;

/**
 * The field rows of a profile's settings tabs (Setup, Profile, AI & Corpus, Conversation,
 * Appearance & Limits). It draws only the table rows from a settings array in the shape of
 * CoreSettings::toArray(); SettingsPage owns the form, nonce, saving and everything around it.
 */
final class ProfileFieldRenderer
{
    /**
     * @param array<string, mixed> $values
     * @param string $conversationsUrl Address of the conversation history screen.
     * @param bool $hubMode True while the profile hub is active: the site-wide rows (daily provider allowance, delete on uninstall) are on the Settings page instead.
     */
    public function rows(string $tab, array $values, string $conversationsUrl, bool $hubMode = false): void
    {
        $accent = '';
        $style = '';
        if ($tab === 'setup' || $tab === 'profile') {
            if ($tab === 'setup') {
                $this->select('enabled', __('Visitor availability', 'niche-clever-interactive'), ['0' => __('Unavailable', 'niche-clever-interactive'), '1' => __('Available after a current connection test', 'niche-clever-interactive')], (bool) $values['enabled'] ? '1' : '0', __('Public answers remain unavailable until this setting is enabled and the current configuration has passed a connection test.', 'niche-clever-interactive'));
            } else {
                echo '<input type="hidden" name="enabled" value="0">';
                $this->checkbox('enabled', __('Enable the Core profile', 'niche-clever-interactive'), (bool) $values['enabled'], __('Enable this only when the saved configuration is ready for public visitors. A current connection test is also required.', 'niche-clever-interactive'));
            }
        }
        if ($tab === 'profile') {
            $this->text('corpus_version', __('Corpus version', 'niche-clever-interactive'), (string) $values['profile']['corpus_version'], __('Use your own short version or date label for the prepared material in the connected vector store. Updating it makes the existing connection test stale.', 'niche-clever-interactive'));
        } elseif ($tab === 'setup') {
            $this->descriptionRow(__('The corpus version is set on the Profile tab.', 'niche-clever-interactive'));
        }

        if ($tab === 'profile') {
            $this->text('presentation_title', __('Panel title', 'niche-clever-interactive'), (string) $values['presentation']['title'], __('The visible title above the public question panel. Keep it short and identify what visitors can ask about.', 'niche-clever-interactive'));
            $this->text('presentation_headline', __('Panel headline', 'niche-clever-interactive'), (string) $values['presentation']['headline'], __('A short supporting line under the title. It should set expectations without promising that every question can be answered.', 'niche-clever-interactive'));
            $this->textarea('presentation_introduction', __('Panel introduction', 'niche-clever-interactive'), (string) $values['presentation']['introduction'], __('Optional introductory copy shown before the question box. Do not include confidential information, credentials or unpublished source material.', 'niche-clever-interactive'));
            $this->text('presentation_unavailable_message', __('Unavailable message', 'niche-clever-interactive'), (string) $values['presentation']['unavailable_message'], __('Shown to visitors in place of the question box while the service is not ready, for example before a passing connection test. Plain text, up to 240 characters. Leave blank to use the default wording.', 'niche-clever-interactive'));
        }

        if ($tab === 'ai-corpus') {
            $this->text('vector_store_reference', __('Vector store reference', 'niche-clever-interactive'), (string) $values['profile']['vector_store_reference'], __('Enter the identifier for an existing OpenAI File Search vector store that you control. NCI does not create, upload to or manage the store. Changing it requires a new connection test.', 'niche-clever-interactive'));
            $this->descriptionRow(__('NCI answers directly from the configured File Search corpus. On its own it does not publish source links or citation metadata.', 'niche-clever-interactive'));
            $this->renderModelFields($values['model']);
        }

        if ($tab === 'conversation') {
            $this->textarea('instructions', __('Owner response guidance', 'niche-clever-interactive'), (string) $values['instructions'], __('Use this for tone, format and level of detail. It cannot override NCI retrieval, safety, privacy or disclosure rules. Editing it requires a new successful connection test.', 'niche-clever-interactive'));
            $this->descriptionRow(__('Applies to live answers after saving and passing a fresh connection test. It can shape response style and format only; retrieval, safety and disclosure rules always apply.', 'niche-clever-interactive'));
            $this->select('response_preset', __('Response preset', 'niche-clever-interactive'), ['concise' => __('Concise', 'niche-clever-interactive'), 'balanced' => __('Balanced', 'niche-clever-interactive'), 'detailed' => __('Detailed', 'niche-clever-interactive')], (string) $values['response_preset'], __('Choose the default answer length. Owner response guidance can refine presentation but cannot remove Core safeguards. Changing this setting requires a new connection test.', 'niche-clever-interactive'));
            $this->text('presentation_placeholder', __('Question placeholder', 'niche-clever-interactive'), (string) $values['presentation']['placeholder'], __('Hint text displayed inside the visitor question field. It should invite suitable questions without exposing internal instructions.', 'niche-clever-interactive'));
            $this->text('presentation_processing_message', __('Processing message', 'niche-clever-interactive'), (string) $values['presentation']['processing_message'], __('Shown with the animated indicator while NCI prepares an answer. Plain text, up to 160 characters. Leave blank to use the default wording.', 'niche-clever-interactive'));
            $this->checkbox('streaming_progressive', __('Progressively reveal answers', 'niche-clever-interactive'), (bool) $values['streaming']['progressive'], __('When the browser and WordPress HTTP transport support it, answer text appears as it arrives. Otherwise visitors see the processing message until the complete validated answer is ready.', 'niche-clever-interactive'), __('Reveal answer text progressively when supported.', 'niche-clever-interactive'), true);
            $this->text('presentation_footer_left', __('Panel footer left label', 'niche-clever-interactive'), (string) $values['presentation']['footer_left'], __('Optional short label displayed in the left side of the panel footer.', 'niche-clever-interactive'));
            $this->text('presentation_footer_right', __('Panel footer right label', 'niche-clever-interactive'), (string) $values['presentation']['footer_right'], __('Optional short label displayed in the right side of the panel footer.', 'niche-clever-interactive'));
            $this->sectionRow(__('Conversation history', 'niche-clever-interactive'), __('Optionally keep a record of the questions visitors ask and the answers they receive, so you can review and delete them. Nothing is recorded unless you turn this on. No IP address, account or browser identifier is stored with a conversation.', 'niche-clever-interactive'));
            $this->checkbox('conversation_log_enabled', __('Record conversations', 'niche-clever-interactive'), (bool) $values['conversation_log']['enabled'], __('When on, each successfully answered public question and its answer are stored in a table on this site. Refused, failed and administrator test requests are never stored. Tell visitors in your privacy notice that questions and answers are kept.', 'niche-clever-interactive'), __('Keep a record of public questions and answers on this site.', 'niche-clever-interactive'), true);
            $this->select('conversation_retention_days', __('Keep conversations for', 'niche-clever-interactive'), ['30' => __('30 days', 'niche-clever-interactive'), '90' => __('90 days', 'niche-clever-interactive'), '365' => __('1 year', 'niche-clever-interactive'), '0' => __('Until I delete them', 'niche-clever-interactive')], (string) $values['conversation_log']['retention_days'], __('Older conversations are removed automatically once a day. Choose the shortest period that meets your needs. The setting also applies to conversations already stored if you later switch recording off.', 'niche-clever-interactive'));
            if (!$hubMode) {
                $this->checkbox('delete_on_uninstall', __('Delete data on uninstall', 'niche-clever-interactive'), (bool) $values['lifecycle']['delete_on_uninstall'], __('By default, deleting the plugin leaves your settings, request counters and conversations in the database so a reinstall can pick them up. Turn this on to remove all of them when the plugin is deleted from the Plugins screen.', 'niche-clever-interactive'), __('Delete all Niche Clever Interactive settings, counters and conversations when the plugin is deleted.', 'niche-clever-interactive'), true);
            }
            echo '<tr class="ncli-admin__field"><th scope="row"><span>' . esc_html(__('Conversation history', 'niche-clever-interactive')) . '</span></th><td><a class="button button-secondary" href="' . esc_attr($conversationsUrl) . '">' . esc_html(__('View conversation history', 'niche-clever-interactive')) . '</a></td></tr>';
        }

        if ($tab === 'appearance-limits') {
            $this->select('panel_style', __('Panel style', 'niche-clever-interactive'), ['classic' => __('Classic', 'niche-clever-interactive'), 'minimal' => __('Minimal', 'niche-clever-interactive'), 'dark' => __('Dark', 'niche-clever-interactive')], (string) $values['appearance']['panel_style'], __('Choose one of the three built-in panel treatments. All styles keep the same Core question-and-answer behaviour.', 'niche-clever-interactive'));
            $this->select('panel_width', __('Panel width', 'niche-clever-interactive'), ['narrow' => __('Narrow', 'niche-clever-interactive'), 'standard' => __('Standard', 'niche-clever-interactive'), 'wide' => __('Wide', 'niche-clever-interactive')], (string) $values['appearance']['panel_width'], __('The maximum width of the public panel. It always shrinks to fit narrower screens.', 'niche-clever-interactive'));
            $style = (string) $values['appearance']['panel_style'];
            $accent = (string) $values['appearance']['accent_color'];
            $this->accentRow($style, $accent);
            $this->checkbox('show_credit', __('Credit line', 'niche-clever-interactive'), (bool) $values['appearance']['show_credit'], __('Adds a small "Powered by NC Interactive" link under the public panel. It is off by default and appears only if you turn it on.', 'niche-clever-interactive'), __('Show "Powered by NC Interactive" under the panel.', 'niche-clever-interactive'), true);
            $this->number('visitor_requests_per_day', __('Visitor requests per day', 'niche-clever-interactive'), (int) $values['limits']['visitor_requests_per_day'], __('Maximum successful or attempted public questions for one privacy-bounded visitor scope in a UTC day. Use this to protect availability and API spend.', 'niche-clever-interactive'));
            if (!$hubMode) {
                $this->number('provider_requests_per_day', __('Provider requests per day', 'niche-clever-interactive'), (int) $values['limits']['provider_requests_per_day'], __('Maximum provider requests across the whole site in a UTC day, including administrator connection tests. Keep this within your OpenAI budget.', 'niche-clever-interactive'));
            }
        }

    }

    /**
     * The settings that belong to the whole site rather than to one profile.
     *
     * @param array<string, mixed> $values In the shape of CoreSettings::toArray().
     */
    public function siteRows(array $values): void
    {
        $this->number('provider_requests_per_day', __('Provider requests per day', 'niche-clever-interactive'), (int) $values['limits']['provider_requests_per_day'], __('Maximum provider requests across the whole site in a UTC day, including administrator connection tests. Keep this within your OpenAI budget.', 'niche-clever-interactive'));
        $this->checkbox('delete_on_uninstall', __('Delete data on uninstall', 'niche-clever-interactive'), (bool) $values['lifecycle']['delete_on_uninstall'], __('By default, deleting the plugin leaves your settings, request counters and conversations in the database so a reinstall can pick them up. Turn this on to remove all of them when the plugin is deleted from the Plugins screen.', 'niche-clever-interactive'), __('Delete all Niche Clever Interactive settings, counters and conversations when the plugin is deleted.', 'niche-clever-interactive'), true);
    }

    /**
     * The same rows for an extension profile. $values holds the profile's effective settings:
     * label, enabled, reference_saved, corpus_version, instructions, response_preset, presentation,
     * panel_style, visitor_requests_per_day, model (complete block) and appearance (panel_width,
     * accent_color, show_credit). Site-wide settings (the provider daily cap, delete on uninstall)
     * and the conversation log belong to Core's profile and are not drawn here.
     *
     * @param array<string, mixed> $values
     */
    public function profileRows(string $tab, array $values, bool $creating = false): void
    {
        if ($tab === 'setup' || $tab === 'profile') {
            if ($tab === 'setup') {
                $this->select('enabled', __('Visitor availability', 'niche-clever-interactive'), ['0' => __('Unavailable', 'niche-clever-interactive'), '1' => __('Available after a current connection test', 'niche-clever-interactive')], $values['enabled'] ? '1' : '0', __('Public answers remain unavailable until this is enabled and the profile has a current passing connection test.', 'niche-clever-interactive'));
            } else {
                echo '<input type="hidden" name="enabled" value="0">';
                $this->checkbox('enabled', __('Enable this profile', 'niche-clever-interactive'), (bool) $values['enabled'], __('Enable this only when the saved configuration is ready for public visitors. A current connection test is also required.', 'niche-clever-interactive'), __('Enable public answers for this profile.', 'niche-clever-interactive'));
                $this->text('label', __('Profile name', 'niche-clever-interactive'), (string) $values['label'], __('A short name for this profile, shown only to you in the Profiles list and the profile switcher.', 'niche-clever-interactive'));
            }
        }
        if ($tab === 'profile' || $creating) {
            $this->text('corpus_version', __('Corpus version', 'niche-clever-interactive'), (string) $values['corpus_version'], __('Use your own short version or date label for the prepared material in this profile\'s vector store. Updating it makes the existing connection test stale.', 'niche-clever-interactive'));
        } elseif ($tab === 'setup') {
            $this->descriptionRow(__('The corpus version is set on the Profile tab.', 'niche-clever-interactive'));
        }

        if ($tab === 'profile') {
            $presentation = $values['presentation'];
            $this->text('presentation_title', __('Panel title', 'niche-clever-interactive'), (string) $presentation['title'], __('The visible title above the public question panel. Leave it blank to use the default profile\'s wording.', 'niche-clever-interactive'));
            $this->text('presentation_headline', __('Panel headline', 'niche-clever-interactive'), (string) $presentation['headline'], __('A short supporting line under the title. Leave it blank to use the default profile\'s wording.', 'niche-clever-interactive'));
            $this->textarea('presentation_introduction', __('Panel introduction', 'niche-clever-interactive'), (string) $presentation['introduction'], __('Optional introductory copy shown before the question box. Do not include confidential information, credentials or unpublished sources.', 'niche-clever-interactive'));
            $this->text('presentation_unavailable_message', __('Unavailable message', 'niche-clever-interactive'), (string) $presentation['unavailable_message'], __('Shown to visitors in place of the question box while this profile is not ready. Leave it blank to use the default profile\'s message.', 'niche-clever-interactive'));
        }

        if ($tab === 'ai-corpus') {
            $saved = (bool) $values['reference_saved'];
            echo wp_kses('<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel('vector_store_reference', __('Vector store reference', 'niche-clever-interactive'), __('Enter the identifier for an existing OpenAI File Search vector store that you control. It is write-only: once saved it is never shown again, and leaving the field blank keeps the saved value. Changing it requires a new connection test.', 'niche-clever-interactive')) . '</th><td>'
                . '<input id="ncli-vector_store_reference" class="regular-text" type="text" name="vector_store_reference" value="" maxlength="256" autocomplete="off"' . ($saved ? ' placeholder="' . esc_attr(__('Saved. Leave blank to keep it.', 'niche-clever-interactive')) . '"' : '') . '></td></tr>', ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
            $this->renderModelFields($values['model']);
        }

        if ($tab === 'conversation') {
            $presentation = $values['presentation'];
            $this->textarea('instructions', __('Owner response guidance', 'niche-clever-interactive'), (string) $values['instructions'], __('Use this for tone, format and level of detail. It cannot override NCI retrieval, safety, privacy or disclosure rules. Editing it requires a new successful connection test.', 'niche-clever-interactive'));
            $this->select('response_preset', __('Response preset', 'niche-clever-interactive'), ['concise' => __('Concise', 'niche-clever-interactive'), 'balanced' => __('Balanced', 'niche-clever-interactive'), 'detailed' => __('Detailed', 'niche-clever-interactive')], (string) $values['response_preset'], __('Choose the default answer length. Owner response guidance can refine presentation but cannot override safety, retrieval or disclosure rules.', 'niche-clever-interactive'));
            $this->text('presentation_placeholder', __('Question placeholder', 'niche-clever-interactive'), (string) $presentation['placeholder'], __('Hint text displayed inside the visitor question field. Leave it blank to use the default profile\'s.', 'niche-clever-interactive'));
            $this->text('presentation_processing_message', __('Processing message', 'niche-clever-interactive'), (string) $presentation['processing_message'], __('Shown with the animated indicator while NCI prepares an answer. Leave blank to use the default profile\'s wording.', 'niche-clever-interactive'));
            $this->text('presentation_footer_left', __('Panel footer left label', 'niche-clever-interactive'), (string) $presentation['footer_left'], __('Optional short label displayed in the left side of the panel footer.', 'niche-clever-interactive'));
            $this->text('presentation_footer_right', __('Panel footer right label', 'niche-clever-interactive'), (string) $presentation['footer_right'], __('Optional short label displayed in the right side of the panel footer.', 'niche-clever-interactive'));
        }

        if ($tab === 'appearance-limits') {
            $appearance = $values['appearance'];
            $style = (string) $values['panel_style'];
            $this->select('panel_style', __('Panel style', 'niche-clever-interactive'), ['classic' => __('Classic', 'niche-clever-interactive'), 'minimal' => __('Minimal', 'niche-clever-interactive'), 'dark' => __('Dark', 'niche-clever-interactive')], $style, __('Choose one of the three built-in panel treatments.', 'niche-clever-interactive'));
            $this->select('panel_width', __('Panel width', 'niche-clever-interactive'), ['narrow' => __('Narrow', 'niche-clever-interactive'), 'standard' => __('Standard', 'niche-clever-interactive'), 'wide' => __('Wide', 'niche-clever-interactive')], (string) $appearance['panel_width'], __('The maximum width of this profile\'s public panel. It always shrinks to fit narrower screens.', 'niche-clever-interactive'));
            $this->accentRow($style, (string) $appearance['accent_color']);
            $this->checkbox('show_credit', __('Credit line', 'niche-clever-interactive'), (bool) $appearance['show_credit'], __('Adds a small "Powered by NC Interactive" link under this profile\'s public panel. It is off by default.', 'niche-clever-interactive'), __('Show "Powered by NC Interactive" under the panel.', 'niche-clever-interactive'), true);
            $this->number('visitor_requests_per_day', __('Visitor requests per day', 'niche-clever-interactive'), (int) $values['visitor_requests_per_day'], __('Maximum successful or attempted public questions for one privacy-bounded visitor scope in a UTC day, for this profile. The site-wide provider allowance is set on the default profile.', 'niche-clever-interactive'), 1, 10000);
        }
    }

    /**
     * @param array{id: string, reasoning_effort: string, retrieval_depth: int, max_output_tokens: int, timeout_seconds: int} $model
     */
    private function renderModelFields(array $model): void
    {
        $modelOptions = '';
        foreach (ModelCatalogue::all() as $entry) {
            $modelOptions .= '<option value="' . esc_attr($entry['id']) . '" data-efforts="' . esc_attr(implode(',', $entry['efforts'])) . '"' . ($model['id'] === $entry['id'] ? ' selected' : '') . '>' . esc_html(ModelCatalogue::describe($entry['id'])['tier'] . ' – ' . $entry['label'] . ' (' . $entry['id'] . ')') . '</option>';
        }
        $helpHtml = '<span class="ncli-admin__model-help-intro">' . esc_html(__('Choose the OpenAI model that writes answers. Higher tiers usually cost more per answer. Changing this requires a new connection test.', 'niche-clever-interactive')) . '</span>' . $this->renderModelTable();
        echo wp_kses('<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel('model_id', __('Model', 'niche-clever-interactive'), '', true, $helpHtml) . '</th><td><select id="ncli-model_id" name="model_id">' . $modelOptions . '</select></td></tr>', ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());

        $efforts = [
            'default' => __('Provider default (not sent)', 'niche-clever-interactive'),
            'none' => __('None', 'niche-clever-interactive'),
            'low' => __('Low', 'niche-clever-interactive'),
            'medium' => __('Medium', 'niche-clever-interactive'),
            'high' => __('High', 'niche-clever-interactive'),
            'xhigh' => __('Extra high', 'niche-clever-interactive'),
            'max' => __('Max', 'niche-clever-interactive'),
        ];
        $this->select('model_reasoning_effort', __('Reasoning effort', 'niche-clever-interactive'), $efforts, $model['reasoning_effort'], __('How much thinking the model does before answering. Higher effort can improve difficult answers but uses more output tokens, costs more and takes longer. Only values valid for the chosen model are accepted, and models without reasoning control ignore it. Changing this requires a new connection test.', 'niche-clever-interactive'));

        $depths = [];
        foreach (ModelCatalogue::RETRIEVAL_DEPTHS as $depth) {
            $depths[(string) $depth] = (string) $depth;
        }
        $this->select('model_retrieval_depth', __('Retrieval depth', 'niche-clever-interactive'), $depths, (string) $model['retrieval_depth'], __('How many passages are retrieved from the vector store for each question. More passages can help broad questions but increase the amount of text the model reads, so cost and response time rise. Changing this requires a new connection test.', 'niche-clever-interactive'));

        $this->number('model_max_output_tokens', __('Maximum answer length (tokens)', 'niche-clever-interactive'), $model['max_output_tokens'], __('The most output tokens one answer may use, including any reasoning. Too low a limit can cut answers short, or leave a reasoning model with no visible answer. Changing this requires a new connection test.', 'niche-clever-interactive'), ModelCatalogue::MIN_OUTPUT_TOKENS, ModelCatalogue::MAX_OUTPUT_TOKENS);

        $timeouts = [];
        foreach (ModelCatalogue::TIMEOUT_SECONDS as $seconds) {
            /* translators: %d: request timeout in seconds. */
            $timeouts[(string) $seconds] = sprintf(__('%d seconds', 'niche-clever-interactive'), $seconds);
        }
        $this->select('model_timeout_seconds', __('Request timeout', 'niche-clever-interactive'), $timeouts, (string) $model['timeout_seconds'], __('How long Core waits for OpenAI before giving up. Higher reasoning effort may need longer. Your hosting limits may stop a request earlier. Core does not retry automatically. Changing this requires a new connection test.', 'niche-clever-interactive'));
        $this->descriptionRow(__('See the Help Centre for guidance on how these choices affect cost. Set a usage limit in your OpenAI dashboard as well; Core\'s request limits do not replace it.', 'niche-clever-interactive'));
    }

    public function renderModelTable(): string
    {
        $html = '<span class="ncli-admin__model-table-wrap"><table class="widefat striped ncli-admin__model-table"><thead><tr>'
            . '<th scope="col">' . esc_html(__('Model', 'niche-clever-interactive')) . '</th>'
            . '<th scope="col">' . esc_html(__('Tier', 'niche-clever-interactive')) . '</th>'
            . '<th scope="col">' . esc_html(__('Best for', 'niche-clever-interactive')) . '</th>'
            . '<th scope="col">' . esc_html(__('Relative cost', 'niche-clever-interactive')) . '</th>'
            . '<th scope="col">' . esc_html(__('Reasoning effort', 'niche-clever-interactive')) . '</th>'
            . '</tr></thead><tbody>';
        foreach (ModelCatalogue::all() as $entry) {
            $efforts = $entry['efforts'] === [] ? __('Not offered', 'niche-clever-interactive') : implode(', ', $entry['efforts']);
            $guidance = ModelCatalogue::describe($entry['id']);
            $html .= '<tr><th scope="row"><code>' . esc_html($entry['id']) . '</code></th>'
                . '<td>' . esc_html($guidance['tier']) . '</td>'
                . '<td>' . esc_html($guidance['best_for']) . '</td>'
                . '<td>' . esc_html($guidance['cost_note']) . '</td>'
                . '<td>' . esc_html($efforts) . '</td></tr>';
        }

        return $html . '</tbody></table></span>';
    }

    private function checkbox(string $name, string $label, bool $checked, string $help, ?string $boxLabel = null, bool $postUnchecked = false): void
    {
        $boxLabel = $boxLabel ?? __('Enable public answers for the default Core profile.', 'niche-clever-interactive');
        // An unchecked box posts nothing; the hidden zero lets an owner switch an option off.
        $hidden = $postUnchecked ? '<input type="hidden" name="' . esc_attr($name) . '" value="0">' : '';
        echo wp_kses('<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel($name, $label, $help, false) . '</th><td>' . $hidden . '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . ($checked ? ' checked' : '') . '> ' . esc_html($boxLabel) . '</label></td></tr>', ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
    }

    private function sectionRow(string $title, string $description): void
    {
        echo '<tr class="ncli-admin__section-row"><td colspan="2"><h3>' . esc_html($title) . '</h3><p class="description">' . esc_html($description) . '</p></td></tr>';
    }

    private function accentRow(string $style, string $accent): void
    {
        $picker = $accent !== '' ? $accent : PanelAppearance::defaultAccent($style);
        $help = __('Sets the colour of the question button and the keyboard focus outline, in every panel style and at every width. A very pale or very dark colour is still used: Core adds an edge to the button, and keeps the style\'s own colour for the focus outline, so both stay visible.', 'niche-clever-interactive');
        echo wp_kses('<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel('accent_color', __('Accent colour', 'niche-clever-interactive'), $help) . '</th><td>'
            . '<input id="ncli-accent_color" class="ncli-admin__colour" type="color" name="accent_color" value="' . esc_attr($picker) . '"> '
            . '<label><input id="ncli-accent_color_default" type="checkbox" name="accent_color_default" value="1"' . ($accent === '' ? ' checked' : '') . '> ' . esc_html(__('Use the panel style\'s own colour', 'niche-clever-interactive')) . '</label>'
            . '</td></tr>', ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
    }

    private function text(string $name, string $label, string $value, string $help): void
    {
        echo wp_kses('<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel($name, $label, $help) . '</th><td><input id="ncli-' . esc_attr($name) . '" class="regular-text" type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"></td></tr>', ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
    }

    private function textarea(string $name, string $label, string $value, string $help): void
    {
        echo wp_kses('<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel($name, $label, $help) . '</th><td><textarea id="ncli-' . esc_attr($name) . '" class="large-text" rows="8" name="' . esc_attr($name) . '">' . esc_html($value) . '</textarea></td></tr>', ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
    }

    /**
     * @param array<string, string> $options
     */
    private function select(string $name, string $label, array $options, string $selected, string $help): void
    {
        echo wp_kses('<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel($name, $label, $help) . '</th><td><select id="ncli-' . esc_attr($name) . '" name="' . esc_attr($name) . '">', ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
        foreach ($options as $value => $text) {
            $optionValue = (string) $value;
            echo '<option value="' . esc_attr($optionValue) . '"' . ($selected === $optionValue ? ' selected' : '') . '>' . esc_html($text) . '</option>';
        }
        echo '</select></td></tr>';
    }

    private function number(string $name, string $label, int $value, string $help, int $min = 1, ?int $max = null): void
    {
        echo wp_kses('<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel($name, $label, $help) . '</th><td><input id="ncli-' . esc_attr($name) . '" type="number" min="' . esc_attr((string) $min) . '"' . ($max !== null ? ' max="' . esc_attr((string) $max) . '"' : '') . ' name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '"></td></tr>', ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
    }

    private function descriptionRow(string $message): void
    {
        echo '<tr class="ncli-admin__field ncli-admin__field--description"><th scope="row"></th><td><p class="description">' . esc_html($message) . '</p></td></tr>';
    }

    /**
     * @param string|null $helpHtml Pre-escaped markup that replaces the plain-text help.
     */
    private function fieldLabel(string $name, string $label, string $help, bool $forControl = true, ?string $helpHtml = null): string
    {
        $id = 'ncli-help-' . $name;
        $labelMarkup = $forControl
            ? '<label for="ncli-' . esc_attr($name) . '">' . esc_html($label) . '</label>'
            : '<span>' . esc_html($label) . '</span>';

        return '<span class="ncli-admin__field-label">' . $labelMarkup
            . '<button type="button" class="ncli-admin__help-toggle" aria-controls="' . esc_attr($id) . '" aria-expanded="false" aria-label="' . esc_attr(__('Help for ', 'niche-clever-interactive') . $label) . '"><span aria-hidden="true">?</span><span class="screen-reader-text">' . esc_html(__('Help for ', 'niche-clever-interactive') . $label) . '</span></button>'
            . '<span id="' . esc_attr($id) . '" class="ncli-admin__field-help' . ($helpHtml !== null ? ' ncli-admin__field-help--wide' : '') . '" role="note" hidden>' . ($helpHtml ?? esc_html($help)) . '</span></span>';
    }
}
