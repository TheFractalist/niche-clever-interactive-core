<?php

declare(strict_types=1);

namespace NicheCleverInteractive\Core\Conversation;

/**
 * Opaque per-page-session identifier used only to group turns. It identifies no
 * visitor and is never trusted for anything but grouping.
 */
final class ConversationId
{
    public static function isValid($value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{32}$/D', $value) === 1;
    }

    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}
