<?php

declare(strict_types=1);

namespace Benzine\Enum;

use Othyn\PhpEnumEnhancements\Traits\EnumEnhancements as OthynEnumEnhancements;

trait EnumEnhancements
{
    use OthynEnumEnhancements;

    public static function fromName(\BackedEnum | string $seekName)
    {
        if ($seekName instanceof \BackedEnum) {
            return $seekName;
        }
        foreach (self::cases() as $name => $status) {
            $name  = is_string($status) ? $name : $status->name;
            $value = is_string($status) ? $status : $status->value;
            if ($name === $seekName) {
                return $status;
            }
        }

        // If there is a default, return it instead of throwing an error
        $hasDefault = false;
        foreach (self::cases() as $enum) {
            if ($enum->name === 'DEFAULT') {
                $hasDefault = true;
            }
        }
        if ($hasDefault) {
            return self::DEFAULT;
        }

        \Kint::dump(sprintf("'%s' is not a valid backing key for enum %s", $seekName, self::class));

        throw new \ValueError(sprintf("'%s' is not a valid backing key for enum %s", $seekName, self::class));
    }

    public static function fromValue(\BackedEnum | int | string $seekValue)
    {
        if ($seekValue instanceof \BackedEnum) {
            return $seekValue;
        }
        foreach (self::cases() as $name => $status) {
            $name  = is_string($status) ? $name : $status->name;
            $value = is_string($status) ? $status : $status->value;
            if ($value === $seekValue) {
                return $status;
            }
        }

        \Kint::dump(sprintf("'%s' is not a valid backing value for enum %s.", $seekValue, self::class));

        throw new \ValueError(sprintf("'%s' is not a valid backing value for enum %s", $seekValue, self::class));
    }

    public static function isValidName(string $name): bool
    {
        try {
            self::fromName($name);
        } catch (\ValueError) {
            return false;
        }

        return true;
    }

    public static function isValidValue(string $name): bool
    {
        try {
            self::fromValue($name);
        } catch (\ValueError) {
            return false;
        }

        return true;
    }

    public static function values(): array
    {
        return self::cases();
    }

    public static function random(): self
    {
        $values = self::values();

        return $values[array_rand($values)];
    }
}
