<?php

declare(strict_types=1);

namespace Benzine\Twig\Extensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class ArrayValuesTwigExtension extends AbstractExtension
{
    public function getName(): string
    {
        return 'Array_Values Twig Extension';
    }

    public function getFilters(): array
    {
        $filters = [];
        $methods = ['values'];
        foreach ($methods as $method) {
            $filters[$method] = new TwigFilter($method, [$this, $method]);
        }

        return $filters;
    }

    public function values($array): string
    {
        return array_values($array);
    }
}
