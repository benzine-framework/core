<?php

declare(strict_types=1);

namespace Benzine\Services;

class EnvironmentService
{
    private array $environmentVariables;

    public function __construct()
    {
        $this->environmentVariables = array_merge($_SERVER, $_ENV);
        if (file_exists(APP_ROOT . '/.env')) {
            $env   = file_get_contents(APP_ROOT . '/.env');
            $lines = explode("\n", $env);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line == '') {
                    continue;
                }
                $parts                                 = explode('=', $line);
                $this->environmentVariables[$parts[0]] = $parts[1];
            }
        }

        ksort($this->environmentVariables);
    }

    public function has(string $key): bool
    {
        return null !== $this->get($key);
    }

    public function all(): array
    {
        ksort($this->environmentVariables);

        return $this->environmentVariables;
    }

    public function get(string $key, mixed $default = null)
    {
        if (isset($this->environmentVariables[$key])) {
            return $this->environmentVariables[$key];
        }

        return $default;
    }

    public function set(string $key, string $value): self
    {
        $this->environmentVariables[$key] = $value;
        ksort($this->environmentVariables);

        return $this;
    }

    public function delete(string $key): self
    {
        unset($this->environmentVariables[$key]);

        return $this;
    }

    public function getPublicHostname(): string
    {
        return sprintf(
            '%s://%s',
            $this->get('SERVER_PORT', 43) == 443 ? 'https' : 'http',
            $this->get('HTTP_HOST')
        );
    }

    public function getPublicPath(): string
    {
        return $this->get('REQUEST_URI');
    }

    public function getPublicUrl(): string
    {
        return $this->getPublicHostname() . $this->getPublicPath();
    }
}
