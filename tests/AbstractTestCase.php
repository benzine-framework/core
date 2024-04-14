<?php

declare(strict_types=1);

namespace Benzine\Tests;

use PHPUnit\Framework\TestCase;

abstract class AbstractTestCase extends TestCase
{
    use Traits\OverrideProtectionTrait;
    use Traits\ArrayEquitabilityTrait;
}
