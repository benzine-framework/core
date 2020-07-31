<?php

namespace Benzine\Router;

use Cache\Adapter\Chain\CachePoolChain;
use Monolog\Logger;
use Slim\App;

class Router
{
    /** @var Route[] */
    private array $routes = [];
    private Logger $logger;
    private CachePoolChain $cachePoolChain;
    private int $cacheTTL = 60;

    public function __construct(\Redis $redis, Logger $logger, CachePoolChain $cachePoolChain)
    {
        $this->logger = $logger;
        $this->cachePoolChain = $cachePoolChain;
    }

    public function weighRoutes(): Router
    {
        $allocatedRoutes = [];
        if (is_array($this->routes) && count($this->routes) > 0) {
            uasort($this->routes, function (Route $a, Route $b) {
                return $a->getWeight() > $b->getWeight();
            });

            foreach ($this->routes as $index => $route) {
                if (($route->isInContainedInValidDomains() || !$route->hasValidDomains())
                    && !isset($allocatedRoutes[$route->getHttpMethod().$route->getRouterPattern()])) {
                    $allocatedRoutes[$route->getHttpMethod().$route->getRouterPattern()] = true;
                } else {
                    unset($this->routes[$index]);
                }
            }
        }

        return $this;
    }

    public function populateRoutes(App $app)
    {
        $this->weighRoutes();
        if (count($this->routes) > 0) {
            foreach ($this->getRoutes() as $route) {
                $app = $route->populateRoute($app);
            }
        }

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
