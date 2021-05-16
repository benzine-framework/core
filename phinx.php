<?php

require_once __DIR__.'/bin/find-autoloader.php';

$app = require VENDOR_PATH.'/../bootstrap.php';
use Benzine\ORM;

/** @var \Benzine\App $app */
$pdo = $app
    ->get(ORM\Connection\Databases::class)
    ->getDatabase('default')
    ->getAdapter()
    ->getDriver()
    ->getConnection()
    ->getResource()
;
$name = $pdo->query('SELECT DATABASE()')->fetchColumn(0);

return [
    'paths' => [
        'migrations' => [
            'db/migrations',
        ],
        'seeds' => [
            'db/seeds',
        ],
    ],
    'migration_base_class' => ORM\Migrations\AbstractMigration::class,
    'environments' => [
        'default_environment' => 'default',
        'default_migration_table' => 'Migrations',
        'default' => [
            'adapter' => 'mysql',
            'connection' => $pdo,
            'name' => $name,
        ],
    ],
];
