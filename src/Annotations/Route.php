<?php declare(strict_types=1);

namespace Benzine\Annotations;

use Doctrine\Common\Annotations\Annotation\Required;

/**
 * @Annotation
 *
 * @Target("METHOD")
 */
class Route
{
    /** @var array */
    public array $methods = ['GET'];

    /**
     * @Required
     * @var string
     */
    public string $path;

    /** @var string  */
    public string $access = \Benzine\Router\Route::ACCESS_PUBLIC;

    /** @var int */
    public int $weight = 100;

    /** @var array */
    public array $domains = [];
}
