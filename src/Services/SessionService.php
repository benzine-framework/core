<?php

declare(strict_types=1);

namespace Benzine\Services;

use Benzine\Redis\Redis;

class SessionService implements \SessionHandlerInterface
{
    protected Redis $redis;
    protected $oldID;
    private ?bool $redisIsAvailable = null;

    private int $lifetime     = 43200;
    private array $dirtyCheck = [];

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    public function setLifetime(int $lifetime): void
    {
        $this->lifetime = $lifetime;
    }

    public function initSession(): void
    {
        if ('cli' === PHP_SAPI || 'phpdbg' == PHP_SAPI || PHP_SESSION_ACTIVE === session_status()) {
            return;
        }

        // set how long server should keep session data
        ini_set('session.gc_maxlifetime', $this->getLifetime());

        // set how long each client should remember their session id
        session_set_cookie_params($this->getLifetime());
        session_set_save_handler($this);

        // Prevent session from influencing the slim headers sent back to the browser.
        session_cache_limiter(null);

        // Begin the Session
        session_start();
    }

    public function close()
    {
        return true;
    }

    public function destroy($session_id)
    {
        $this->oldID = $session_id;

        return true;
    }

    public function gc($maxlifetime)
    {
        return true;
    }

    public function open($save_path, $name)
    {
        return true;
    }

    public function useRedis(): bool
    {
        if ($this->redisIsAvailable === null) {
            $this->redisIsAvailable = $this->redis->isAvailable();
        }

        return $this->redisIsAvailable;
    }

    public function read($session_id)
    {
        if ($this->useAPCU()) {
            if (apcu_exists('read-' . $session_id)) {
                return apcu_fetch('read-' . $session_id);
            }
        }

        if (!empty($this->oldID)) {
            $session_id = $this->oldID ? $this->oldID : $session_id;
        }

        $result = '';
        if ($this->useRedis()) {
            $serialised = $this->redis->get("session:{$session_id}");
            if (null != $serialised) {
                if (!empty($this->oldID)) {
                    // clean up old session after regenerate
                    $this->redis->del("session:{$session_id}");
                    $this->oldID = null;
                }
                $result = unserialize($serialised);
            }
        }

        if ($this->useAPCU()) {
            apcu_store('read-' . $session_id, $result, 30);
        } else {
            $this->dirtyCheck['read-' . $session_id] = crc32($result);
        }

        return $result;
    }

    /**
     * @param string $session_id
     * @param string $session_data
     *
     * @return bool always returns true
     */
    public function write($session_id, $session_data): bool
    {
        if ($this->useAPCU()) {
            $dirty = crc32(apcu_fetch('read-' . $session_id)) != crc32($session_data);
        } else {
            $dirty = $this->dirtyCheck['read-' . $session_id] != crc32($session_data);
        }

        if ($this->useRedis() && $dirty) {
            $this->redis->set("session:{$session_id}", serialize($session_data));
            $this->redis->expire("session:{$session_id}", $this->getLifetime());
        }

        if ($this->useAPCU()) {
            apcu_store('read-' . $session_id, $session_data);
        }

        return true;
    }

    public function has(string $key): bool
    {
        $this->initSession();

        return isset($_SESSION[$key]);
    }

    public function get(string $key)
    {
        $this->initSession();

        if (isset($_SESSION[$key])) {
            return unserialize($_SESSION[$key]);
        }

        return '';
    }

    public function getAll(): array
    {
        $this->initSession();

        if (is_array($_SESSION) && count($_SESSION) > 0) {
            $output = [];
            foreach ($_SESSION as $k => $v) {
                $output[$k] = unserialize($v);
            }

            return $output;
        }

        return [];
    }

    public function set(string $key, $value): bool
    {
        $this->initSession();

        $_SESSION[$key] = serialize($value);

        return true;
    }

    public function dispose(string $key): bool
    {
        $this->initSession();

        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);

            return true;
        }

        return false;
    }

    private function useAPCU(): bool
    {
        return function_exists('apcu_store');
    }
}
