<?php

namespace Benzine\Workers;

use Benzine\ORM\Abstracts\Model;

class WorkerWorkItem
{
    protected array $data;

    public function __call($name, $arguments)
    {
        $method = substr(strtolower($name), 0, 3);
        $field = substr(strtolower($name), 3);
        switch ($method) {
            case 'set':
                $this->data[$field] = $arguments[0];

                return $this;
            case 'get':
                return $this->data[$field];
            default:
                throw new \Exception("Method {$name} doesn't exist");
        }
    }

    public function __serialize(): array
    {
        return $this->data;
    }

    public function __unserialize(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     *
     * @return WorkerWorkItem
     */
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
        if ($this->data[$key] instanceof Model) {
            $this->data[$key]->__setUp();
        }

        return $this->data[$key];
    }

    public function getKeys(): array
    {
        return array_keys($this->data);
    }
}
