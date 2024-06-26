<?php

declare(strict_types=1);

namespace Benzine\Router;

use Cache\Adapter\Chain\CachePoolChain;
use Cache\Adapter\Common\Exception\CachePoolException;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

class Router
{
    /** @var Route[] */
    protected array $routes = [];
    protected int $cacheTTL = 60;

    private bool $routesArePopulated = false;

    public function __construct(
        protected Logger $logger,
        protected CachePoolChain $cachePoolChain
    ) {}

    public function loadRoutesFromAnnotations(
        array $controllerPaths,
        ?string $baseNamespace = null
    ): void {
        AnnotationRegistry::registerLoader('class_exists');

        $reader = new AnnotationReader();

        foreach ($controllerPaths as $controllerPath) {
            if (!is_dir($controllerPath)) {
                continue;
            }

            $dirIterator      = new \RecursiveDirectoryIterator($controllerPath);
            $iteratorIterator = new \RecursiveIteratorIterator($dirIterator);

            $phpFiles         = new \RegexIterator($iteratorIterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

            foreach ($phpFiles as $controllerFile) {
                $fileClassName   = ltrim(str_replace([$controllerPath, '/', '.php'], ['', '\\', ''], $controllerFile[0]), '\\');
                $expectedClasses = [
                    $baseNamespace . '\\Actions\\' . $fileClassName,
                    $baseNamespace . '\\Controllers\\' . $fileClassName,
                    'Benzine\\Controllers\\' . $fileClassName,
                ];

                foreach ($expectedClasses as $expectedClass) {
                    if (!class_exists($expectedClass)) {
                        $this->logger->warning('While loading routes from annotations in {file}, expected class {expectedClass} does not exist.', [
                            'file'          => $controllerFile[0],
                            'expectedClass' => $expectedClass,
                        ]);

                        continue;
                    }

                    $rc = new \ReflectionClass($expectedClass);
                    if ($rc->isAbstract()) {
                        $this->logger->warning('While loading routes from annotations in {file}, expected class {expectedClass} is abstract.', [
                            'file'          => $controllerFile[0],
                            'expectedClass' => $expectedClass,
                        ]);

                        continue;
                    }

                    foreach ($rc->getMethods() as $method) {
                        if (!$method->isPublic()) {
                            // Non public methods can't have actions called on them
                            continue;
                        }

                        $routeAttibutes = $method->getAttributes(\Benzine\Annotations\Route::class);
                        foreach ($routeAttibutes as $routeAttibute) {
                            foreach ($routeAttibute->getArguments()['methods'] as $httpMethod) {
                                $newRoute = (new Route())
                                    ->setHttpMethod($httpMethod)
                                    ->setRouterPattern('/' . ltrim($routeAttibute->getArguments()['path'], '/'))
                                    ->setCallback($expectedClass . ':' . $method->name)
                                    ->setWeight($routeAttibute->getArguments()['weight'] ?? 100)
                                ;
                                if (isset($routeAttibute->getArguments()['domains']) && is_array($routeAttibute->getArguments()['domains'])) {
                                    foreach ($routeAttibute->getArguments()['domains'] as $domain) {
                                        $newRoute->addValidDomain($domain);
                                    }
                                }
                                $this->addRoute($newRoute);
                            }
                        }
                    }

                    break;
                }
            }
        }
    }

    public function populateRoutes(App $app, ?ServerRequestInterface $request = null): App
    {
        if ($this->routesArePopulated) {
            return $app;
        }

        $host = (null !== $request)
            ? $request->getUri()->getHost()
            : null;

        $this->weighRoutes($host);

        if (count($this->routes) > 0) {
            foreach ($this->getRoutes() as $route) {
                $app = $route->populateRoute($app);
            }
        }

        $this->routesArePopulated = true;

        return $app;
    }

    public function addRoute(Route $route)
    {
        $this->routes[$route->getUniqueIdentifier()] = $route;

        return $this;
    }

    /**
     * @return Route[]
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    public function loadCache(): bool
    {
        $time      = microtime(true);
        $cacheItem = $this->cachePoolChain->getItem('routes');
        if (!$cacheItem || null === $cacheItem->get()) {
            return false;
        }

        $this->routes          = $cacheItem->get();
        $timeToLoadFromCacheMs = (microtime(true) - $time) * 1000;
        if ($timeToLoadFromCacheMs >= 500) {
            $this->logger->warning(sprintf('Loaded routes from Cache in %sms, which is slower than 500ms', number_format($timeToLoadFromCacheMs, 2)));
        }

        return true;
    }

    public function cache(): Router
    {
        $routeItem = $this->cachePoolChain
            ->getItem('routes')
            ->set($this->getRoutes())
            ->expiresAfter($this->cacheTTL)
        ;

        try {
            $this->cachePoolChain->save($routeItem);
            // $this->logger->info('Cached router to cache pool');
        } catch (CachePoolException $cachePoolException) {
            $this->logger->critical('Cache Pool Exception: ' . $cachePoolException->getMessage());
        }

        return $this;
    }

    protected function weighRoutes(?string $host = null): self
    {
        $allocatedRoutes = [];
        if (is_array($this->routes) && count($this->routes) > 0) {
            uasort($this->routes, function (Route $a, Route $b) {
                $a1 = $a->getWeight();
                $b1 = $b->getWeight();
                if ($a1 === $b1) {
                    return 0;
                }

                return ($a1 > $b1) ? +1 : -1;
            });

            foreach ($this->routes as $index => $route) {
                $routeKey = $route->getHttpMethod() . $route->getRouterPattern();
                if (!isset($allocatedRoutes[$routeKey]) && ($route->isInContainedInValidDomains($host) || !$route->hasValidDomains())) {
                    $allocatedRoutes[$routeKey] = true;
                } else {
                    unset($this->routes[$index]);
                }
            }
        }

        return $this;
    }
}
