<?php

namespace ⌬;

use Cache\Adapter\Apc\ApcCachePool;
use Cache\Adapter\Apcu\ApcuCachePool;
use Cache\Adapter\Chain\CachePoolChain;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Adapter\Predis\PredisCachePool;
use DebugBar\Bridge\MonologCollector;
use DebugBar\DebugBar;
use DebugBar\StandardDebugBar;
use Faker\Factory as FakerFactory;
use Faker\Provider;
use Predis\Client as Predis;
use Psr\Http\Message\ResponseInterface;
use SebastianBergmann\Diff\Differ;
use Slim;
use ⌬\Configuration\Configuration;
use ⌬\Configuration\DatabaseConfig;
use ⌬\Database\Db;
use ⌬\Database\Profiler;
use ⌬\HTML\Twig\Extensions;
use ⌬\Log\Logger;
use ⌬\Redis\RedisLuaScripts\SetIfHigherLuaScript;
use ⌬\Services\EnvironmentService;

class ⌬
{
    public const DEFAULT_TIMEZONE = 'Europe/London';

    /** @var ⌬ */
    public static $instance;

    /** @var Configuration */
    protected $configuration;
    /** @var \Slim\App */
    protected $app;
    /** @var Container\Container */
    protected $container;
    /** @var Log\Logger */
    protected $logger;

    protected $isSessionsEnabled = true;

    protected $containerAliases = [
        'view' => Slim\Views\Twig::class,
        'DatabaseInstance' => DbConfig::class,
        'Differ' => Differ::class,
        'HttpClient' => \GuzzleHttp\Client::class,
        'Faker' => \Faker\Generator::class,
        'Environment' => EnvironmentService::class,
        'Redis' => Redis\Redis::class,
        'Monolog' => Log\Logger::class,
        'Gone\AppCore\Logger' => Log\Logger::class,
        'Cache' => CachePoolChain::class,
    ];

    protected $routePaths = [];

    protected $viewPaths = [];

    protected $optionsDefaults = [];

    protected $interrogateControllersComplete = false;

    public function __construct($options = [])
    {
        $this->configuration = new Configuration();

        $this->routePaths = [
            $this->configuration->get(Configuration::KEY_APP_ROOT).'/src/Routes.php',
            $this->configuration->get(Configuration::KEY_APP_ROOT).'/src/RoutesExtra.php',
        ];

        $options = array_merge($this->optionsDefaults, $options);

        if (isset($options['config'])) {
            if (is_string($options['config'])) {
                $configRealpath = $options['config'];
                if (!file_exists($configRealpath)) {
                    throw new Exceptions\BenzineConfigurationException("Cant find {$configRealpath}.");
                }
                $this->configuration->configureFromYaml($options['config']);
            }
        }
        $this->setup();
    }

    public function setup(): void
    {
        // Create Slim app
        $this->app = new \Slim\App(
            new Container\Container([
                'settings' => [
                    'debug' => $this->getConfiguration()->get(Configuration::KEY_DEBUG_ENABLE),
                    'displayErrorDetails' => $this->getConfiguration()->get(Configuration::KEY_DEBUG_ENABLE),
                    'determineRouteBeforeAppMiddleware' => true,
                ],
            ])
        );

        // Fetch DI Container
        $this->container = $this->app->getContainer();
        // @todo remove this depenency on getting the container from Slim.
        //$this->container = new Container\Container();

        $this->populateContainerAliases($this->container);

        $this->setupDependencies();

        $this->logger = $this->getContainer()->get(Log\Logger::class);

        if (file_exists($this->configuration->get(Configuration::KEY_APP_ROOT).'/src/AppContainer.php')) {
            require $this->configuration->get(Configuration::KEY_APP_ROOT).'/src/AppContainer.php';
        }
        if (file_exists($this->configuration->get(Configuration::KEY_APP_ROOT).'/src/AppContainerExtra.php')) {
            require $this->configuration->get(Configuration::KEY_APP_ROOT).'/src/AppContainerExtra.php';
        }

        $this->addRoutePathsRecursively($this->configuration->get(Configuration::KEY_APP_ROOT).'/src/Routes');

        if ('cli' != php_sapi_name() && $this->isSessionsEnabled) {
            $session = $this->getContainer()->get(Session\Session::class);
        }

        $this->setupMiddlewares();

        if(class_exists(Controllers\Controller::class)) {
            $this->addViewPath($this->getContainer()->get(Controllers\Controller::class)->getViewsPath());
            if (file_exists($this->configuration->get(Configuration::KEY_APP_ROOT) . '/views/')) {
                $this->addViewPath($this->configuration->get(Configuration::KEY_APP_ROOT) . '/views/');
            }
            if (file_exists($this->configuration->get(Configuration::KEY_APP_ROOT) . '/src/Views')) {
                $this->addViewPath($this->configuration->get(Configuration::KEY_APP_ROOT) . '/src/Views');
            }

            $this->interrogateControllers();
        }
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function getLogger(): Log\Logger
    {
        return $this->logger;
    }

    public function setupDependencies(): void
    {
        // add PSR-15 support shim
        $this->container['callableResolver'] = function ($container) {
            return new \Bnf\Slim3Psr15\CallableResolver($container);
        };

        // Register Twig View helper
        $this->container[Slim\Views\Twig::class] = function ($c) {
            foreach ($this->viewPaths as $i => $viewLocation) {
                if (!file_exists($viewLocation) || !is_dir($viewLocation)) {
                    unset($this->viewPaths[$i]);
                }
            }

            $view = new \Slim\Views\Twig(
                $this->viewPaths,
                [
                    'cache' => false,
                    'debug' => true,
                ]
            );

            // Instantiate and add Slim specific extension
            $view->addExtension(
                new Slim\Views\TwigExtension(
                    $c['router'],
                    $c['request']->getUri()
                )
            );

            $view->addExtension(new Extensions\ArrayUniqueTwigExtension());
            $view->addExtension(new Extensions\FilterAlphanumericOnlyTwigExtension());

            // Add coding string transform filters (ie: camel_case to StudlyCaps)
            $view->addExtension(new Extensions\TransformExtension());

            // Add pluralisation/depluralisation support with singularize/pluralize filters
            $view->addExtension(new Extensions\InflectionExtension());

            // Added Twig_Extension_Debug to enable twig dump() etc.
            $view->addExtension(new \Twig_Extension_Debug());
            $view->addExtension(new \Twig_Extensions_Extension_Date());
            $view->addExtension(new \Twig_Extensions_Extension_Text());

            $view->offsetSet('app_name', $this->configuration->get(Configuration::KEY_APP_NAME));
            $view->offsetSet('year', date('Y'));

            return $view;
        };

        $this->container[Configuration::class] = function (Slim\Container $c) {
            $benzineYamlFile = '/app/.benzine.yml'; // @todo this shouldn't be hardcoded into /app
            return Configuration::InitFromFile($benzineYamlFile);
        };

        $this->container[Db::class] = function (Slim\Container $c) {
            return new Db($c->get(DatabaseConfig::class));
        };

        $this->container[DatabaseConfig::class] = function (Slim\Container $c) {
            /** @var Configuration $configuration */
            $configuration = $c->get(Configuration::class);
            $dbConfig = new DatabaseConfig();
            foreach ($configuration->getArray('benzine/databases') as $dbName => $database) {
                $dbConfig->set($dbName, [
                    'driver' => $database['driver'] ?? 'Pdo_Mysql',
                    'hostname' => gethostbyname($database['host']),
                    'port' => $database['port'] ?? 3306,
                    'username' => $database['username'],
                    'password' => $database['password'],
                    'database' => $database['database'],
                    'charset' => $database['charset'] ?? 'UTF8',
                ]);
            }

            return $dbConfig;
        };

        $this->container[\Faker\Generator::class] = function (Slim\Container $c) {
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
        };

        $this->container[\GuzzleHttp\Client::class] = function (Slim\Container $c) {
            return new \GuzzleHttp\Client([
                // You can set any number of default request options.
                'timeout' => 2.0,
            ]);
        };

        $this->container[Services\EnvironmentService::class] = function (Slim\Container $c) {
            return new Services\EnvironmentService();
        };

        $this->container[Predis::class] = function (Slim\Container $c) {
            /** @var EnvironmentService $environmentService */
            $environmentService = $c->get(EnvironmentService::class);
            if ($environmentService->isSet('REDIS_HOST')) {
                $redisMasterHosts = explode(',', $environmentService->get('REDIS_HOST'));
            }
            if ($environmentService->isSet('REDIS_HOST_MASTER')) {
                $redisMasterHosts = explode(',', $environmentService->get('REDIS_HOST_MASTER'));
            }
            if ($environmentService->isSet('REDIS_HOST_SLAVE')) {
                $redisSlaveHosts = explode(',', $environmentService->get('REDIS_HOST_SLAVE'));
            }

            $options = [];

            $options['profile'] = function ($options) {
                $profile = $options->getDefault('profile');
                $profile->defineCommand('setifhigher', SetIfHigherLuaScript::class);

                return $profile;
            };

            return new Predis(
                $redisMasterHosts[0],
                $options
            );
        };

        $this->container[CachePoolChain::class] = function (Slim\Container $c) {
            $caches = [];

            // If apc/apcu present, add it to the pool
            if (function_exists('apcu_add')) {
                $caches[] = new ApcuCachePool();
            } elseif (function_exists('apc_add')) {
                $caches[] = new ApcCachePool();
            }

            // If Redis is configured, add it to the pool.
            $caches[] = new PredisCachePool($c->get(Redis\Redis::class));
            $caches[] = new ArrayCachePool();

            return new CachePoolChain($caches);
        };

        $this->container['MonologFormatter'] = function (Slim\Container $c) {
            /** @var Services\EnvironmentService $environment */
            $environment = $c->get(Services\EnvironmentService::class);

            return
            new LineFormatter(
            // the default output format is "[%datetime%] %channel%.%level_name%: %message% %context% %extra%"
                $environment->get('MONOLOG_FORMAT', '[%datetime%] %channel%.%level_name%: %message% %context% %extra%')."\n",
                'Y n j, g:i a'
            )
            ;
        };

        $this->container[\Monolog\Logger::class] = function (Slim\Container $c) {
            /** @var Configuration $configuration */
            $configuration = $c->get(Configuration::class);
            $appName = $configuration->get(Configuration::KEY_APP_NAME);

            return new \Monolog\Logger($appName);
        };

        $this->container[DebugBar::class] = function (Slim\Container $container) {
            $debugBar = new StandardDebugBar();
            /** @var Logger $logger */
            $logger = $container->get(Log\Logger::class);
            /** @var \Monolog\Logger $monolog */
            $monolog = $logger->getMonolog();
            $debugBar->addCollector(new MonologCollector($monolog));

            return $debugBar;
        };

        $this->container[\Middlewares\Debugbar::class] = function (Slim\Container $container) {
            $debugBar = $container->get(DebugBar::class);

            return new \Middlewares\Debugbar($debugBar);
        };

        $this->container[Session\Session::class] = function (Slim\Container $container) {
            return Session\Session::start($container->get(Redis\Redis::class));
        };

        $this->container[Differ::class] = function (Slim\Container $container) {
            return new Differ();
        };

        $this->container[Profiler\Profiler::class] = function (Slim\Container $container) {
            return new Profiler\Profiler($container->get(Log\Logger::class));
        };

        /** @var Services\EnvironmentService $environmentService */
        $environmentService = $this->getContainer()->get(Services\EnvironmentService::class);
        if ($environmentService->isSet('TIMEZONE')) {
            date_default_timezone_set($environmentService->get('TIMEZONE'));
        } else {
            date_default_timezone_set(self::DEFAULT_TIMEZONE);
        }

        $debugBar = $this->getContainer()->get(DebugBar::class);
    }

    public function setupMiddlewares(): void
    {
        // Middlewares
        //$this->app->add($this->container->get(Middleware\EnvironmentHeadersOnResponse::class));
        //#$this->app->add($this->container->get(\Middlewares\ContentType(["text/html", "application/json"])));
        $this->app->add($this->container->get(\Middlewares\Debugbar::class));
        //#$this->app->add($this->container->get(\Middlewares\Geolocation::class));
        //$this->app->add($this->container->get(\Middlewares\TrailingSlash::class));
        //$this->app->add($this->container->get(Middleware\JSONResponseLinter::class));
        //$this->app->add($this->container->get(\Middlewares\Whoops::class));
        //$this->app->add($this->container->get(\Middlewares\CssMinifier::class));
        //$this->app->add($this->container->get(\Middlewares\JsMinifier::class));
        //$this->app->add($this->container->get(\Middlewares\HtmlMinifier::class));
        //$this->app->add($this->container->get(\Middlewares\GzipEncoder::class));
    }

    /**
     * @param mixed $doNotUseStaticInstance
     *
     * @return self
     */
    public static function Instance(array $options = [])
    {
        if (!self::$instance) {
            $calledClass = get_called_class();
            self::$instance = new $calledClass($options);
        }

        $expectedClass = self::$instance->getConfiguration()->get(Configuration::KEY_CLASS);
        if (get_class(self::$instance) != $expectedClass) {
            self::$instance = new $expectedClass($options);
        }

        return self::$instance;
    }

    /**
     * @return Container\Container
     */
    public static function Container()
    {
        return self::Instance()->getContainer();
    }

    public function getContainer(): Container\Container
    {
        return $this->container;
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

    /**
     * @param $directory
     *
     * @return int number of Paths added
     */
    public function addRoutePathsRecursively($directory)
    {
        $count = 0;
        if (file_exists($directory)) {
            foreach (new \DirectoryIterator($directory) as $file) {
                if (!$file->isDot()) {
                    if ($file->isFile() && 'php' == $file->getExtension()) {
                        $this->addRoutePath($file->getRealPath());
                        ++$count;
                    } elseif ($file->isDir()) {
                        $count += $this->addRoutePathsRecursively($file->getRealPath());
                    }
                }
            }
        }

        return $count;
    }

    public function addViewPath($path)
    {
        if (file_exists($path)) {
            $this->viewPaths[] = $path;
        }

        return $this;
    }

    public function makeClean(): self
    {
        $this->setup();
        $this->loadAllRoutes();

        return $this;
    }

    public function populateContainerAliases(&$container)
    {
        foreach ($this->containerAliases as $alias => $class) {
            if ($alias != $class) {
                $container[$alias] = function (Slim\Container $c) use ($class) {
                    return $c->get($class);
                };
            }
        }
    }

    public static function Log(int $level = Logger::DEBUG, $message)
    {
        return self::Instance()
            ->getContainer()
            ->get(Log\Logger::class)
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

    public static function waitForMySQLToBeReady($connection = null)
    {
        if (!$connection) {
            /** @var DbConfig $configs */
            $dbConfig = self::Instance()->getContainer()->get(DatabaseConfig::class);
            $configs = $dbConfig->__toArray();

            if (isset($configs['Default'])) {
                $connection = $configs['Default'];
            } else {
                foreach ($configs as $option => $connection) {
                    self::waitForMySQLToBeReady($connection);
                }

                return;
            }
        }

        $ready = false;
        echo "Waiting for MySQL ({$connection['hostname']}:{$connection['port']}) to come up...";
        while (false == $ready) {
            $conn = @fsockopen($connection['hostname'], $connection['port']);
            if (is_resource($conn)) {
                fclose($conn);
                $ready = true;
            } else {
                echo '.';
                usleep(500000);
            }
        }
        echo " [DONE]\n";

        /** @var Services\EnvironmentService $environmentService */
        $environmentService = self::Container()->get(Services\EnvironmentService::class);

        $environmentService->rebuildEnvironmentVariables();
    }

    public function runHttp(): ResponseInterface
    {
        return $this->app->run();
    }

    protected function interrogateControllers()
    {
        if ($this->interrogateControllersComplete) {
            return;
        }
        $this->interrogateControllersComplete = true;

        $controllerPaths = [
            $this->getConfiguration()->get(Configuration::KEY_APP_ROOT).'/src/Controllers',
            $this->getConfiguration()->get(Configuration::KEY_APP_ROOT).'/vendor/benzine/benzine-controllers/src',
        ];
        foreach ($controllerPaths as $controllerPath) {
            //$this->logger->debug("Route Discovery - {$controllerPath}");
            if (file_exists($controllerPath)) {
                foreach (new \DirectoryIterator($controllerPath) as $controllerFile) {
                    if (!$controllerFile->isDot() && $controllerFile->isFile() && $controllerFile->isReadable()) {
                        //$this->logger->debug(" >  {$controllerFile->getPathname()}");
                        $appClass = new \ReflectionClass($this->getConfiguration()->get(Configuration::KEY_CLASS));
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
                                        if (1 == 1 || ResponseInterface::class == ($method->getReturnType() instanceof \ReflectionType ? $method->getReturnType()->getName() : null)) {
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
                                                        $keyMethod = "set{$key}";

                                                        if (method_exists($newRoute, $keyMethod)) {
                                                            $newRoute->{$keyMethod}($value);
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
