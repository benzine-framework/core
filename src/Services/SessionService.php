<?php

namespace Benzine\Services;

class SessionService
{
    protected \Redis $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }
}
