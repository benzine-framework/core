<?php

namespace Benzine\Services;

class EnvironmentService
{
    private array $environmentVariables;

    public function __construct()
    {
        $this->environmentVariables = array_merge($_SERVER, $_ENV);
        ksort($this->environmentVariables);
    }

    public function has(string $key) : bool {
        return $this->get($key) !== null;
    }

    public function get(string $key, string $default = null){
        if(isset($this->environmentVariables[$key])){
            return $this->environmentVariables[$key];
        }
        return $default;
    }

    public function set(string $key, string $value) : self {
        $this->environmentVariables[$key] = $value;
        ksort($this->environmentVariables);
        return $this;
    }

    public function delete(string $key) : self {
        unset($this->environmentVariables[$key]);
        return $this;
    }
}