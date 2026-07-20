<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware\EnableForeignKeys;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Platforms\SqlitePlatform;

final class SQLiteForeignKeyMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return $driver->getDatabasePlatform() instanceof SqlitePlatform
            ? (new EnableForeignKeys())->wrap($driver)
            : $driver;
    }
}
