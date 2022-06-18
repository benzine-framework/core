<?php

namespace Benzine\Workers;

use Benzine\Redis\Redis;
use Benzine\Services\EnvironmentService;
use Benzine\Workers\AbstractWorker;
use Monolog\Logger;

abstract class WaitForEmitWorker extends AbstractWorker
{
    protected array $messageTypes = [];

    public function addMessageTypeListener(string $messageType)
    {
        $this->messageTypes[] = $messageType;
        $this->logger->debug("Added {$messageType} to message type handlers.");
        return $this;
    }

    public $callback;

    public function setCallback($callback)
    {
        $this->callback = $callback;
        return $this;
    }

    public function __construct(
        protected Redis    $redis,
        Logger             $logger,
        EnvironmentService $environmentService
    )
    {
        parent::__construct($logger, $environmentService);
        $this->setCallback([$this, 'message']);
    }

    public function run(): void
    {
        $this->logger->debug("Running Emit Worker");
        $this->redis->listen(array($this, "recv"));
    }

    public function recv($redis, $pattern, $chan, $msg)
    {
        $json = json_decode($msg, true);
        if (in_array($json['MESSAGE_TYPE'], $this->messageTypes)) {
            call_user_func($this->callback, $json);
        }
    }

    public function iterate(): bool
    {
    }
}