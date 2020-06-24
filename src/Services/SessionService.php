<?php

namespace Benzine\Services;

use ⌬\Session\Session;

class SessionService implements \SessionHandlerInterface
{
    protected \Redis $redis;
    protected $oldID;

    private int $lifetime = 43200;
    private bool $sessionInitialised = false;
    private array $dirtyCheck = [];

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * @return int
     */
    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    /**
     * @param int $lifetime
     */
    public function setLifetime(int $lifetime): void
    {
        $this->lifetime = $lifetime;
    }

    public function initSession()
    {
        if ($this->sessionInitialised) {
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
        $this->sessionInitialised = true;
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

    public function read($session_id)
    {
        if ($this->useAPCU()) {
            if (apcu_exists('read-'.$session_id)) {
                return apcu_fetch('read-'.$session_id);
            }
        }

        if (!empty($this->oldID)) {
            $session_id = $this->oldID ? $this->oldID : $session_id;
        }

        $serialised = $this->redis->get("session:{$session_id}");
        if (null != $serialised) {
            if (!empty($this->oldID)) {
                // clean up old session after regenerate
                $this->redis->del("session:{$session_id}");
                $this->oldID = null;
            }
            $result = unserialize($serialised);
        } else {
            $result = '';
        }

        if ($this->useAPCU()) {
            apcu_store('read-'.$session_id, $result, 30);
        } else {
            $this->dirtyCheck['read-'.$session_id] = crc32($result);
        }

        return $result;
    }

    public function write($session_id, $session_data)
    {
        $dirty = false;
        if ($this->useAPCU()) {
            $dirty = crc32(apcu_fetch('read-'.$session_id)) != crc32($session_data);
        } else {
            $dirty = $this->dirtyCheck['read-'.$session_id] != crc32($session_data);
        }
        if ($dirty) {
            $this->redis->set("session:{$session_id}", serialize($session_data));
            $this->redis->expire("session:{$session_id}", $this->getLifetime());
        }
        apcu_store('read-'.$session_id, $session_data);

        return true;
    }

    public function get(string $key)
    {
        $this->initSession();

        if (isset($_SESSION[$key])) {
            return unserialize($_SESSION[$key]);
        }

        return '';
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
        return false;

        return function_exists('apcu_store');
    }
}
