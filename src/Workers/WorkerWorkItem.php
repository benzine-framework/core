<?php

declare(strict_types=1);

namespace Benzine\Workers;

use Benzine\Exceptions\WorkerException;
use Benzine\ORM\Abstracts\AbstractModel;

class WorkerWorkItem implements \Serializable
{
    protected array $data;

    public function __call($name, $arguments)
    {
        $method = substr(strtolower($name), 0, 3);
        $field  = substr(strtolower($name), 3);

        switch ($method) {
            case 'set':
                $this->data[$field] = $arguments[0];

                return $this;

            case 'get':
                return $this->data[$field];

            default:
                throw new WorkerException("Method {$name} doesn't exist");
        }
    }

    public function serialize()
    {
        return serialize($this->data);
    }

    public function unserialize($serialized): void
    {
        $this->data = unserialize($serialized);
    }

    public static function Factory(object $object)
    {
        $class = $object::class;

        return (new WorkerWorkItem())
            ->setKey($class, $object)
        ;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function setKey(string $key, $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function getKey(string $key)
    {
        if ($this->data[$key] instanceof AbstractModel) {
            $this->data[$key]->__setUp();
        }

        return $this->data[$key];
    }

    public function getKeys(): array
    {
        return array_keys($this->data);
    }
}
