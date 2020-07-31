<?php

namespace Benzine\Twig\Extensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ArrayValuesTwigExtension extends AbstractExtension
{
    public function getName()
    {
        return 'Array_Values Twig Extension';
    }

    public function getFilters()
    {
        $filters = [];
        $methods = ['values'];
        foreach ($methods as $method) {
            $filters[$method] = new TwigFilter($method, [$this, $method]);
        }

        return $filters;
    }

    public function values($array)
    {
        return array_values($array);
    }
}
