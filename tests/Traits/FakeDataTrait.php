<?php

declare(strict_types=1);

namespace Benzine\Tests\Traits;

use Faker\Factory as FakerFactory;
use Faker\Generator;
use Faker\Provider;

trait FakeDataTrait
{
    /** @var Generator */
    protected static $faker;

    public static function setUpBeforeClass(): void
    {
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

        // Continue setup.
        parent::setUpBeforeClass();
    }

    public function faker(): Generator
    {
        return self::$faker;
    }
}
