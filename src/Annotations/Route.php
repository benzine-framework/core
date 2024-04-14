<?php

declare(strict_types=1);

namespace Benzine\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;

/**
 * @Annotation
 * @Target("METHOD")
 */
class Route
{
    public array $methods = ['GET'];

    /**
     * @Required
     */
    public string $path;

    public string $access = \Benzine\Router\Route::ACCESS_PUBLIC;

    public int $weight = 100;

    public array $domains = [];
}
