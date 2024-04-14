<?php

declare(strict_types=1);

namespace Benzine\Redis\Lua;

class ZAddIfLower extends AbstractLuaExtension
{
    protected function getScript(): string
    {
        return <<<'LUA'
                local c = tonumber(redis.call('zscore', KEYS[1], ARGV[1]));
                if c then
                    if tonumber(KEYS[2]) < c then
                        redis.call('zadd', KEYS[1], KEYS[2], ARGV[1])
                        return tonumber(KEYS[2]) - c
                    else
                        return 0
                    end
                else
                    redis.call('zadd', KEYS[1], KEYS[2], ARGV[1])
                    return 'OK'
                end
            LUA;
    }
}
