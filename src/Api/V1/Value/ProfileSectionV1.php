<?php

namespace NicheCleverInteractive\Core\Api\V1\Value;

use InvalidArgumentException;

/**
 * A block an extension adds to one of a profile's tabs. Core chooses the tab names and draws the
 * page, tab bar and panel; the extension names a permitted tab, a key and a title, and supplies
 * the body when Core asks for it.
 */
final class ProfileSectionV1
{
    /** Tabs of the profile workspace. `questions` and `cache` appear only when an extension adds a section to them. */
    public const TABS = ['setup', 'profile', 'ai-corpus', 'conversation', 'questions', 'collections', 'appearance-limits', 'placement', 'cache', 'diagnostics'];

    private string $tab;
    private string $key;
    private string $title;
    private bool $inlineWithPanel;

    /**
     * @param bool $inlineWithPanel True keeps the extension block with Core's General fields instead
     *                               of giving it a separate sub-tab.
     */
    public function __construct(string $tab, string $key, string $title, bool $inlineWithPanel = false)
    {
        $title = trim($title);
        if (!in_array($tab, self::TABS, true) || preg_match('/^[a-z][a-z0-9_]{2,40}$/D', $key) !== 1
            || $title === '' || strlen($title) > 80 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $title) === 1) {
            throw new InvalidArgumentException('A profile section needs a permitted tab, a bounded key and a short plain-text title.');
        }

        $this->tab = $tab;
        $this->key = $key;
        $this->title = $title;
        $this->inlineWithPanel = $inlineWithPanel;
    }

    public function tab(): string
    {
        return $this->tab;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function inlineWithPanel(): bool
    {
        return $this->inlineWithPanel;
    }
}
