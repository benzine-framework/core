<?php

namespace Benzine\Workers;

class ExampleQueueWorker extends AbstractQueueWorker implements QueueWorkerInterface
{
    public function process(WorkerWorkItem $item): ?WorkerWorkItem
    {
        return $item->setOutput(sqrt($item->getInput()));
    }
}
