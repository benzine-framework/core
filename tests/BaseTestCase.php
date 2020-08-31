<?php

namespace Benzine\Tests;

use Faker\Factory as FakerFactory;
use Faker\Generator;
use Faker\Provider;
use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase
{
    // Set this to true if you want to see whats going on inside some unit tests..
    public const DEBUG_MODE = false;

    private static Generator $faker;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        // Force Kint into CLI mode.
        \Kint::$mode_default = \Kint::MODE_CLI;
    }

    /**
     * @return Generator
     */
    public static function getFaker()
    {
        if (!self::$faker) {
            self::$faker = FakerFactory::create();
            self::$faker->addProvider(new Provider\Base(self::$faker));
            self::$faker->addProvider(new Provider\DateTime(self::$faker));
            self::$faker->addProvider(new Provider\Lorem(self::$faker));
            self::$faker->addProvider(new Provider\Internet(self::$faker));
            self::$faker->addProvider(new Provider\Payment(self::$faker));
            self::$faker->addProvider(new Provider\en_US\Person(self::$faker));
            self::$faker->addProvider(new Provider\en_US\Address(self::$faker));
            self::$faker->addProvider(new Provider\en_US\PhoneNumber(self::$faker));
            self::$faker->addProvider(new Provider\en_US\Company(self::$faker));
        }

        return self::$faker;
    }

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
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function setProtectedProperty(&$object, $property, $value)
    {
        $reflection = new \ReflectionClass(get_class($object));
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->setValue($object, $value);
    }

    public function getProtectedProperty(&$object, $property)
    {
        $reflection = new \ReflectionClass(get_class($object));
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($object);
    }

    public function assertArraysEquitable($expected, $actual): void
    {
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);
    }
}
