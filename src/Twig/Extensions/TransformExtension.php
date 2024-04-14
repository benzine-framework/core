<?php

declare(strict_types=1);

namespace Benzine\Twig\Extensions;

use Camel\CaseTransformer;
use Camel\Format;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TransformExtension extends AbstractExtension
{
    private $transformers = [
        'Camel',
        'ScreamingSnake',
        'Snake',
        'Spinal',
        'Studly',
    ];

    public function getFilters()
    {
        $filters = [];
        foreach ($this->transformers as $fromTransformer) {
            foreach ($this->transformers as $toTransformer) {
                $name           = 'transform_' . strtolower($fromTransformer) . '_to_' . strtolower($toTransformer);
                $context        = $this;
                $filters[$name] =
                    new TwigFilter($name, fn (string $word): string => $context->transform($word, $fromTransformer, $toTransformer));
            }
        }

        return $filters;
    }

    public function transform($string, $from, $to)
    {
        $fromTransformer = $this->getTransformer($from);
        $toTransformer   = $this->getTransformer($to);

        $transformer = new CaseTransformer($fromTransformer, $toTransformer);

        return $transformer->transform($string);
    }

    public function getName()
    {
        return 'transform_extension';
    }

    protected function getTransformer($name)
    {
        switch (strtolower($name)) {
            case 'camel':
            case 'camelcase':
                return new Format\CamelCase();

            case 'screaming':
            case 'screamingsnake':
            case 'screamingsnakecase':
                return new Format\ScreamingSnakeCase();

            case 'snake':
            case 'snakecase':
                return new Format\SnakeCase();

            case 'spinal':
            case 'spinalcase':
                return new Format\SpinalCase();

            case 'studly':
                return new Format\StudlyCaps();

            default:
                throw new TransformExtensionException("Unknown transformer: \"{$name}\".");
        }
    }
}
