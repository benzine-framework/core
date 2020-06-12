<?php

namespace Benzine\Services;

use Symfony\Component\Yaml\Yaml;

class ConfigurationService
{
    public const KEY_APP_NAME = 'application/name';
    public const KEY_APP_ROOT = 'application/root';
    public const KEY_CLASS = 'application/class';
    public const KEY_DEBUG_ENABLE = 'application/debug';
    public const KEY_DEFAULT_ACCESS = 'application/default_access';
    public const KEY_TIMEZONE = 'application/timezone';
    public const KEY_LOCALE = 'application/locale';
    public const KEY_SESSION_ENABLED = 'application/session_enabled';
    public const KEY_LOG_FORMAT_DATE = 'logging/format_date';
    public const KEY_LOG_FORMAT_MESSAGE = 'logging/format_message';

    protected EnvironmentService $environmentService;
    protected string $appRoot;
    protected array $config;

    public function __construct(EnvironmentService $environmentService)
    {
        $this->environmentService = $environmentService;
        $this->findConfig();
        $this->setupDefines();
    }

    protected function setupDefines() : void {
        define("APP_ROOT", $this->appRoot);
        define("APP_NAME", $this->get('application/name'));
    }

    /**
     * Locate .benzine.yml
     * @param string|null $path
     */
    protected function findConfig(string $path = null) : bool {
        if(!$path){
            $path = getcwd();
            //$path = dirname($this->environmentService->get('SCRIPT_FILENAME'));
        }
        if(!file_exists($path . "/.benzine.yml")){
            $currentDirElem = explode(DIRECTORY_SEPARATOR, $path);
            array_pop($currentDirElem);
            $parentPath = implode(DIRECTORY_SEPARATOR, $currentDirElem);
            return $this->findConfig($parentPath);
        }

        $this->config = Yaml::parseFile($path . "/.benzine.yml");
        $this->appRoot = $path;

        return true;
    }

    public function get(string $key, string $defaultValue = null){
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
}