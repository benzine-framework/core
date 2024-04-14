<?php

declare(strict_types=1);

namespace Benzine\Tests;

use PHPUnit\Framework\TestCase;

abstract class AbstractTestCase extends TestCase
{
    use Traits\OverrideProtectionTrait;
    use Traits\ArrayEquitabilityTrait;

    private $singleTestTime;

    private $waypoint_count;
    private $waypoint_last_time;

    public function setUp(): void
    {
        parent::setUp();
        $this->singleTestTime     = microtime(true);
        $this->waypoint_count     = 0;
        $this->waypoint_last_time = $this->singleTestTime;
    }

    public function tearDown(): void
    {
        parent::tearDown();
        if (defined('DEBUG') && DEBUG) {
            $time = microtime(true) - $this->singleTestTime;
            echo '' . get_called_class() . ':' . $this->getName() . ': Took ' . number_format($time, 3) . " seconds\n\n";
        }
    }

    public function waypoint($message = ''): void
    {
        if (defined('DEBUG') && DEBUG) {
            $time_since_last_waypoint = number_format((microtime(true) - $this->waypoint_last_time) * 1000, 2, '.', '');
            $time_since_begin         = number_format((microtime(true) - $this->singleTestTime) * 1000, 2, '.', '');
            ++$this->waypoint_count;
            if (1 == $this->waypoint_count) {
                echo "\n";
            }
            echo " > Waypoint {$this->waypoint_count} - {$time_since_last_waypoint}ms / {$time_since_begin}ms {$message}\n";
            $this->waypoint_last_time = microtime(true);
        }
    }
}
