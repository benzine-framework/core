<?php

namespace Benzine\Workers;

class ExampleQueueWorker extends AbstractQueueWorker
{
    protected function process(WorkerWorkItem $item): ?WorkerWorkItem
    {
        return $item->setOutput(sqrt($item->getInput()));
    }
}
