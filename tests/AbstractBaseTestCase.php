<?php

declare(strict_types=1);

namespace Benzine\Tests;

use Faker\Factory as FakerFactory;
use Faker\Generator as Faker;
use Faker\Provider;

abstract class AbstractBaseTestCase extends AbstractTestCase
{
    private static Faker $faker;

    public function __construct($name = null)
    {
        parent::__construct($name);

        // Force Kint into CLI mode.
        \Kint::$mode_default = \Kint::MODE_CLI;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
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

    public static function getFaker(): Faker
    {
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
    public function invokeMethod(&$object, $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass($object::class);
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    public function setProtectedProperty(&$object, $property, $value): self
    {
        $reflection = new \ReflectionClass($object::class);
        $prop       = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);

        return $this;
    }

    public function getProtectedProperty(&$object, $property)
    {
        $reflection = new \ReflectionClass($object::class);
        $prop       = $reflection->getProperty($property);
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
