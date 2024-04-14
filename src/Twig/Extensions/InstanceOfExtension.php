<?php

declare(strict_types=1);

namespace Benzine\Twig\Extensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigTest;

class InstanceOfExtension extends AbstractExtension
{
    public function getTests()
    {
        return [
            new TwigTest('instanceof', [$this, 'isInstanceOf']),
        ];
    }

    public function isInstanceOf($var, $instance)
    {
        if (is_object($var) && $var instanceof $instance) {
            return true;
        }

        return false;
    }

    public function getName()
    {
        return 'instanceof_extension';
    }
}
