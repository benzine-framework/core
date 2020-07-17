<?php

namespace Benzine\Workers;

use Benzine\Services\EnvironmentService;
use Benzine\Services\QueueService;
use Benzine\Services\WorkerWorkItem;
use Monolog\Logger;

abstract class AbstractQueueWorker extends AbstractWorker
{
    protected QueueService $queueService;

    /** @var string Name of the input redis queue */
    protected ?string $inputQueue;
    /** @var string[] Name of the output redis queues */
    protected ?array $outputQueues;

    protected ?array $resultItems;

    public function __construct(
        QueueService $queueService,
        Logger $logger,
        EnvironmentService $environmentService
    ) {
        $this->queueService = $queueService;
        parent::__construct($logger, $environmentService);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Set default queues
        if (!isset($this->inputQueue)) {
            $this->inputQueue = sprintf('%s:input', $this->getClassWithoutNamespace());
        }
        if (!isset($this->outputQueues)) {
            $this->outputQueues[] = sprintf('%s:output', $this->getClassWithoutNamespace());
        }

        $this->logger->debug(
            sprintf(
                'Worker %s: Listening to "%s" and outputting on %d channel(s)',
                $this->getClassWithoutNamespace(),
                $this->inputQueue,
                count($this->outputQueues)
            )
        );
    }

    /**
     * @return QueueService
     */
    public function getQueueService(): QueueService
    {
        return $this->queueService;
    }

    /**
     * @param QueueService $queueService
     *
     * @return AbstractQueueWorker
     */
    public function setQueueService(QueueService $queueService): AbstractQueueWorker
    {
        $this->queueService = $queueService;

        return $this;
    }

    /**
     * @return string
     */
    public function getInputQueue(): string
    {
        return $this->inputQueue;
    }

    /**
     * @param string $inputQueue
     *
     * @return AbstractQueueWorker
     */
    public function setInputQueue(string $inputQueue): AbstractQueueWorker
    {
        $this->inputQueue = $inputQueue;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getOutputQueues(): array
    {
        return $this->outputQueues;
    }

    /**
     * @param string[] $outputQueues
     *
     * @return AbstractQueueWorker
     */
    public function setOutputQueues(array $outputQueues): AbstractQueueWorker
    {
        $this->outputQueues = $outputQueues;

        return $this;
    }

    public function iterate(): bool
    {
        $queueLength = $this->queueService->getQueueLength($this->inputQueue);
        $this->logger->debug(sprintf(
            'Queue %s Length: %d',
            $this->inputQueue,
            $queueLength
        ));

        if (isset($this->cliArguments['stop-on-zero']) && true === $this->cliArguments['stop-on-zero'] && 0 == $queueLength) {
            $this->logger->warning('--stop-on-zero is set, and the queue length is zero! Stopping!');
            exit;
        }

        if ($queueLength <= 0) {
            return false;
        }

        $items = $this->queueService->pop($this->inputQueue);
        $this->resultItems = [];

        foreach ($items as $item) {
            try {
                $processResults = $this->process($item);
            } catch (\Exception $e) {
                $this->returnToInputQueue($item);

                $this->logger->error(
                    'Exception encountered while processing message queue.',
                    [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'code' => $e->getCode(),
                        'trace' => array_slice($e->getTrace(), 0, 5),
                    ]
                );
                continue;
            }

            if (is_array($processResults)) {
                foreach ($processResults as $processResult) {
                    $this->resultItems[] = $processResult;
                }
            } else if (null !== $processResults) {
                $this->resultItems[] = $processResults;
            }
        }

        foreach ($this->outputQueues as $outputQueue) {
            $this->queueService->push($outputQueue, $this->resultItems);
        }

        return true;
    }

    /**
     * @return null|array
     */
    public function getResultItems(): ?array
    {
        return $this->resultItems;
    }

    /**
     * Send work item back to the queue it came from.
     *
     * @param WorkerWorkItem $item
     */
    protected function returnToInputQueue(WorkerWorkItem $item): void
    {
        $this->queueService->push($this->inputQueue, [$item]);
    }

    protected function sendToSuccessQueues(WorkerWorkItem $item): int
    {
        $queuedItems = 0;
        foreach ($this->outputQueues as $outputQueue) {
            $queuedItems += $this->queueService->push($outputQueue, [$item]);
        }

        return $queuedItems;
    }

    protected function sendToFailureQueue(WorkerWorkItem $item): void
    {
        $this->queueService->push($this->getFailureQueue(), [$item]);
    }

    protected function getFailureQueue(): string
    {
        return sprintf('%s:failures', $this->inputQueue);
    }

    /**
     * @param WorkerWorkItem $item
     *
     * @return WorkerWorkItem|WorkerWorkItem[]|null
     */
    abstract protected function process(WorkerWorkItem $item);
}
