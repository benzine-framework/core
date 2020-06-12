<?php

namespace Benzine\Services;

class ConfigurationService
{
    public function __construct(array $config = null)
    {
        $this->config = $config;

        if (null === $this->config) {
            $this->config = [
                'benzine' => [
                    'application' => [
                        'name' => 'Benzine App',
                        'debug' => false,
                        'default_access' => 'public',
                        'timezone' => 'UTC',
                        'locale' => 'en_US.UTF-8',
                        'session_enabled' => true,
                        'time_start' => microtime(true),
                    ],
                    'logging' => [
                        'format_date' => 'Y-m-d H:i:s',
                        'format_message' => '%datetime% > %level_name% > %message% %context% %extra%',
                    ],
                ],
            ];
            $this->findConfigs();
        }

        $this->handleEnvvars();
    }

    public function __toArray(): array
    {
        return $this->arrayFlatten($this->config);
    }

    public static function Init(array $config): Configuration
    {
        return new Configuration($config);
    }

    public static function InitFromFile(string $filePath): Configuration
    {
        return self::Init(Yaml::parseFile($filePath));
    }

    public function dump(): array
    {
        return $this->__toArray();
    }

    public function set(string $key, $value): self
    {
        $scope = &$this->config;
        foreach (explode('/', strtolower($key)) as $keyBit) {
            $scope = &$scope[$keyBit];
        }
        $scope = $value;

        return $this;
    }

    public function has(string $key): bool
    {
        return false != $this->get($key, false)
            || (is_array($this->getArray($key)) && count($this->getArray($key)) >= 1);
    }

    public function get(string $key, $defaultValue = null)
    {
        $scope = $this->config;
        foreach (explode('/', strtolower($key)) as $keyBit) {
            $scope = &$scope[$keyBit];
        }

        if (is_array($scope)) {
            return trim(end($scope));
        }

        if (!$scope) {
            return $defaultValue;
        }

        return trim($scope);
    }

    public function getArray(string $key)
    {
        $scope = $this->config;
        foreach (explode('/', strtolower($key)) as $keyBit) {
            $scope = &$scope[$keyBit];
        }

        return $scope;
    }

    public function defineAs(string $defineTarget, string $key): self
    {
        if (!defined($defineTarget)) {
            define($defineTarget, $this->get($key));
        }

        return $this;
    }

    public function handleEnvvars(): void
    {
        $envvars = array_merge($_SERVER, $_ENV);

        array_walk_recursive($this->config, function (&$value, $key) use ($envvars) {
            foreach ($envvars as $envvar => $envvarValue) {
                if (is_array($envvarValue)) {
                    continue;
                }
                $value = str_replace("\${$envvar}", $envvarValue, $value);
            }
        });
    }

    public function findConfigs($currentDir = null): void
    {
        if (null == $currentDir) {
            $currentDir = dirname(realpath($_SERVER['SCRIPT_FILENAME']));
        }

        if (file_exists($currentDir.'/.benzine.yml')) {
            $this->configureFromYaml($currentDir.'/.benzine.yml');

            return;
        }

        $currentDirElem = explode(DIRECTORY_SEPARATOR, $currentDir);
        array_pop($currentDirElem);
        $this->findConfigs(implode(DIRECTORY_SEPARATOR, $currentDirElem));
    }

    public function configureFromYaml(string $file): self
    {
        $this->set('benzine/application/root', realpath(dirname($file)));
        //\Kint::dump($this->config, Yaml::parseFile($file));
        $this->config = array_merge_recursive($this->config, Yaml::parseFile($file));
        //$this->ksortRecursive($this->config);
        $this->process();

        return $this;
    }

    public function process(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', true == $this->get('benzine/application/debug'));
        ini_set('display_startup_errors', true == $this->get('benzine/application/debug'));
        date_default_timezone_set($this->get('benzine/application/timezone', 'UTC'));
        setlocale(LC_ALL, $this->get('benzine/application/locale'));

        $this
            ->defineAs('DEBUG', self::KEY_DEBUG_ENABLE)
            ->defineAs('APP_START', self::KEY_APP_START)
            ->defineAs('APP_ROOT', self::KEY_APP_ROOT)
            ->defineAs('DEFAULT_ROUTE_ACCESS_MODE', self::KEY_DEFAULT_ACCESS)
        ;
    }

    public function getDatabases(): DatabaseConfig
    {
        $dbConfig = new DatabaseConfig();
        foreach ($this->config['benzine']['databases'] as $name => $config) {
            $dbConfig->set($name, [
                'driver' => DatabaseConfig::DbTypeToDriver($config['type']),
                'hostname' => $config['host'],
                'port' => $config['port'] ?? DatabaseConfig::DbTypeToDefaultPort($config['type']),
                'username' => $config['username'] ?? null,
                'password' => $config['password'] ?? null,
                'database' => $config['database'],
            ]);
        }

        return $dbConfig;
    }

    public function getNamespace(): string
    {
        return $this->config['benzine']['application']['namespace']
            ?? $this->config['benzine']['application']['name'];
    }

    public function getAppName(): string
    {
        return $this->config['benzine']['application']['name'];
    }

    public function getAppContainer(): string
    {
        return $this->config['benzine']['application']['app_container_class']
            ?? ⌬\⌬::class;
    }

    public function getLaminatorTemplates(): array
    {
        return $this->config['benzine']['laminator']['templates']
            ?? ['Models', 'Services', 'Controllers', 'Endpoints', 'Routes'];
    }
}