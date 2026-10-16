<?php

namespace NicheCleverInteractive\Core\Api\V1\Contracts;

use NicheCleverInteractive\Core\Api\V1\Value\EarlierTurnV1;
use NicheCleverInteractive\Core\Api\V1\Value\ProfileIdV1;

interface ConversationContextV1
{
    /**
     * Earlier validated turns of one conversation, oldest first, for use as follow-up context.
     * Core never calls this without a valid conversation identifier. It accepts at most 10 turns
     * and a bounded total size, keeps the most recent ones, and treats an exception, a non-list or
     * any wrong entry as no context at all. A request that carries context is never answered from
     * the answer cache.
     *
     * @return list<EarlierTurnV1>
     */
    public function earlierTurns(ProfileIdV1 $profileId, string $conversationId): array;
}
