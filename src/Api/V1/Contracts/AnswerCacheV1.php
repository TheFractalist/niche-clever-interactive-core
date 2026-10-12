<?php

namespace NicheCleverInteractive\Core\Api\V1\Contracts;

use NicheCleverInteractive\Core\Api\V1\Value\CacheKeyV1;
use NicheCleverInteractive\Core\Api\V1\Value\CachedAnswerV1;

interface AnswerCacheV1
{
    public function lookup(CacheKeyV1 $key): ?CachedAnswerV1;

    public function store(CacheKeyV1 $key, CachedAnswerV1 $answer): void;
}
