<?php

namespace Benzine\Twig\Extensions;

use Gone\Inflection\Inflect;
use Twig\Extension\AbstractExtension;

class InflectionExtension extends AbstractExtension
{
    public function getFilters()
    {
        $filters = [];
        $filters['pluralize'] = new \Twig_SimpleFilter('pluralize', function ($word) {
            return Inflect::pluralize($word);
        });
        $filters['singularize'] = new \Twig_SimpleFilter('singularize', function ($word) {
            return Inflect::singularize($word);
        });

        return $filters;
    }

    public function getName()
    {
        return 'inflection_extension';
    }
}
