<?php

namespace Benzine\Redis;

use Monolog\Logger;

/**
 * A wrapper around \Redis for my own sanity.
 */
class Redis
{
    private string $host;
    private int $port;
    private float $timeout;
    private \Redis $redis;
    private Logger $logger;

    /** @var Lua\AbstractLuaExtension[] */
    private array $scripts;

    public function __construct(Logger $logger, string $host, int $port = 6379, float $timeout = 0.0)
    {
        $this->logger = $logger;

        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;

        $this->redis = new \Redis();
    }

    public function __call($name, $arguments)
    {
        $this->runBeforeRedisCommand();

        if (method_exists($this->redis, $name)) {
            return call_user_func_array([$this->redis, $name], $arguments);
        }

        foreach ($this->scripts as $script) {
            foreach ($script->getFunctionNames() as $functionName) {
                if (strtolower($name) == strtolower($functionName)) {
                    $script->load();

                    return $this->evalSha($script->getHash(), $arguments);
                }
            }
        }
    }

    public function getUnderlyingRedis(): \Redis
    {
        return $this->redis;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function isAvailable(): bool
    {
        try {
            $this->ping('am I human?');

            return true;
        } catch (\RedisException $redisException) {
            return false;
        }
    }

    public function initialiseExtensions(): void
    {
        $this->scripts[] = new Lua\SetIfHigher($this);
        $this->scripts[] = new Lua\SetIfLower($this);
        $this->scripts[] = new Lua\ZAddIfHigher($this);
        $this->scripts[] = new Lua\ZAddIfLower($this);
    }

    public function connect($host, $port = 6379, $timeout = 0.0, $reserved = null, $retryInterval = 0, $readTimeout = 0.0): void
    {
        throw new \RedisException('Do not directly call connect()');
    }

    private function runBeforeRedisCommand(): void
    {
        if (!$this->redis->isConnected()) {
            $this->redis->pconnect($this->host, $this->port, $this->timeout);
            $this->initialiseExtensions();
        }
    }
}
