<?php

declare(strict_types=1);

namespace Benzine\Tests;

use Benzine\App;

abstract class AbstractCoreTest extends AbstractTestCase
{
    protected App $app;

    public function setUp(): void
    {
        parent::setUp();
        $this->app = new TestApp();
    }
}
