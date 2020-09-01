<?php

namespace Benzine\Twig\Extensions;

use Gone\Inflection\Inflect;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class InflectionExtension extends AbstractExtension
{
    public function getFilters()
    {
        $filters = [];
        $filters['pluralize'] = new TwigFilter('pluralize', function (string $word): string {
            return Inflect::pluralize($word);
        });
        $filters['singularize'] = new TwigFilter('singularize', function (string $word): string {
            return Inflect::singularize($word);
        });

        return $filters;
    }

    public function getName()
    {
        return 'inflection_extension';
    }
}
