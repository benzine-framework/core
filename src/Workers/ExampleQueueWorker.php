<?php

declare(strict_types=1);

namespace Benzine\Workers;

class ExampleQueueWorker extends AbstractQueueWorker
{
    protected function process(WorkerWorkItem $item): ?WorkerWorkItem
    {
        return $item->setOutput(sqrt($item->getInput()));
    }
}
