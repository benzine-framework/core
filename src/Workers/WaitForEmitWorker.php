<?php

declare(strict_types=1);

namespace Benzine\Workers;

use Benzine\Redis\Redis;
use Benzine\Services\EnvironmentService;
use Monolog\Logger;

abstract class WaitForEmitWorker extends AbstractWorker
{
    public $callback;
    protected array $messageTypes = [];

    public function __construct(
        protected Redis $redis,
        Logger $logger,
        EnvironmentService $environmentService
    ) {
        parent::__construct($logger, $environmentService);
        $this->setCallback([$this, 'message']);
    }

    public function addMessageTypeListener(string $messageType)
    {
        $this->messageTypes[] = $messageType;
        $this->logger->debug("Added {$messageType} to message type handlers.");

        return $this;
    }

    public function setCallback($callback)
    {
        $this->callback = $callback;

        return $this;
    }

    public function recv($redis, $pattern, $chan, $msg): void
    {
        $json = json_decode($msg, true);
        if (in_array($json['MESSAGE_TYPE'], $this->messageTypes, true)) {
            call_user_func($this->callback, $json);
        }
    }

    public function iterate(): bool
    {
        $this->logger->debug('Running Emit Worker');
        $this->redis->listen([$this, 'recv']);

        return true;
    }
}
