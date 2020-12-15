<?php

namespace Benzine\Router;

use Monolog\Logger;
use Slim\App;

class Route
{
    public const ACCESS_PUBLIC = 'public';
    public const ACCESS_PRIVATE = 'private';

    public const ARGUMENT_ACCESS = '_access';

    protected $name;
    protected $callback;
    protected $SDKClass;
    protected $SDKFunction;
    protected $SDKTemplate = 'callback';
    protected $routerPattern;
    protected $httpEndpoint;
    protected $httpMethod = 'GET';
    protected $weight = 0;
    protected $singular;
    protected $plural;
    protected $properties;
    protected $propertyData = [];
    protected $propertyOptions;
    protected $exampleEntity;
    protected $exampleEntityFinderFunction;
    protected array $callbackProperties = [];
    protected array $arguments = [
        self::ARGUMENT_ACCESS => self::ACCESS_PUBLIC,
    ];
    protected array $validDomains = [];

    public function getCallbackProperties(): array
    {
        return $this->callbackProperties;
    }

    public function setCallbackProperties(array $callbackProperties): Route
    {
        $this->callbackProperties = [];
        foreach ($callbackProperties as $name => $property) {
            $this->populateCallbackProperty($name, $property);
        }

        return $this;
    }

    /**
     * @param $name
     * @param null $default
     *
     * @return $this
     */
    public function addCallbackProperty(string $name, bool $mandatory = false, $default = null)
    {
        return $this->populateCallbackProperty($name, [
            'isMandatory' => $mandatory,
            'default' => $default,
        ]);
    }

    public function populateCallbackProperty(string $name, array $property)
    {
        $property['name'] = $name;
        $this->callbackProperties[$name] = array_merge(
            [
                'in' => null,
                'description' => null,
                'isMandatory' => null,
                'default' => null,
                'type' => null,
                'examples' => [],
            ],
            $property
        );

        return $this;
    }

    public function getUniqueIdentifier()
    {
        return implode(
            '::',
            [
                $this->getRouterPattern(),
                $this->getHttpMethod(),
                "Weight={$this->getWeight()}",
                $this->callback,
            ]
        );
    }

    public function setProperties($properties): Route
    {
        $this->properties = [];
        foreach ($properties as $name => $type) {
            if (is_numeric($name)) {
                $this->properties[] = $type;
            } else {
                $this->properties[] = $name;
                $this->propertyData[$name]['type'] = $type;
            }
        }

        return $this;
    }

    /**
     * @param mixed $propertyOptions
     *
     * @return Route
     */
    public function setPropertyOptions($propertyOptions)
    {
        $this->propertyOptions = [];
        foreach ($propertyOptions as $name => $options) {
            $this->propertyOptions[$name] = $options;
            $this->propertyData[$name]['options'] = $options;
        }

        return $this;
    }

    public function populateRoute(App $app): App
    {
        $mapping = $app->map(
            [$this->getHttpMethod()],
            $this->getRouterPattern(),
            $this->getCallback()
        );

        $mapping->setName($this->getName() ? $this->getName() : 'Unnamed Route');

        foreach ($this->arguments as $key => $value) {
            $mapping->setArgument($key, $value);
        }

        return $app;
    }

    /**
     * @return string
     */
    public function getAccess()
    {
        return $this->getArgument(self::ARGUMENT_ACCESS);
    }

    /**
     * @param string $access
     */
    public function setAccess($access = self::ACCESS_PUBLIC): Route
    {
        return $this->setArgument(self::ARGUMENT_ACCESS, $access);
    }

    public function getArgument(string $argument)
    {
        $argument = $this->prefixArgumentKey($argument);

        return $this->arguments[$argument] ?? null;
    }

    public function setArgument(string $argument, $value): Route
    {
        $argument = $this->prefixArgumentKey($argument);
        $this->arguments[$argument] = $value;

        return $this;
    }

    public function getName(): string
    {
        return $this->name ?? 'Unnamed Route';
    }

    public function setName(string $name): Route
    {
        $this->name = $name;

        return $this;
    }

    public function getCallback(): string
    {
        return $this->callback;
    }

    public function setCallback(string $callback): Route
    {
        $this->callback = $callback;

        return $this;
    }

    public function getSDKClass(): string
    {
        return $this->SDKClass;
    }

    public function setSDKClass(string $SDKClass): Route
    {
        $this->SDKClass = $SDKClass;

        return $this;
    }

    public function getSDKFunction(): string
    {
        return $this->SDKFunction;
    }

    public function setSDKFunction(string $SDKFunction): Route
    {
        $this->SDKFunction = $SDKFunction;

        return $this;
    }

    public function getSDKTemplate(): string
    {
        return $this->SDKTemplate;
    }

    public function setSDKTemplate(string $SDKTemplate): Route
    {
        $this->SDKTemplate = $SDKTemplate;

        return $this;
    }

    public function getRouterPattern(): string
    {
        return $this->routerPattern;
    }

    public function setRouterPattern(string $routerPattern): Route
    {
        $this->routerPattern = $routerPattern;

        return $this;
    }

    public function getHttpEndpoint(): string
    {
        return $this->httpEndpoint;
    }

    public function setHttpEndpoint(string $httpEndpoint): Route
    {
        $this->httpEndpoint = $httpEndpoint;

        return $this;
    }

    public function getHttpMethod(): string
    {
        return $this->httpMethod;
    }

    public function setHttpMethod(string $httpMethod): Route
    {
        $this->httpMethod = $httpMethod;

        return $this;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): Route
    {
        $this->weight = $weight;

        return $this;
    }

    public function getSingular(): string
    {
        return $this->singular;
    }

    public function setSingular(string $singular): Route
    {
        $this->singular = $singular;

        return $this;
    }

    public function getPlural(): string
    {
        return $this->plural;
    }

    public function setPlural(string $plural): Route
    {
        $this->plural = $plural;

        return $this;
    }

    public function getPropertyData(): array
    {
        return $this->propertyData;
    }

    public function setPropertyData(array $propertyData): Route
    {
        $this->propertyData = $propertyData;

        return $this;
    }

    public function getExampleEntity()
    {
        return $this->exampleEntity;
    }

    /**
     * @param mixed $exampleEntity
     *
     * @return Route
     */
    public function setExampleEntity($exampleEntity)
    {
        $this->exampleEntity = $exampleEntity;

        return $this;
    }

    public function getExampleEntityFinderFunction()
    {
        return $this->exampleEntityFinderFunction;
    }

    /**
     * @param mixed $exampleEntityFinderFunction
     *
     * @return Route
     */
    public function setExampleEntityFinderFunction($exampleEntityFinderFunction)
    {
        $this->exampleEntityFinderFunction = $exampleEntityFinderFunction;

        return $this;
    }

    /**
     * @return array|string[]
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param array|string[] $arguments
     *
     * @return Route
     */
    public function setArguments($arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }

    public function addValidDomain(string $validDomain): Route
    {
        $this->validDomains[] = $validDomain;

        return $this;
    }

    public function getValidDomains(): array
    {
        $this->validDomains = array_unique($this->validDomains);

        return $this->validDomains;
    }

    public function hasValidDomains(): bool
    {
        return count($this->validDomains) > 0;
    }

    public function isInContainedInValidDomains(string $host = null): bool
    {
        if (null === $host) {
            return false;
        }

        foreach ($this->validDomains as $validDomain) {
            if (fnmatch($validDomain, $host)) {
                return true;
            }
        }

        return false;
    }

    public function setValidDomains(array $validDomains): Route
    {
        $this->validDomains = $validDomains;

        return $this;
    }

    private function prefixArgumentKey(string $key)
    {
        if (0 !== strpos($key, '_')) {
            $key = "_{$key}";
        }

        return $key;
    }
}
