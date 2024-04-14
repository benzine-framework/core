<?php

declare(strict_types=1);

namespace Benzine\Tests\Dependencies\Monolog;

use Benzine\Tests\AbstractCoreTest;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * @internal
 *
 * @coversNothing
 */
class MonologTest extends AbstractCoreTest
{
    public function testMonolog(): void
    {
        $logger = $this->app->get(Logger::class);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }
}
