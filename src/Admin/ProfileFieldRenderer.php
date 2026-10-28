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
                $this->select('enabled', 'Visitor availability', ['0' => 'Unavailable', '1' => 'Available after a current connection test'], (bool) $values['enabled'] ? '1' : '0', 'Public answers remain unavailable until this setting is enabled and the current configuration has passed a connection test.');
            } else {
                echo '<input type="hidden" name="enabled" value="0">';
                $this->checkbox('enabled', 'Enable the Core profile', (bool) $values['enabled'], 'Enable this only when the saved configuration is ready for public visitors. A current connection test is also required.');
            }
        }
        if ($tab === 'profile') {
            $this->text('corpus_version', 'Corpus version', (string) $values['profile']['corpus_version'], 'Use your own short version or date label for the prepared material in the connected vector store. Updating it makes the existing connection test stale.');
        } elseif ($tab === 'setup') {
            $this->descriptionRow('The corpus version is set on the Profile tab.');
        }

        if ($tab === 'profile') {
            $this->text('presentation_title', 'Panel title', (string) $values['presentation']['title'], 'The visible title above the public question panel. Keep it short and identify what visitors can ask about.');
            $this->text('presentation_headline', 'Panel headline', (string) $values['presentation']['headline'], 'A short supporting line under the title. It should set expectations without promising that every question can be answered.');
            $this->textarea('presentation_introduction', 'Panel introduction', (string) $values['presentation']['introduction'], 'Optional introductory copy shown before the question box. Do not include confidential information, credentials or unpublished source material.');
            $this->text('presentation_unavailable_message', 'Unavailable message', (string) $values['presentation']['unavailable_message'], 'Shown to visitors in place of the question box while the service is not ready, for example before a passing connection test. Plain text, up to 240 characters. Leave blank to use the default wording.');
        }

        if ($tab === 'ai-corpus') {
            $this->text('vector_store_reference', 'Vector store reference', (string) $values['profile']['vector_store_reference'], 'Enter the identifier for an existing OpenAI File Search vector store that you control. NCI does not create, upload to or manage the store. Changing it requires a new connection test.');
            $this->descriptionRow('NCI answers directly from the configured File Search corpus. On its own it does not publish source links or citation metadata.');
            $this->renderModelFields($values['model']);
        }

        if ($tab === 'conversation') {
            $this->textarea('instructions', 'Owner response guidance', (string) $values['instructions'], 'Use this for tone, format and level of detail. It cannot override NCI retrieval, safety, privacy or disclosure rules. Editing it requires a new successful connection test.');
            $this->descriptionRow('Applies to live answers after saving and passing a fresh connection test. It can shape response style and format only; retrieval, safety and disclosure rules always apply.');
            $this->select('response_preset', 'Response preset', ['concise' => 'Concise', 'balanced' => 'Balanced', 'detailed' => 'Detailed'], (string) $values['response_preset'], 'Choose the default answer length. Owner response guidance can refine presentation but cannot remove Core safeguards. Changing this setting requires a new connection test.');
            $this->text('presentation_placeholder', 'Question placeholder', (string) $values['presentation']['placeholder'], 'Hint text displayed inside the visitor question field. It should invite suitable questions without exposing internal instructions.');
            $this->text('presentation_processing_message', __('Processing message', 'niche-clever-interactive'), (string) $values['presentation']['processing_message'], __('Shown with the animated indicator while NCI prepares an answer. Plain text, up to 160 characters. Leave blank to use the default wording.', 'niche-clever-interactive'));
            $this->checkbox('streaming_progressive', __('Progressively reveal answers', 'niche-clever-interactive'), (bool) $values['streaming']['progressive'], __('When the browser and WordPress HTTP transport support it, answer text appears as it arrives. Otherwise visitors see the processing message until the complete validated answer is ready.', 'niche-clever-interactive'), __('Reveal answer text progressively when supported.', 'niche-clever-interactive'), true);
            $this->text('presentation_footer_left', 'Panel footer left label', (string) $values['presentation']['footer_left'], 'Optional short label displayed in the left side of the panel footer.');
            $this->text('presentation_footer_right', 'Panel footer right label', (string) $values['presentation']['footer_right'], 'Optional short label displayed in the right side of the panel footer.');
            $this->sectionRow('Conversation history', 'Optionally keep a record of the questions visitors ask and the answers they receive, so you can review and delete them. Nothing is recorded unless you turn this on. No IP address, account or browser identifier is stored with a conversation.');
            $this->checkbox('conversation_log_enabled', 'Record conversations', (bool) $values['conversation_log']['enabled'], 'When on, each successfully answered public question and its answer are stored in a table on this site. Refused, failed and administrator test requests are never stored. Tell visitors in your privacy notice that questions and answers are kept.', 'Keep a record of public questions and answers on this site.', true);
            $this->select('conversation_retention_days', 'Keep conversations for', ['30' => '30 days', '90' => '90 days', '365' => '1 year', '0' => 'Until I delete them'], (string) $values['conversation_log']['retention_days'], 'Older conversations are removed automatically once a day. Choose the shortest period that meets your needs. The setting also applies to conversations already stored if you later switch recording off.');
            if (!$hubMode) {
                $this->checkbox('delete_on_uninstall', 'Delete data on uninstall', (bool) $values['lifecycle']['delete_on_uninstall'], 'By default, deleting the plugin leaves your settings, request counters and conversations in the database so a reinstall can pick them up. Turn this on to remove all of them when the plugin is deleted from the Plugins screen.', 'Delete all Niche Clever Interactive settings, counters and conversations when the plugin is deleted.', true);
            }
            echo '<tr class="ncli-admin__field"><th scope="row"><span>' . esc_html(__('Conversation history', 'niche-clever-interactive')) . '</span></th><td><a class="button button-secondary" href="' . esc_attr($conversationsUrl) . '">' . esc_html(__('View conversation history', 'niche-clever-interactive')) . '</a></td></tr>';
        }

        if ($tab === 'appearance-limits') {
            $this->select('panel_style', 'Panel style', ['classic' => 'Classic', 'minimal' => 'Minimal', 'dark' => 'Dark'], (string) $values['appearance']['panel_style'], 'Choose one of the three built-in panel treatments. All styles keep the same Core question-and-answer behaviour.');
            $this->select('panel_width', 'Panel width', ['narrow' => 'Narrow', 'standard' => 'Standard', 'wide' => 'Wide'], (string) $values['appearance']['panel_width'], 'The maximum width of the public panel. It always shrinks to fit narrower screens.');
            $style = (string) $values['appearance']['panel_style'];
            $accent = (string) $values['appearance']['accent_color'];
            $this->accentRow($style, $accent);
            $this->checkbox('show_credit', 'Credit line', (bool) $values['appearance']['show_credit'], 'Adds a small "Powered by NC Interactive" link under the public panel. It is off by default and appears only if you turn it on.', 'Show "Powered by NC Interactive" under the panel.', true);
            $this->number('visitor_requests_per_day', 'Visitor requests per day', (int) $values['limits']['visitor_requests_per_day'], 'Maximum successful or attempted public questions for one privacy-bounded visitor scope in a UTC day. Use this to protect availability and API spend.');
            if (!$hubMode) {
                $this->number('provider_requests_per_day', 'Provider requests per day', (int) $values['limits']['provider_requests_per_day'], 'Maximum provider requests across the whole site in a UTC day, including administrator connection tests. Keep this within your OpenAI budget.');
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
        $this->number('provider_requests_per_day', 'Provider requests per day', (int) $values['limits']['provider_requests_per_day'], 'Maximum provider requests across the whole site in a UTC day, including administrator connection tests. Keep this within your OpenAI budget.');
        $this->checkbox('delete_on_uninstall', 'Delete data on uninstall', (bool) $values['lifecycle']['delete_on_uninstall'], 'By default, deleting the plugin leaves your settings, request counters and conversations in the database so a reinstall can pick them up. Turn this on to remove all of them when the plugin is deleted from the Plugins screen.', 'Delete all Niche Clever Interactive settings, counters and conversations when the plugin is deleted.', true);
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
                $this->select('enabled', 'Visitor availability', ['0' => 'Unavailable', '1' => 'Available after a current connection test'], $values['enabled'] ? '1' : '0', 'Public answers remain unavailable until this is enabled and the profile has a current passing connection test.');
            } else {
                echo '<input type="hidden" name="enabled" value="0">';
                $this->checkbox('enabled', 'Enable this profile', (bool) $values['enabled'], 'Enable this only when the saved configuration is ready for public visitors. A current connection test is also required.', 'Enable public answers for this profile.');
                $this->text('label', 'Profile name', (string) $values['label'], 'A short name for this profile, shown only to you in the Profiles list and the profile switcher.');
            }
        }
        if ($tab === 'profile' || $creating) {
            $this->text('corpus_version', 'Corpus version', (string) $values['corpus_version'], 'Use your own short version or date label for the prepared material in this profile\'s vector store. Updating it makes the existing connection test stale.');
        } elseif ($tab === 'setup') {
            $this->descriptionRow('The corpus version is set on the Profile tab.');
        }

        if ($tab === 'profile') {
            $presentation = $values['presentation'];
            $this->text('presentation_title', 'Panel title', (string) $presentation['title'], 'The visible title above the public question panel. Leave it blank to use the default profile\'s wording.');
            $this->text('presentation_headline', 'Panel headline', (string) $presentation['headline'], 'A short supporting line under the title. Leave it blank to use the default profile\'s wording.');
            $this->textarea('presentation_introduction', 'Panel introduction', (string) $presentation['introduction'], 'Optional introductory copy shown before the question box. Do not include confidential information, credentials or unpublished sources.');
            $this->text('presentation_unavailable_message', 'Unavailable message', (string) $presentation['unavailable_message'], 'Shown to visitors in place of the question box while this profile is not ready. Leave it blank to use the default profile\'s message.');
        }

        if ($tab === 'ai-corpus') {
            $saved = (bool) $values['reference_saved'];
            echo wp_kses('<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel('vector_store_reference', 'Vector store reference', 'Enter the identifier for an existing OpenAI File Search vector store that you control. It is write-only: once saved it is never shown again, and leaving the field blank keeps the saved value. Changing it requires a new connection test.') . '</th><td>'
                . '<input id="ncli-vector_store_reference" class="regular-text" type="text" name="vector_store_reference" value="" maxlength="256" autocomplete="off"' . ($saved ? ' placeholder="' . esc_attr(__('Saved. Leave blank to keep it.', 'niche-clever-interactive')) . '"' : '') . '></td></tr>', ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
            $this->renderModelFields($values['model']);
        }

        if ($tab === 'conversation') {
            $presentation = $values['presentation'];
            $this->textarea('instructions', 'Owner response guidance', (string) $values['instructions'], 'Use this for tone, format and level of detail. It cannot override NCI retrieval, safety, privacy or disclosure rules. Editing it requires a new successful connection test.');
            $this->select('response_preset', 'Response preset', ['concise' => 'Concise', 'balanced' => 'Balanced', 'detailed' => 'Detailed'], (string) $values['response_preset'], 'Choose the default answer length. Owner response guidance can refine presentation but cannot override safety, retrieval or disclosure rules.');
            $this->text('presentation_placeholder', 'Question placeholder', (string) $presentation['placeholder'], 'Hint text displayed inside the visitor question field. Leave it blank to use the default profile\'s.');
            $this->text('presentation_processing_message', __('Processing message', 'niche-clever-interactive'), (string) $presentation['processing_message'], __('Shown with the animated indicator while NCI prepares an answer. Leave blank to use the default profile\'s wording.', 'niche-clever-interactive'));
            $this->text('presentation_footer_left', 'Panel footer left label', (string) $presentation['footer_left'], 'Optional short label displayed in the left side of the panel footer.');
            $this->text('presentation_footer_right', 'Panel footer right label', (string) $presentation['footer_right'], 'Optional short label displayed in the right side of the panel footer.');
        }

        if ($tab === 'appearance-limits') {
            $appearance = $values['appearance'];
            $style = (string) $values['panel_style'];
            $this->select('panel_style', 'Panel style', ['classic' => 'Classic', 'minimal' => 'Minimal', 'dark' => 'Dark'], $style, 'Choose one of the three built-in panel treatments.');
            $this->select('panel_width', 'Panel width', ['narrow' => 'Narrow', 'standard' => 'Standard', 'wide' => 'Wide'], (string) $appearance['panel_width'], 'The maximum width of this profile\'s public panel. It always shrinks to fit narrower screens.');
            $this->accentRow($style, (string) $appearance['accent_color']);
            $this->checkbox('show_credit', 'Credit line', (bool) $appearance['show_credit'], 'Adds a small "Powered by NC Interactive" link under this profile\'s public panel. It is off by default.', 'Show "Powered by NC Interactive" under the panel.', true);
            $this->number('visitor_requests_per_day', 'Visitor requests per day', (int) $values['visitor_requests_per_day'], 'Maximum successful or attempted public questions for one privacy-bounded visitor scope in a UTC day, for this profile. The site-wide provider allowance is set on the default profile.', 1, 10000);
        }
    }

    /**
     * @param array{id: string, reasoning_effort: string, retrieval_depth: int, max_output_tokens: int, timeout_seconds: int} $model
     */
    private function renderModelFields(array $model): void
    {
        $modelOptions = '';
        foreach (ModelCatalogue::all() as $entry) {
            $modelOptions .= '<option value="' . esc_attr($entry['id']) . '" data-efforts="' . esc_attr(implode(',', $entry['efforts'])) . '"' . ($model['id'] === $entry['id'] ? ' selected' : '') . '>' . esc_html($this->t($entry['tier']) . ' – ' . $entry['label'] . ' (' . $entry['id'] . ')') . '</option>';
        }
        $helpHtml = '<span class="ncli-admin__model-help-intro">' . esc_html(__('Choose the OpenAI model that writes answers. Higher tiers usually cost more per answer. Changing this requires a new connection test.', 'niche-clever-interactive')) . '</span>' . $this->renderModelTable();
        echo wp_kses('<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel('model_id', 'Model', '', true, $helpHtml) . '</th><td><select id="ncli-model_id" name="model_id">' . $modelOptions . '</select></td></tr>', ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());

        $efforts = ['default' => 'Provider default (not sent)'];
        foreach (['none', 'low', 'medium', 'high', 'xhigh', 'max'] as $effort) {
            $efforts[$effort] = ucfirst($effort === 'xhigh' ? 'extra high' : $effort);
        }
        $this->select('model_reasoning_effort', 'Reasoning effort', $efforts, $model['reasoning_effort'], 'How much thinking the model does before answering. Higher effort can improve difficult answers but uses more output tokens, costs more and takes longer. Only values valid for the chosen model are accepted, and models without reasoning control ignore it. Changing this requires a new connection test.');

        $depths = [];
        foreach (ModelCatalogue::RETRIEVAL_DEPTHS as $depth) {
            $depths[(string) $depth] = (string) $depth;
        }
        $this->select('model_retrieval_depth', 'Retrieval depth', $depths, (string) $model['retrieval_depth'], 'How many passages are retrieved from the vector store for each question. More passages can help broad questions but increase the amount of text the model reads, so cost and response time rise. Changing this requires a new connection test.');

        $this->number('model_max_output_tokens', 'Maximum answer length (tokens)', $model['max_output_tokens'], 'The most output tokens one answer may use, including any reasoning. Too low a limit can cut answers short, or leave a reasoning model with no visible answer. Changing this requires a new connection test.', ModelCatalogue::MIN_OUTPUT_TOKENS, ModelCatalogue::MAX_OUTPUT_TOKENS);

        $timeouts = [];
        foreach (ModelCatalogue::TIMEOUT_SECONDS as $seconds) {
            /* translators: %d: request timeout in seconds. */
            $timeouts[(string) $seconds] = sprintf(__('%d seconds', 'niche-clever-interactive'), $seconds);
        }
        $this->select('model_timeout_seconds', 'Request timeout', $timeouts, (string) $model['timeout_seconds'], 'How long Core waits for OpenAI before giving up. Higher reasoning effort may need longer. Your hosting limits may stop a request earlier. Core does not retry automatically. Changing this requires a new connection test.');
        $this->descriptionRow('See the Help Centre for guidance on how these choices affect cost. Set a usage limit in your OpenAI dashboard as well; Core\'s request limits do not replace it.');
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
            $html .= '<tr><th scope="row"><code>' . esc_html($entry['id']) . '</code></th>'
                . '<td>' . esc_html($this->t($entry['tier'])) . '</td>'
                . '<td>' . esc_html($this->t($entry['best_for'])) . '</td>'
                . '<td>' . esc_html($this->t($entry['cost_note'])) . '</td>'
                . '<td>' . esc_html($efforts) . '</td></tr>';
        }

        return $html . '</tbody></table></span>';
    }

    private function checkbox(string $name, string $label, bool $checked, string $help, ?string $boxLabel = null, bool $postUnchecked = false): void
    {
        $boxLabel = $boxLabel ?? 'Enable public answers for the default Core profile.';
        // An unchecked box posts nothing; the hidden zero lets an owner switch an option off.
        $hidden = $postUnchecked ? '<input type="hidden" name="' . esc_attr($name) . '" value="0">' : '';
        echo wp_kses('<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel($name, $label, $help, false) . '</th><td>' . $hidden . '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . ($checked ? ' checked' : '') . '> ' . esc_html($this->t($boxLabel)) . '</label></td></tr>', ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
    }

    private function sectionRow(string $title, string $description): void
    {
        echo '<tr class="ncli-admin__section-row"><td colspan="2"><h3>' . esc_html($this->t($title)) . '</h3><p class="description">' . esc_html($this->t($description)) . '</p></td></tr>';
    }

    private function accentRow(string $style, string $accent): void
    {
        $picker = $accent !== '' ? $accent : PanelAppearance::defaultAccent($style);
        $help = 'Sets the colour of the question button and the keyboard focus outline, in every panel style and at every width. A very pale or very dark colour is still used: Core adds an edge to the button, and keeps the style\'s own colour for the focus outline, so both stay visible.';
        echo wp_kses('<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel('accent_color', 'Accent colour', $help) . '</th><td>'
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
            echo '<option value="' . esc_attr($optionValue) . '"' . ($selected === $optionValue ? ' selected' : '') . '>' . esc_html($this->t($text)) . '</option>';
        }
        echo '</select></td></tr>';
    }

    private function number(string $name, string $label, int $value, string $help, int $min = 1, ?int $max = null): void
    {
        echo wp_kses('<tr class="ncli-admin__field"><th scope="row">' . $this->fieldLabel($name, $label, $help) . '</th><td><input id="ncli-' . esc_attr($name) . '" type="number" min="' . esc_attr((string) $min) . '"' . ($max !== null ? ' max="' . esc_attr((string) $max) . '"' : '') . ' name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '"></td></tr>', ExtensionMarkupPolicy::allowedHtml(), ExtensionMarkupPolicy::allowedProtocols());
    }

    private function descriptionRow(string $message): void
    {
        echo '<tr class="ncli-admin__field ncli-admin__field--description"><th scope="row"></th><td><p class="description">' . esc_html($this->t($message)) . '</p></td></tr>';
    }

    /**
     * @param string|null $helpHtml Pre-escaped markup that replaces the plain-text help.
     */
    private function fieldLabel(string $name, string $label, string $help, bool $forControl = true, ?string $helpHtml = null): string
    {
        $id = 'ncli-help-' . $name;
        $labelMarkup = $forControl
            ? '<label for="ncli-' . esc_attr($name) . '">' . esc_html($this->t($label)) . '</label>'
            : '<span>' . esc_html($this->t($label)) . '</span>';

        return '<span class="ncli-admin__field-label">' . $labelMarkup
            . '<button type="button" class="ncli-admin__help-toggle" aria-controls="' . esc_attr($id) . '" aria-expanded="false" aria-label="' . esc_attr(__('Help for ', 'niche-clever-interactive') . $this->t($label)) . '"><span aria-hidden="true">?</span><span class="screen-reader-text">' . esc_html(__('Help for ', 'niche-clever-interactive') . $this->t($label)) . '</span></button>'
            . '<span id="' . esc_attr($id) . '" class="ncli-admin__field-help' . ($helpHtml !== null ? ' ncli-admin__field-help--wide' : '') . '" role="note" hidden>' . ($helpHtml ?? esc_html($this->t($help))) . '</span></span>';
    }

    private function t(string $text): string
    {
        return $text;
    }
}
