<?php

namespace Benzine\Workers;

use Benzine\Services\EnvironmentService;
use Monolog\Logger;

abstract class AbstractWorker implements WorkerInterface
{
    protected Logger $logger;
    protected EnvironmentService $environmentService;
    protected int $timeBetweenRuns = 5;
    protected bool $stopOnZero = false;

    public function __construct(
        Logger $logger,
        EnvironmentService $environmentService
    ) {
        $this->logger = $logger;
        $this->environmentService = $environmentService;
        $this->setUp();
        $this->logger->info(
            sprintf(
                'Started Worker "%s".',
                $this->getClassWithoutNamespace()
            )
        );
    }

    /**
     * @param bool $stopOnZero
     */
    public function setStopOnZero(bool $stopOnZero): self
    {
        $this->stopOnZero = $stopOnZero;
        return $this;
    }
    
    protected function setUp(): void
    {
    }

    public function run(): void
    {
        $this->logger->debug("Running with an interval of {$this->timeBetweenRuns} seconds.");
        while (true) {
            $didWork = $this->iterate();
            if (!$didWork) {
                sleep($this->timeBetweenRuns);
            }
        }
    }

    public function getTimeBetweenRuns(): int
    {
        return $this->timeBetweenRuns;
    }

    public function setTimeBetweenRuns(int $timeBetweenRuns): AbstractWorker
    {
        $this->timeBetweenRuns = $timeBetweenRuns;

        return $this;
    }

    protected function getClassWithoutNamespace(): string
    {
        $classNameElems = explode('\\', get_called_class());

        return end($classNameElems);
    }
}
