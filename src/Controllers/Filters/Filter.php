<?php

declare(strict_types=1);

namespace Benzine\Controllers\Filters;

use Benzine\Exceptions\FilterDecodeException;
use Laminas\Db\Sql\Expression;

class Filter
{
    protected $limit;
    protected $offset;
    protected $wheres;
    protected $order;
    protected $orderDirection;

    public function getOrderDirection()
    {
        return $this->orderDirection;
    }

    /**
     * @throws FilterDecodeException
     */
    public function setOrderDirection(string $orderDirection): self
    {
        if (!in_array(strtoupper($orderDirection), ['ASC', 'DESC', 'RAND'], true)) {
            throw new FilterDecodeException("Failed to decode Filter Order, Direction unknown: {$orderDirection} must be ASC|DESC|RAND");
        }
        $this->orderDirection = strtoupper($orderDirection);

        return $this;
    }

    /**
     * @throws FilterDecodeException
     */
    public function parseFromHeader($header): self
    {
        foreach ($header as $key => $value) {
            switch ($key) {
                case 'limit':
                    $this->setLimit($value);

                    break;

                case 'offset':
                    $this->setOffset($value);

                    break;

                case 'wheres':
                    $this->setWheres($value);

                    break;

                case 'order':
                    $this->parseOrder($value);

                    break;

                default:
                    throw new FilterDecodeException("Failed to decode Filter, unknown key: {$key}");
            }
        }

        return $this;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    public function setLimit($limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function getOffset()
    {
        return $this->offset;
    }

    public function setOffset($offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function getWheres()
    {
        return $this->wheres;
    }

    public function setWheres($wheres): self
    {
        $this->wheres = $wheres;

        return $this;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function setOrder($order): self
    {
        $this->order = $order;

        return $this;
    }

    public function setOrderRandom(): self
    {
        $this->setOrder(new Expression('RAND()'));

        return $this;
    }

    public function parseOrder($orderArray): self
    {
        if (in_array(strtolower($orderArray['column']), ['rand', 'random', 'rand()'], true)) {
            $this->setOrderRandom();
        } elseif (isset($orderArray['column'], $orderArray['direction'])) {
            $this->setOrder($orderArray['column']);

            if (isset($orderArray['direction'])) {
                $this->setOrderDirection($orderArray['direction']);
            }
        } else {
            throw new FilterDecodeException("Could not find properties 'column' or 'direction' of the order array given.");
        }

        return $this;
    }
}
