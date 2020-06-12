<?php

namespace Benzine\Twig\Extensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ArrayUniqueTwigExtension extends AbstractExtension
{
    public function getName()
    {
        return 'ArrayUnique Twig Extension';
    }

    public function getFilters()
    {
        $filters = [];
        $methods = ['unique'];
        foreach ($methods as $method) {
            $filters[$method] = new TwigFilter($method, [$this, $method]);
        }

        return $filters;
    }

    public function unique($array)
    {
        if (is_array($array)) {
            return array_unique($array, SORT_REGULAR);
        }

        return $array;
    }
}
