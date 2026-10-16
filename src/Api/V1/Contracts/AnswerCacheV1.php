<?php

namespace NicheCleverInteractive\Core\Api\V1\Contracts;

use NicheCleverInteractive\Core\Api\V1\Value\CacheKeyV1;
use NicheCleverInteractive\Core\Api\V1\Value\CachedAnswerV1;

interface AnswerCacheV1
{
    public function lookup(CacheKeyV1 $key): ?CachedAnswerV1;

    /**
     * Offered after Core has validated a provider answer for an eligible request. The cache decides
     * whether to keep it; $question is the visitor's wording, supplied so an owner can review it.
     * A cache that keeps question text should delete it when the entry is rejected or expires.
     */
    public function store(CacheKeyV1 $key, CachedAnswerV1 $answer, string $question = ''): void;
}
