<?php

declare(strict_types=1);

namespace Benzine\Controllers\Filters;

class FilterCondition
{
    public const CONDITION_EQUAL                 = '=';
    public const CONDITION_NOT_EQUAL             = '!=';
    public const CONDITION_GREATER_THAN          = '>';
    public const CONDITION_LESS_THAN             = '<';
    public const CONDITION_GREATER_THAN_OR_EQUAL = '>=';
    public const CONDITION_LESS_THAN_OR_EQUAL    = '<=';
    public const CONDITION_LIKE                  = 'LIKE';
    public const CONDITION_NOT_LIKE              = 'NOT LIKE';
    public const CONDITION_IN                    = 'IN';
    public const CONDITION_NOT_IN                = 'NOT IN';
}
