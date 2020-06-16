<?php

namespace Benzine\Workers;

interface QueueWorkerInterface extends WorkerInterface
{
    /**
     * @param $item WorkerWorkItem
     *
     * @return null|WorkerWorkItem mutated result work item, or null
     */
    public function process(WorkerWorkItem $item): ?WorkerWorkItem;
}
