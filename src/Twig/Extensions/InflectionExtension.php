<?php

declare(strict_types=1);

namespace Benzine\Twig\Extensions;

use MatthewBaggett\Inflection\Inflect;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class InflectionExtension extends AbstractExtension
{
    public function getFilters()
    {
        $filters                = [];
        $filters['pluralize']   = new TwigFilter('pluralize', fn (?string $word = null): string => !empty($word) ? Inflect::pluralize($word) : '');
        $filters['singularize'] = new TwigFilter('singularize', fn (?string $word = null): string => !empty($word) ? Inflect::singularize($word) : '');

        return $filters;
    }

    public function getName()
    {
        return 'inflection_extension';
    }
}
