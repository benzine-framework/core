<?php

namespace Benzine\Twig\Extensions;

use MatthewBaggett\Inflection\Inflect;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class InflectionExtension extends AbstractExtension
{
    public function getFilters()
    {
        $filters = [];
        $filters['pluralize'] = new TwigFilter('pluralize', function (string $word = null): string {
            return !empty($word) ? Inflect::pluralize($word) : '';
        });
        $filters['singularize'] = new TwigFilter('singularize', function (string $word = null): string {
            return !empty($word) ? Inflect::singularize($word) : '';
        });

        return $filters;
    }

    public function getName()
    {
        return 'inflection_extension';
    }
}
