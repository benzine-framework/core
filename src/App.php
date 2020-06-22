<?php

namespace Benzine;

use Benzine\ORM\Connection\Databases;
use Benzine\ORM\Laminator;
use Benzine\Services\ConfigurationService;
use Benzine\Services\EnvironmentService;
use Benzine\Services\SessionService;
use Benzine\Twig\Extensions;
use Cache\Adapter\Apc\ApcCachePool;
use Cache\Adapter\Apcu\ApcuCachePool;
use Cache\Adapter\Chain\CachePoolChain;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Adapter\Redis\RedisCachePool;
use DebugBar\Bridge\MonologCollector;
use DebugBar\DebugBar;
use DebugBar\StandardDebugBar;
use DI\Container;
use DI\ContainerBuilder;
use Faker\Factory as FakerFactory;
use Faker\Provider;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim;
use Slim\Factory\AppFactory;
use Twig;
use Twig\Loader\FilesystemLoader;

class App
{
    public const DEFAULT_TIMEZONE = 'Europe/London';
    public static App $instance;

    protected EnvironmentService $environmentService;
    protected ConfigurationService $configurationService;
    protected \Slim\App $app;
    protected Logger $logger;
    protected bool $isSessionsEnabled = true;
    protected array $routePaths = [];
    protected array $viewPaths = [];
    protected bool $interrogateControllersComplete = false;

    private static bool $isInitialised = false;

    public function __construct()
    {
        // Configure Dependency Injector
        $container = $this->setupContainer();
        AppFactory::setContainer($container);

        $this->setup($container);

        // Configure Router
        $this->routePaths = [
            APP_ROOT.'/src/Routes.php',
            APP_ROOT.'/src/RoutesExtra.php',
        ];

        // Configure Slim
        $this->app = AppFactory::create();
        $this->app->add(Slim\Views\TwigMiddleware::createFromContainer($this->app));
        $this->app->addRoutingMiddleware();
        $errorMiddleware = $this->app->addErrorMiddleware(true, true, true);
    }

    protected function setup(ContainerInterface $container): void
    {
        $this->logger = $container->get(Logger::class);

        if ('cli' != php_sapi_name() && $this->isSessionsEnabled) {
            $session = $container->get(SessionService::class);
        }

        $this->setupMiddlewares($container);
        $this->viewPaths[] = APP_ROOT.'/views/';
        $this->viewPaths[] = APP_ROOT.'/src/Views/';
        $this->interrogateControllers();
    }

    /**
     * Get item from Dependency Injection.
     *
     * @return mixed
     */
    public function get(string $id)
    {
        return $this->getApp()->getContainer()->get($id);
    }

    public function setupContainer(): Container
    {
        $app = $this;
        $container =
            (new ContainerBuilder())
                ->useAutowiring(true)
                ->useAnnotations(true)
            ;
        if (file_exists('/app/cache')) {
            //    $container->enableCompilation("/app/cache");
        //    $container->writeProxiesToFile(true, "/app/cache/injection-proxies");
        }
        $container = $container->build();

        $container->set(Slim\Views\Twig::class, function (ContainerInterface $container) {
            foreach ($this->viewPaths as $i => $viewLocation) {
                if (!file_exists($viewLocation) || !is_dir($viewLocation)) {
                    unset($this->viewPaths[$i]);
                }
            }
            $settings = ['cache' => APP_ROOT.'/cache/twig'];
            $loader = new FilesystemLoader();

            foreach ($this->viewPaths as $path) {
                $loader->addPath($path);
            }

            $twig = new Slim\Views\Twig($loader, $settings);

            $twig->addExtension(new Extensions\ArrayUniqueTwigExtension());
            $twig->addExtension(new Extensions\FilterAlphanumericOnlyTwigExtension());

            // Add coding string transform filters (ie: camel_case to StudlyCaps)
            $twig->addExtension(new Extensions\TransformExtension());

            // Add pluralisation/depluralisation support with singularize/pluralize filters
            $twig->addExtension(new Extensions\InflectionExtension());

            // Added Twig_Extension_Debug to enable twig dump() etc.
            $twig->addExtension(new Twig\Extension\DebugExtension());

            $twig->offsetSet('app_name', APP_NAME);
            $twig->offsetSet('year', date('Y'));

            return $twig;
        });
        $container->set('view', function (ContainerInterface $container) {
            return $container->get(Slim\Views\Twig::class);
        });

        $container->set(EnvironmentService::class, function (ContainerInterface $container) {
            return new EnvironmentService();
        });

        $container->set(ConfigurationService::class, function (ContainerInterface $container) use ($app) {
            return new ConfigurationService(
                $app,
                $container->get(EnvironmentService::class)
            );
        });

        $container->set(\Faker\Generator::class, function (ContainerInterface $c) {
            $faker = FakerFactory::create();
            $faker->addProvider(new Provider\Base($faker));
            $faker->addProvider(new Provider\DateTime($faker));
            $faker->addProvider(new Provider\Lorem($faker));
            $faker->addProvider(new Provider\Internet($faker));
            $faker->addProvider(new Provider\Payment($faker));
            $faker->addProvider(new Provider\en_US\Person($faker));
            $faker->addProvider(new Provider\en_US\Address($faker));
            $faker->addProvider(new Provider\en_US\PhoneNumber($faker));
            $faker->addProvider(new Provider\en_US\Company($faker));

            return $faker;
        });
        $container->set(CachePoolChain::class, function (ContainerInterface $c) {
            $caches = [];

            // If apc/apcu present, add it to the pool
            if (function_exists('apcu_add')) {
                $caches[] = new ApcuCachePool();
            } elseif (function_exists('apc_add')) {
                $caches[] = new ApcCachePool();
            }

            // If Redis is configured, add it to the pool.
            $caches[] = new RedisCachePool($c->get(\Redis::class));
            $caches[] = new ArrayCachePool();

            return new CachePoolChain($caches);
        });

        $container->set('MonologFormatter', function (ContainerInterface $c) {
            /** @var Services\EnvironmentService $environment */
            $environment = $c->get(Services\EnvironmentService::class);

            return
            new LineFormatter(
            // the default output format is "[%datetime%] %channel%.%level_name%: %message% %context% %extra%"
                $environment->get('MONOLOG_FORMAT', '[%datetime%] %channel%.%level_name%: %message% %context% %extra%')."\n",
                'Y n j, g:i a'
            );
        });

        $container->set(Logger::class, function (ContainerInterface $c) {
            /** @var ConfigurationService $configuration */
            $configuration = $c->get(ConfigurationService::class);

            $monolog = new Logger($configuration->get(ConfigurationService::KEY_APP_NAME));
            $monolog->pushHandler(new ErrorLogHandler(), Logger::DEBUG);
            $monolog->pushProcessor(new PsrLogMessageProcessor());

            return $monolog;
        });

        //$container->set(DebugBar::class, function (ContainerInterface $container) {
        //    $debugBar = new StandardDebugBar();
        //    /** @var Logger $logger */
        //    $logger = $container->get(Logger::class);
        //    $debugBar->addCollector(new MonologCollector($logger));
//
//            return $debugBar;
//        });

        $container->set(\Middlewares\Debugbar::class, function (ContainerInterface $container) {
            $debugBar = $container->get(DebugBar::class);

            return new \Middlewares\Debugbar($debugBar);
        });

        $container->set(\Redis::class, function (ContainerInterface $container) {
            $environmentService = $container->get(EnvironmentService::class);

            $redis = new \Redis();
            $redis->connect(
                $environmentService->get('REDIS_HOST', 'redis'),
                $environmentService->get('REDIS_PORT', 6379)
            );

            return $redis;
        });

        $container->set(SessionService::class, function (ContainerInterface $container) {
            return new SessionService(
                $container->get(\Redis::class)
            );
        });

        $container->set(Databases::class, function (ContainerInterface $container) {
            return new Databases(
                $container->get(ConfigurationService::class)
            );
        });
        $container->set(Laminator::class, function (ContainerInterface $container) {
            return new Laminator(
                APP_ROOT,
                $container->get(ConfigurationService::class),
                $container->get(Databases::class)
            );
        });

        /** @var Services\EnvironmentService $environmentService */
        $environmentService = $container->get(Services\EnvironmentService::class);
        if ($environmentService->has('TIMEZONE')) {
            date_default_timezone_set($environmentService->get('TIMEZONE'));
        } elseif (file_exists('/etc/timezone')) {
            date_default_timezone_set(trim(file_get_contents('/etc/timezone')));
        } else {
            date_default_timezone_set(self::DEFAULT_TIMEZONE);
        }

        $debugBar = $container->get(DebugBar::class);

        return $container;
    }

    public function setupMiddlewares(ContainerInterface $container): void
    {
        // Middlewares
        //$this->app->add($container->get(Middleware\EnvironmentHeadersOnResponse::class));
        //$this->app->add($container->get(\Middlewares\ContentLength::class));
        //$this->app->add($container->get(\Middlewares\Debugbar::class));
        //$this->app->add($container->get(\Middlewares\Geolocation::class));
        //$this->app->add($container->get(\Middlewares\TrailingSlash::class));
        //$this->app->add($container->get(Middleware\JSONResponseLinter::class));
        //$this->app->add($container->get(\Middlewares\Whoops::class));
        //$this->app->add($container->get(\Middlewares\Minifier::class));
        //$this->app->add($container->get(\Middlewares\GzipEncoder::class));
    }

    /**
     * @param mixed $doNotUseStaticInstance
     *
     * @return self
     */
    public static function Instance(array $options = [])
    {
        if (!self::$isInitialised) {
            $calledClass = get_called_class();
            self::$instance = new $calledClass($options);
        }

        return self::$instance;
    }

    public function getApp()
    {
        return $this->app;
    }

    public function addRoutePath($path): self
    {
        if (file_exists($path)) {
            $this->routePaths[] = $path;
        }

        return $this;
    }

    public function clearRoutePaths(): self
    {
        $this->routePaths = [];

        return $this;
    }

    public function addViewPath($path)
    {
        if (file_exists($path)) {
            $this->viewPaths[] = $path;
        }

        return $this;
    }

    public static function Log(int $level = Logger::DEBUG, $message)
    {
        return self::Instance()
            ->getContainer()
            ->get(Logger::class)
            ->log($level, ($message instanceof \Exception) ? $message->__toString() : $message)
        ;
    }

    public function loadAllRoutes()
    {
        $app = $this->getApp();
        foreach ($this->routePaths as $path) {
            if (file_exists($path)) {
                include $path;
            }
        }
        Router\Router::Instance()->populateRoutes($app);

        return $this;
    }

    public function runHttp(): void
    {
        $this->app->run();
    }

    protected function interrogateControllers()
    {
        if ($this->interrogateControllersComplete) {
            return;
        }
        $this->interrogateControllersComplete = true;

        $controllerPaths = [
            APP_ROOT.'/src/Controllers',
        ];

        foreach ($controllerPaths as $controllerPath) {
            //$this->logger->debug("Route Discovery - {$controllerPath}");
            if (file_exists($controllerPath)) {
                foreach (new \DirectoryIterator($controllerPath) as $controllerFile) {
                    if (!$controllerFile->isDot() && $controllerFile->isFile() && $controllerFile->isReadable()) {
                        //$this->logger->debug(" >  {$controllerFile->getPathname()}");
                        $appClass = new \ReflectionClass(get_called_class());
                        $expectedClasses = [
                            $appClass->getNamespaceName().'\\Controllers\\'.str_replace('.php', '', $controllerFile->getFilename()),
                            '⌬\\Controllers\\'.str_replace('.php', '', $controllerFile->getFilename()),
                        ];
                        foreach ($expectedClasses as $expectedClass) {
                            //$this->logger->debug("  > {$expectedClass}");
                            if (class_exists($expectedClass)) {
                                $rc = new \ReflectionClass($expectedClass);
                                if (!$rc->isAbstract()) {
                                    foreach ($rc->getMethods() as $method) {
                                        /** @var \ReflectionMethod $method */
                                        if (true || ResponseInterface::class == ($method->getReturnType() instanceof \ReflectionType ? $method->getReturnType()->getName() : null)) {
                                            $docBlock = $method->getDocComment();
                                            foreach (explode("\n", $docBlock) as $docBlockRow) {
                                                if (false === stripos($docBlockRow, '@route')) {
                                                    continue;
                                                }
                                                //$this->logger->debug("   > fff {$docBlockRow}");

                                                $route = trim(substr(
                                                    $docBlockRow,
                                                    (stripos($docBlockRow, '@route') + strlen('@route'))
                                                ));
                                                //$this->logger->debug("   > Route {$route}");

                                                //\Kint::dump($route);

                                                @list($httpMethods, $path, $extra) = explode(' ', $route, 3);
                                                //\Kint::dump($httpMethods, $path, $extra);exit;
                                                $httpMethods = explode(',', strtoupper($httpMethods));

                                                $options = [];
                                                $defaultOptions = [
                                                    'access' => Router\Route::ACCESS_PUBLIC,
                                                    'weight' => 100,
                                                ];
                                                if (isset($extra)) {
                                                    foreach (explode(' ', $extra) as $item) {
                                                        @list($extraK, $extraV) = explode('=', $item, 2);
                                                        if (!isset($extraV)) {
                                                            $extraV = true;
                                                        }
                                                        $options[$extraK] = $extraV;
                                                    }
                                                }
                                                $options = array_merge($defaultOptions, $options);
                                                foreach ($httpMethods as $httpMethod) {
                                                    //$this->logger->debug("    > Adding {$path} to router");

                                                    $newRoute = Router\Route::Factory()
                                                        ->setHttpMethod($httpMethod)
                                                        ->setRouterPattern('/'.ltrim($path, '/'))
                                                        ->setCallback($method->class.':'.$method->name)
                                                    ;

                                                    foreach ($options as $key => $value) {
                                                        $keyMethod = 'set'.ucfirst($key);
                                                        if (method_exists($newRoute, $keyMethod)) {
                                                            $newRoute->{$keyMethod}($value);
                                                        } else {
                                                            $newRoute->setArgument($key, $value);
                                                        }
                                                    }

                                                    Router\Router::Instance()->addRoute($newRoute);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        Router\Router::Instance()->weighRoutes();
    }
}
