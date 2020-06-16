<?php

namespace Benzine\Workers;

interface WorkerInterface
{
    /**
     * @return bool true if work done successfully, false if not
     */
    public function iterate(): bool;

    /**
     * Indefinitely run an instance of this worker.
     */
    public function run(): void;
}
