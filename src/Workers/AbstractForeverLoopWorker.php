<?php

namespace Benzine\Workers;

abstract class AbstractForeverLoopWorker extends AbstractWorker implements WorkerInterface
{
    public function run(): void
    {
        $this->logger->debug("Running with an interval of {$this->timeBetweenRuns} seconds.");
        while (true) {
            $this->iterate();
            sleep($this->timeBetweenRuns);
        }
    }
}
