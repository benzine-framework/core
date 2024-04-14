<?php

declare(strict_types=1);

namespace Benzine\Tests\Traits;

trait OverrideProtectionTrait
{
    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on
     * @param string $methodName Method name to call
     * @param array  $parameters array of parameters to pass into method
     *
     * @return mixed method return
     */
    public function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($object::class);
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function setProtectedProperty(&$object, $property, $value)
    {
        $reflection = new \ReflectionClass($object::class);
        $prop       = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->setValue($object, $value);
    }

    public function getProtectedProperty(&$object, $property)
    {
        $reflection = new \ReflectionClass($object::class);
        $prop       = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($object);
    }
}
