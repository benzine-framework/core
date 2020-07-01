<?php

namespace Benzine\Tests\Traits;

trait ArrayEquitabilityTrait
{
    public function assertArraysEquitable($expected, $actual): void
    {
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);
    }
}
