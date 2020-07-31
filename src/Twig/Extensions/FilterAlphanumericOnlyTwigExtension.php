<?php

namespace Benzine\Twig\Extensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class FilterAlphanumericOnlyTwigExtension extends AbstractExtension
{
    public function getName()
    {
        return 'Filter Alphanumeric Only Twig Extension';
    }

    public function getFilters()
    {
        $filters = [];
        $methods = ['filteralphaonly'];
        foreach ($methods as $method) {
            $filters[$method] = new TwigFilter($method, [$this, $method]);
        }

        return $filters;
    }

    public function filteralphaonly($string)
    {
        return preg_replace('/[^a-z0-9_]+/i', '', $string);
    }
}
