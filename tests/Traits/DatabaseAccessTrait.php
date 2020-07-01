<?php

namespace Benzine\Tests\Traits;

use Slim\Container;
use ⌬\Database\Db;
use ⌬\Services\EnvironmentService;
use ⌬\⌬ as App;

trait DatabaseAccessTrait
{
    public static function setUpBeforeClass(): void
    {
        // If MySQL has been configured, enable a transaction that we can rollback later.
        if (self::isTestDatabaseEnabled()) {
            /** @var Db $db */
            $db = App::Instance()->getContainer()->get(Db::class);

            // If MySQL has been configured, begin transaction.
            if ($db->isMySQLConfigured()) {
                foreach ($db->getDatabases() as $name => $database) {
                    $database->driver->getConnection()->beginTransaction();
                }
            }
        }

        // Continue setup.
        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        // If MySQL has been configured, roll back transaction.
        if (self::isTestDatabaseEnabled()) {
            /** @var Db $db */
            $db = App::Instance()->getContainer()->get(Db::class);
            if ($db->isMySQLConfigured()) {
                foreach ($db->getDatabases() as $name => $database) {
                    $database->driver->getConnection()->rollback();
                }
            }
        }

        // Continue Teardown.
        parent::tearDownAfterClass();
    }

    public function assertArraysEquitable($expected, $actual): void
    {
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    private static function isTestDatabaseEnabled(): bool
    {
        /** @var EnvironmentService $environment */
        $environment = self::getAppContainer()->get(EnvironmentService::class);

        return $environment->isSet('MYSQL_HOST') || $environment->isSet('POSTGRES_HOST');
    }

    /**
     * @return App
     */
    private static function getAppObject()
    {
        $coreAppName = APP_CORE_NAME;

        return $coreAppName::Instance(false);
    }

    /**
     * @return Container
     */
    private static function getAppContainer()
    {
        return self::getAppObject()->getContainer();
    }
}
