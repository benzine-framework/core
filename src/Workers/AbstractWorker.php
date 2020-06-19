<?php

namespace Benzine\Workers;

use Benzine\Services\EnvironmentService;
use Monolog\Logger;

abstract class AbstractWorker implements WorkerInterface
{
    protected Logger $logger;
    protected EnvironmentService $environmentService;
    protected array $cliArguments;
    protected int $timeBetweenRuns = 1;

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

    protected function setUp(): void
    {
    }

    /**
     * @return array
     */
    public function getCliArguments(): array
    {
        return $this->cliArguments;
    }

    /**
     * @param array $cliArguments
     *
     * @return AbstractWorker
     */
    public function setCliArguments(array $cliArguments): AbstractWorker
    {
        $this->cliArguments = $cliArguments;

        return $this;
    }

    public function run(): void
    {
        $this->logger->debug("Running with an interval of {$this->timeBetweenRuns}");
        while (true) {
            $didWork = $this->iterate();
            if (!$didWork) {
                sleep($this->timeBetweenRuns);
            }
        }
    }

    protected function getClassWithoutNamespace(): string
    {
        $classNameElems = explode('\\', get_called_class());

        return end($classNameElems);
    }
}
