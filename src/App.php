<?php

namespace Benzine;

use Benzine\ORM\Connection\Databases;
use Benzine\ORM\Laminator;
use Benzine\Redis\Redis;
use Benzine\Router\Route;
use Benzine\Router\Router;
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
use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DataCollector\RequestDataCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar;
use DI\Container;
use DI\ContainerBuilder;
use Faker\Factory as FakerFactory;
use Faker\Provider;
use Middlewares\TrailingSlash;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim;
use Slim\Factory\AppFactory;
use Symfony\Bridge\Twig\Extension as SymfonyTwigExtensions;
use Symfony\Component\Translation;
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
    protected DebugBar $debugBar;
    protected Router $router;
    protected bool $isSessionsEnabled = true;
    protected bool $interrogateControllersComplete = false;
    private array $routePaths = [];
    private array $viewPaths = [];
    private string $cachePath = '/cache';
    private array $supportedLanguages = ['en_US'];

    private static bool $isInitialised = false;

    public function __construct()
    {
        // Configure Dependency Injector
        $container = $this->setupContainer();
        $this->logger = $container->get(Logger::class);
        $this->debugBar = $container->get(DebugBar::class);
        AppFactory::setContainer($container);

        if ('cli' != php_sapi_name() && $this->isSessionsEnabled) {
            $session = $container->get(SessionService::class);
        }

        $this->viewPaths[] = APP_ROOT.'/views/';
        $this->viewPaths[] = APP_ROOT.'/src/Views/';

        // Configure Router
        $this->routePaths = [
            APP_ROOT.'/src/Routes.php',
            APP_ROOT.'/src/RoutesExtra.php',
        ];

        // Configure Slim
        $this->app = AppFactory::create();
        $this->app->add(Slim\Views\TwigMiddleware::createFromContainer($this->app));
        $this->app->addRoutingMiddleware();

        $this->setupMiddlewares($container);

        $errorMiddleware = $this->app->addErrorMiddleware(true, true, true, $this->logger);

        $this->debugBar['time']->startMeasure('interrogateTranslations', 'Time to interrogate translation files');
        $this->interrogateTranslations();
        $this->debugBar['time']->stopMeasure('interrogateTranslations');

        $this->debugBar['time']->startMeasure('interrogateControllers', 'Time to interrogate controllers for routes');
        $this->interrogateControllers();
        $this->debugBar['time']->stopMeasure('interrogateControllers');

        $this->logger->debug(sprintf('Bootstrap complete in %sms', number_format((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2)));
    }

    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    public function setCachePath(string $cachePath): App
    {
        $this->cachePath = $cachePath;

        return $this;
    }

    /**
     * Get item from Dependency Injection.
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
        if (file_exists($this->getCachePath())) {
            //    $container->enableCompilation($this->getCachePath());
            //    $container->writeProxiesToFile(true, "{$this->getCachePath()}/injection-proxies");
        }
        $container = $container->build();

        $container->set(Slim\Views\Twig::class, function (
            EnvironmentService $environmentService,
            SessionService $sessionService,
            Translation\Translator $translator
        ) {
            foreach ($this->viewPaths as $i => $viewLocation) {
                if (!file_exists($viewLocation) || !is_dir($viewLocation)) {
                    unset($this->viewPaths[$i]);
                }
            }

            $twigCachePath = "{$this->getCachePath()}/twig";
            $twigSettings = [];

            if ($environmentService->has('TWIG_CACHE') && 'on' == strtolower($environmentService->get('TWIG_CACHE'))) {
                $twigSettings['cache'] = $twigCachePath;
            }

            if (!file_exists($twigCachePath)) {
                @mkdir($twigCachePath, 0777, true);
            }

            $loader = new FilesystemLoader();

            foreach ($this->viewPaths as $path) {
                $loader->addPath($path);
            }

            $twig = new Slim\Views\Twig($loader, $twigSettings);

            $twig->addExtension(new Extensions\ArrayUniqueTwigExtension());
            $twig->addExtension(new Extensions\FilterAlphanumericOnlyTwigExtension());

            // Add coding string transform filters (ie: camel_case to StudlyCaps)
            $twig->addExtension(new Extensions\TransformExtension());

            // Add pluralisation/depluralisation support with singularize/pluralize filters
            $twig->addExtension(new Extensions\InflectionExtension());

            // Added Twig_Extension_Debug to enable twig dump() etc.
            $twig->addExtension(new Twig\Extension\DebugExtension());

            // Add Twig extension to integrate Kint
            $twig->addExtension(new \Kint\Twig\TwigExtension());

            // Add Twig Translate from symfony/twig-bridge
            $selectedLanguage = $sessionService->has('Language') ? $sessionService->get('Language') : 'en_US';
            $twig->addExtension(new SymfonyTwigExtensions\TranslationExtension($translator));
            $twig->offsetSet('language', $translator->trans($selectedLanguage));

            // Set some default parameters
            $twig->offsetSet('app_name', APP_NAME);
            $twig->offsetSet('year', date('Y'));
            $twig->offsetSet('session', $sessionService);

            return $twig;
        });

        // This is required as some plugins for Slim expect there to be a twig available as "view"
        $container->set('view', function (Slim\Views\Twig $twig) {
            return $twig;
        });

        $container->set(Translation\Translator::class, function (SessionService $sessionService) {
            $selectedLanguage = $sessionService->has('Language') ? $sessionService->get('Language') : 'en_US';

            $translator = new Translation\Translator($selectedLanguage);

            // set default locale
            $translator->setFallbackLocales(['en_US']);

            // build the yaml loader
            $yamlLoader = new Translation\Loader\YamlFileLoader();

            // add the loader to the translator
            $translator->addLoader('yaml', $yamlLoader);

            // add some resources to the translator
            $translator->addResource('yaml', APP_ROOT."/src/Strings/{$selectedLanguage}.yaml", $selectedLanguage);

            return $translator;
        });

        $container->set(ConfigurationService::class, function (EnvironmentService $environmentService) use ($app) {
            return new ConfigurationService(
                $app,
                $environmentService
            );
        });

        $container->set(\Faker\Generator::class, function () {
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

        $container->set(CachePoolChain::class, function (\Redis $redis) {
            $caches = [];

            // If apc/apcu present, add it to the pool
            if (function_exists('apcu_add')) {
                $caches[] = new ApcuCachePool();
            } elseif (function_exists('apc_add')) {
                $caches[] = new ApcCachePool();
            }

            // If Redis is configured, add it to the pool.
            $caches[] = new RedisCachePool($redis);
            $caches[] = new ArrayCachePool();

            return new CachePoolChain($caches);
        });

        $container->set('MonologFormatter', function (EnvironmentService $environmentService) {
            return new LineFormatter(
            // the default output format is "[%datetime%] %channel%.%level_name%: %message% %context% %extra%"
                $environmentService->get('MONOLOG_FORMAT', '[%datetime%] %channel%.%level_name%: %message% %context% %extra%')."\n",
                'Y n j, g:i a'
            );
        });

        $container->set(Logger::class, function (ConfigurationService $configurationService) {
            $monolog = new Logger($configurationService->get(ConfigurationService::KEY_APP_NAME));
            $monolog->pushHandler(new ErrorLogHandler(), Logger::DEBUG);
            $monolog->pushProcessor(new PsrLogMessageProcessor());

            return $monolog;
        });

        $container->set(\Redis::class, function (EnvironmentService $environmentService) {
            $redis = new Redis();
            $redis->connect(
                $environmentService->get('REDIS_HOST', 'redis'),
                $environmentService->get('REDIS_PORT', 6379)
            );

            return $redis;
        });

        $container->set(Laminator::class, function (ConfigurationService $configurationService, Databases $databases) {
            return new Laminator(
                APP_ROOT,
                $configurationService,
                $databases
            );
        });

        $container->set(TrailingSlash::class, function () {
            return (new TrailingSlash())->redirect();
        });

        $container->set(DebugBar::class, function (Logger $logger) {
            return (new DebugBar())
                ->addCollector(new PhpInfoCollector())
                ->addCollector(new MessagesCollector())
                //->addCollector(new RequestDataCollector())
                ->addCollector(new TimeDataCollector())
                ->addCollector(new MemoryCollector())
                ->addCollector(new ExceptionsCollector())
                ->addCollector(new MonologCollector($logger, Logger::DEBUG))
            ;
        });

        $container->set(\Middlewares\Debugbar::class, function (DebugBar $debugBar) {
            return new \Middlewares\Debugbar(
                $debugBar
            );
        });

        $this->environmentService = $container->get(Services\EnvironmentService::class);
        if ($this->environmentService->has('TIMEZONE')) {
            date_default_timezone_set($this->environmentService->get('TIMEZONE'));
        } elseif (file_exists('/etc/timezone')) {
            date_default_timezone_set(trim(file_get_contents('/etc/timezone')));
        } else {
            date_default_timezone_set(self::DEFAULT_TIMEZONE);
        }

        $this->router = $container->get(Router::class);

        return $container;
    }

    public function setupMiddlewares(ContainerInterface $container): void
    {
        // Middlewares
        //$this->app->add($container->get(\Middlewares\Geolocation::class));
        $this->app->add($container->get(\Middlewares\TrailingSlash::class));
        //$this->app->add($container->get(\Middlewares\Whoops::class));
        //$this->app->add($container->get(\Middlewares\Minifier::class));
        //$this->app->add($container->get(\Middlewares\GzipEncoder::class));
        $this->app->add($container->get(\Middlewares\ContentLength::class));
    }

    /**
     * @return self
     */
    public static function Instance(array $options = [])
    {
        if (!self::$isInitialised) {
            $calledClass = get_called_class();
            /** @var App $tempApp */
            $tempApp = new $calledClass($options);
            /** @var ConfigurationService $config */
            $config = $tempApp->get(ConfigurationService::class);
            $configCoreClass = $config->getCore();
            if ($configCoreClass != get_called_class()) {
                self::$instance = new $configCoreClass($options);
            } else {
                self::$instance = $tempApp;
            }
        }

        return self::$instance;
    }

    /**
     * Convenience function to get objects out of the Dependency Injection Container.
     */
    public static function DI(string $key)
    {
        return self::Instance()->get($key);
    }

    public function getApp(): Slim\App
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
            ->getLogger()
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
        $this->router->populateRoutes($app);

        return $this;
    }

    public function runHttp(): void
    {
        $this->debugBar['time']->startMeasure('runHTTP', 'HTTP runtime');
        $this->app->run();
        if ($this->debugBar['time']->hasStartedMeasure('runHTTP')) {
            $this->debugBar['time']->stopMeasure('runHTTP');
        }
    }

    /**
     * @return string[]
     */
    public function getSupportedLanguages(): array
    {
        return $this->supportedLanguages;
    }

    /**
     * @param string[] $supportedLanguages
     */
    public function setSupportedLanguages(array $supportedLanguages): self
    {
        $this->supportedLanguages = $supportedLanguages;

        return $this;
    }

    public function addSupportedLanguage(string $supportedLanguage): self
    {
        $this->supportedLanguages[] = $supportedLanguage;
        $this->supportedLanguages = array_unique($this->supportedLanguages);

        return $this;
    }

    public function isSupportedLanguage(string $supportedLanguage): bool
    {
        return in_array($supportedLanguage, $this->supportedLanguages, true);
    }

    /**
     * @return mixed|Router
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * @param mixed|Router $router
     *
     * @return App
     */
    public function setRouter($router)
    {
        $this->router = $router;

        return $this;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    protected function interrogateTranslations(): void
    {
        foreach (new \DirectoryIterator(APP_ROOT.'/src/Strings') as $translationFile) {
            if ('yaml' == $translationFile->getExtension()) {
                $languageName = substr($translationFile->getBasename(), 0, -5);
                $this->addSupportedLanguage($languageName);
            }
        }
    }

    protected function interrogateControllers(): void
    {
        if ($this->interrogateControllersComplete) {
            return;
        }
        $this->interrogateControllersComplete = true;

        if ($this->environmentService->has('USE_ROUTE_CACHE')
            && 'yes' == strtolower($this->environmentService->get('USE_ROUTE_CACHE'))
            && $this->router->loadCache()
        ) {
            return;
        }

        $controllerPaths = [
            APP_ROOT.'/src/Controllers',
        ];

        foreach ($controllerPaths as $controllerPath) {
            if (file_exists($controllerPath)) {
                foreach (new \DirectoryIterator($controllerPath) as $controllerFile) {
                    if (!$controllerFile->isDot() && $controllerFile->isFile() && $controllerFile->isReadable()) {
                        $appClass = new \ReflectionClass(get_called_class());
                        $expectedClasses = [
                            $appClass->getNamespaceName().'\\Controllers\\'.str_replace('.php', '', $controllerFile->getFilename()),
                            'Benzine\\Controllers\\'.str_replace('.php', '', $controllerFile->getFilename()),
                        ];
                        foreach ($expectedClasses as $expectedClass) {
                            if (class_exists($expectedClass)) {
                                $rc = new \ReflectionClass($expectedClass);
                                if (!$rc->isAbstract()) {
                                    foreach ($rc->getMethods() as $method) {
                                        /** @var \ReflectionMethod $method */
                                        if (true || ResponseInterface::class == ($method->getReturnType() instanceof \ReflectionType ? $method->getReturnType()->getName() : null)) {
                                            $docBlock = $method->getDocComment();
                                            $newRoute = new Route($this->logger);
                                            foreach (explode("\n", $docBlock) as $docBlockRow) {
                                                if (false !== stripos($docBlockRow, '@route')) {
                                                    $route = trim(substr(
                                                        $docBlockRow,
                                                        (stripos($docBlockRow, '@route') + strlen('@route'))
                                                    ));

                                                    @list($httpMethods, $path, $extra) = explode(' ', $route, 3);
                                                    $httpMethods = explode(',', strtoupper($httpMethods));

                                                    $options = [];
                                                    $defaultOptions = [
                                                        'access' => Route::ACCESS_PUBLIC,
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
                                                        $newRoute
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

                                                        $this->router->addRoute($newRoute);
                                                    }
                                                } elseif (false !== stripos($docBlockRow, '@domains')) {
                                                    $domains = explode(' ', trim(substr(
                                                        $docBlockRow,
                                                        (stripos($docBlockRow, '@domains') + strlen('@domains'))
                                                    )));
                                                    foreach ($domains as $domain) {
                                                        $newRoute->addValidDomain($domain);
                                                    }
                                                    $newRoute->setWeight($newRoute->getWeight() - 10);
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

        $this->router
            ->weighRoutes()
            ->cache()
        ;
    }
}
