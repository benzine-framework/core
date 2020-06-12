<?php

namespace Benzine\Twig\Extensions;

use Twig\Extension\AbstractExtension;

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
            $filters[$method] = new \Twig_Filter($method, [$this, $method]);
        }

        return $filters;
    }

    public function values($array)
    {
        return array_values($array);
    }
}