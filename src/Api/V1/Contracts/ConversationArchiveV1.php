<?php

namespace NicheCleverInteractive\Core\Api\V1\Contracts;

use NicheCleverInteractive\Core\Api\V1\Value\ArchivedTurnV1;

/**
 * Core's guarded view of its conversation history, handed to a governance extension. Every method
 * requires the current user to have `manage_options` (Core checks it itself, so an extension cannot
 * skip the check), validates its arguments, and works in bounded pages. Dates are `Y-m-d`, inclusive,
 * interpreted in UTC. Core never exposes the table, a query or any identifier of a visitor.
 */
interface ConversationArchiveV1
{
    public const MAX_PAGE = 500;

    /** @throws \RuntimeException when the current user may not manage the plugin or storage fails */
    public function count(?string $profileId, ?string $fromDate, ?string $toDate): int;

    /**
     * Turns with an id greater than $afterId, oldest first, at most $limit (1 to 500).
     *
     * @return list<ArchivedTurnV1>
     * @throws \RuntimeException
     */
    public function page(?string $profileId, ?string $fromDate, ?string $toDate, int $afterId, int $limit): array;

    /**
     * Deletes up to $limit (1 to 500) matching turns, oldest first, and returns how many were removed.
     * Erasure is always scoped to one profile; there is no delete-everything call.
     *
     * @throws \RuntimeException
     */
    public function delete(string $profileId, ?string $fromDate, ?string $toDate, int $limit): int;
}
