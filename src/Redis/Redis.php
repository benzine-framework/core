<?php

namespace Benzine\Redis;

use Monolog\Logger;

class Redis
{
    /**
     * A wrapper around \Redis for my own sanity.
     *
     * @method isConnected
     * @method getHost
     * @method getPort
     * @method getDbNum
     * @method getTimeout
     * @method getReadTimeout
     * @method getPersistentID
     * @method getAuth
     * @method swapdb(int $db1, int $db2)
     * @method setOption($option, $value)
     * @method getOption($option)
     * @method ping($message=null)
     * @method echo($message)
     * @method get($key)
     * @method set($key, $value, $timeout = null)
     * @method setex($key, $ttl, $value)
     * @method psetex($key, $ttl, $value)
     * @method setnx($key, $value)
     * @method del($key1, ...$otherKeys)
     * @method delete($key1, $key2 = null, $key3 = null)
     * @method unlink($key1, $key2 = null, $key3 = null)
     * @method multi($mode = Redis::MULTI)
     * @method pipeline
     * @method exec
     * @method discard
     * @method watch($key)
     * @method unwatch
     * @method subscribe($channels, $callback)
     * @method psubscribe($patterns, $callback)
     * @method publish($channel, $message)
     * @method pubsub($keyword, $argument)
     * @method unsubscribe($channels = null)
     * @method punsubscribe($patterns = null)
     * @method exists($key)
     * @method incr($key)
     * @method incrByFloat($key, $increment)
     * @method incrBy($key, $value)
     * @method decr($key)
     * @method decrBy($key, $value)
     * @method lPush($key, ...$value1)
     * @method rPush($key, ...$value1)
     * @method lPushx($key, $value)
     * @method rPushx($key, $value)
     * @method lPop($key)
     * @method rPop($key)
     * @method blPop($keys, $timeout)
     * @method brPop(array $keys, $timeout)
     * @method lLen($key)
     * @method lSize($key)
     * @method lIndex($key, $index)
     * @method lGet($key, $index)
     * @method lSet($key, $index, $value)
     * @method lRange($key, $start, $end)
     * @method lGetRange($key, $start, $end)
     * @method lTrim($key, $start, $stop)
     * @method listTrim($key, $start, $stop)
     * @method lRem($key, $value, $count)
     * @method lRemove($key, $value, $count)
     * @method lInsert($key, $position, $pivot, $value)
     * @method sAdd($key, ...$value1)
     * @method sRem($key, ...$member1)
     * @method sRemove($key, ...$member1)
     * @method sMove($srcKey, $dstKey, $member)
     * @method sIsMember($key, $value)
     * @method sContains($key, $value)
     * @method sCard($key)
     * @method sPop($key, $count = 1)
     * @method sRandMember($key, $count = 1)
     * @method sInter($key1, ...$otherKeys)
     * @method sInterStore($dstKey, $key1, ...$otherKeys)
     * @method sUnion($key1, ...$otherKeys)
     * @method sUnionStore($dstKey, $key1, ...$otherKeys)
     * @method sDiff($key1, ...$otherKeys)
     * @method sDiffStore($dstKey, $key1, ...$otherKeys)
     * @method sMembers($key)
     * @method sGetMembers($key)
     * @method sScan($key, &$iterator, $pattern = null, $count = 0)
     * @method getSet($key, $value)
     * @method randomKey
     * @method select($dbIndex)
     * @method move($key, $dbIndex)
     * @method rename($srcKey, $dstKey)
     * @method renameKey($srcKey, $dstKey)
     * @method renameNx($srcKey, $dstKey)
     * @method expire($key, $ttl)
     * @method pExpire($key, $ttl)
     * @method setTimeout($key, $ttl)
     * @method expireAt($key, $timestamp)
     * @method pExpireAt($key, $timestamp)
     * @method keys($pattern)
     * @method getKeys($pattern)
     * @method dbSize
     * @method auth($password)
     * @method bgrewriteaof
     * @method slaveof($host = '127.0.0.1', $port = 6379)
     * @method slowLog(string $operation, int $length = null)
     * @method object($string = '', $key = '')
     * @method save
     * @method bgsave
     * @method lastSave
     * @method wait($numSlaves, $timeout)
     * @method type($key)
     * @method append($key, $value)
     * @method getRange($key, $start, $end)
     * @method substr($key, $start, $end)
     * @method setRange($key, $offset, $value)
     * @method strlen($key)
     * @method bitpos($key, $bit, $start = 0, $end = null)
     * @method getBit($key, $offset)
     * @method setBit($key, $offset, $value)
     * @method bitCount($key)
     * @method bitOp($operation, $retKey, $key1, ...$otherKeys)
     * @method flushDB
     * @method flushAll
     * @method sort($key, $option = null)
     * @method info($option = null)
     * @method resetStat
     * @method ttl($key)
     * @method pttl($key)
     * @method persist($key)
     * @method mset(array $array)
     * @method getMultiple(array $keys)
     * @method mget(array $array)
     * @method msetnx(array $array)
     * @method rpoplpush($srcKey, $dstKey)
     * @method brpoplpush($srcKey, $dstKey, $timeout)
     * @method zAdd($key, $options, $score1, $value1 = null, $score2 = null, $value2 = null, $scoreN = null, $valueN = null)
     * @method zRange($key, $start, $end, $withscores = null)
     * @method zRem($key, $member1, ...$otherMembers)
     * @method zDelete($key, $member1, ...$otherMembers)
     * @method zRevRange($key, $start, $end, $withscore = null)
     * @method zRangeByScore($key, $start, $end, array $options)
     * @method zRevRangeByScore($key, $start, $end, array $options)
     * @method zRangeByLex($key, $min, $max, $offset = null, $limit = null)
     * @method zRevRangeByLex($key, $min, $max, $offset = null, $limit = null)
     * @method zCount($key, $start, $end)
     * @method zRemRangeByScore($key, $start, $end)
     * @method zDeleteRangeByScore($key, $start, $end)
     * @method zRemRangeByRank($key, $start, $end)
     * @method zDeleteRangeByRank($key, $start, $end)
     * @method zCard($key)
     * @method zSize($key)
     * @method zScore($key, $member)
     * @method zRank($key, $member)
     * @method zRevRank($key, $member)
     * @method zIncrBy($key, $value, $member)
     * @method zUnionStore($output, $zSetKeys, array $weights = null, $aggregateFunction = 'SUM')
     * @method zUnion($Output, $ZSetKeys, array $Weights = null, $aggregateFunction = 'SUM')
     * @method zInterStore($output, $zSetKeys, array $weights = null, $aggregateFunction = 'SUM')
     * @method zInter($Output, $ZSetKeys, array $Weights = null, $aggregateFunction = 'SUM')
     * @method zScan($key, &$iterator, $pattern = null, $count = 0)
     * @method bzPopMax($key1, $key2, $timeout)
     * @method bzPopMin($key1, $key2, $timeout)
     * @method zPopMax($key, $count = 1)
     * @method zPopMin($key, $count = 1)
     * @method hSet($key, $hashKey, $value)
     * @method hSetNx($key, $hashKey, $value)
     * @method hGet($key, $hashKey)
     * @method hLen($key)
     * @method hDel($key, $hashKey1, ...$otherHashKeys)
     * @method hKeys($key)
     * @method hVals($key)
     * @method hGetAll($key)
     * @method hExists($key, $hashKey)
     * @method hIncrBy($key, $hashKey, $value)
     * @method hIncrByFloat($key, $field, $increment)
     * @method hMSet($key, $hashKeys)
     * @method hMGet($key, $hashKeys)
     * @method hScan($key, &$iterator, $pattern = null, $count = 0)
     * @method hStrLen(string $key, string $field)
     * @method geoadd($key, $longitude, $latitude, $member)
     * @method geohash($key, ...$member)
     * @method geopos(string $key, string $member)
     * @method geodist($key, $member1, $member2, $unit = null)
     * @method georadius($key, $longitude, $latitude, $radius, $unit, array $options = null)
     * @method georadiusbymember($key, $member, $radius, $units, array $options = null)
     * @method config($operation, $key, $value)
     * @method eval($script, $args, $numKeys = 0)
     * @method evaluate($script, $args, $numKeys = 0)
     * @method evalSha($scriptSha, $args, $numKeys = 0)
     * @method evaluateSha($scriptSha, $args, $numKeys = 0)
     * @method script($command, $script)
     * @method getLastError
     * @method clearLastError
     * @method client($command, $value = '')
     * @method dump($key)
     * @method restore($key, $ttl, $value)
     * @method migrate($host, $port, $key, $db, $timeout, $copy = false, $replace = false)
     * @method time
     * @method scan(&$iterator, $pattern = null, $count = 0)
     * @method pfAdd($key, array $elements)
     * @method pfCount($key)
     * @method pfMerge($destKey, array $sourceKeys)
     * @method rawCommand($command, $arguments)
     * @method getMode
     * @method xAck($stream, $group, $messages)
     * @method xAdd($key, $id, $messages, $maxLen = 0, $isApproximate = false)
     * @method xClaim($key, $group, $consumer, $minIdleTime, $ids, $options = [])
     * @method xDel($key, $ids)
     * @method xGroup($operation, $key, $group, $msgId = '', $mkStream = false)
     * @method xInfo($operation, $stream, $group)
     * @method xLen($stream)
     * @method xPending($stream, $group, $start = null, $end = null, $count = null, $consumer = null)
     * @method xRange($stream, $start, $end, $count = null)
     * @method xRead($streams, $count = null, $block = null)
     * @method xReadGroup($group, $consumer, $streams, $count = null, $block = null)
     * @method xRevRange($stream, $end, $start, $count = null)
     * @method xTrim($stream, $maxLen, $isApproximate)
     * @method sAddArray($key, array $values)
     * @method ping(string $string)
     */
    private string $host;
    private int $port;
    private string $password;
    private float $timeout;
    private \Redis $redis;
    private Logger $logger;

    /** @var Lua\AbstractLuaExtension[] */
    private array $scripts;

    public function __construct(Logger $logger, string $host, int $port = 6379, string $password = null, float $timeout = 0.0)
    {
        $this->logger = $logger;

        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
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
            @$this->redis->pconnect($this->host, $this->port, $this->timeout);
            if ($this->password) {
                $this->redis->auth($this->password);
            }
            $this->initialiseExtensions();
        }
    }
}
