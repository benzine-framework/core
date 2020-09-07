<?php

namespace Benzine\Router;

use Cache\Adapter\Chain\CachePoolChain;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Monolog\Logger;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;

class Router
{
    /** @var Route[] */
    private array $routes = [];
    private Logger $logger;
    private CachePoolChain $cachePoolChain;
    private int $cacheTTL = 60;

    private bool $routesArePopulated = false;

    public function __construct(Logger $logger, CachePoolChain $cachePoolChain)
    {
        $this->logger = $logger;
        $this->cachePoolChain = $cachePoolChain;
    }

    public function loadRoutesFromAnnotations(
        array $controllerPaths,
        string $baseNamespace = null
    ): void {
        AnnotationRegistry::registerLoader('class_exists');

        $reader = new AnnotationReader();

        foreach ($controllerPaths as $controllerPath) {
            if (!is_dir($controllerPath)) {
                continue;
            }

            $dirIterator = new \RecursiveDirectoryIterator($controllerPath);
            $iteratorIterator = new \RecursiveIteratorIterator($dirIterator);
            $phpFiles = new \RegexIterator($iteratorIterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

            foreach ($phpFiles as $controllerFile) {
                $fileClassName = ltrim(str_replace([$controllerPath, '/', '.php'], ['', '\\', ''], $controllerFile[0]), '\\');
                $expectedClasses = [
                    $baseNamespace . '\\Controllers\\' . $fileClassName,
                    'Benzine\\Controllers\\' . $fileClassName,
                ];

                foreach ($expectedClasses as $expectedClass) {
                    if (!class_exists($expectedClass)) {
                        continue;
                    }

                    $rc = new \ReflectionClass($expectedClass);
                    if ($rc->isAbstract()) {
                        continue;
                    }

                    foreach ($rc->getMethods() as $method) {
                        if (!$method->isPublic()) {
                            continue;
                        }

                        $routeAnnotation = $reader->getMethodAnnotation($method, \Benzine\Annotations\Route::class);
                        if (!($routeAnnotation instanceof \Benzine\Annotations\Route)) {
                            continue;
                        }

                        foreach($routeAnnotation->methods as $httpMethod) {
                            $newRoute = new Route($this->logger);

                            $newRoute
                                ->setHttpMethod($httpMethod)
                                ->setRouterPattern('/' . ltrim($routeAnnotation->path, '/'))
                                ->setCallback($method->class . ':' . $method->name)
                                ->setWeight($routeAnnotation->weight);

                            foreach ($routeAnnotation->domains as $domain) {
                                $newRoute->addValidDomain($domain);
                            }

                            $this->addRoute($newRoute);
                        }
                    }

                    break;
                }
            }
        }
    }

    public function populateRoutes(App $app, ServerRequestInterface $request = null): App
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

    protected function weighRoutes(string $host = null): self
    {
        $allocatedRoutes = [];
        if (is_array($this->routes) && count($this->routes) > 0) {
            uasort($this->routes, function (Route $a, Route $b) {
                return $a->getWeight() > $b->getWeight();
            });

            foreach ($this->routes as $index => $route) {
                $routeKey = $route->getHttpMethod().$route->getRouterPattern();
                if (!isset($allocatedRoutes[$routeKey]) && ($route->isInContainedInValidDomains($host) || !$route->hasValidDomains())) {
                    $allocatedRoutes[$routeKey] = true;
                } else {
                    unset($this->routes[$index]);
                }
            }
        }

        return $this;
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
        $time = microtime(true);
        $cacheItem = $this->cachePoolChain->getItem('routes');
        if (!$cacheItem || null === $cacheItem->get()) {
            return false;
        }

        $this->routes = $cacheItem->get();
        $this->logger->debug(sprintf('Loaded routes from Cache in %sms', number_format((microtime(true) - $time) * 1000, 2)));

        return true;
    }

    public function cache(): Router
    {
        $routeItem = $this->cachePoolChain
            ->getItem('routes')
            ->set($this->getRoutes())
            ->expiresAfter($this->cacheTTL)
        ;
        $this->cachePoolChain->save($routeItem);

        return $this;
    }
}
