<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\SqlitePlatform;

final class SQLiteConnectionMiddleware implements Middleware
{
    private string $journalMode;

    public function __construct(string $journalMode)
    {
        $journalMode = strtoupper($journalMode);
        if (!in_array($journalMode, ['WAL', 'DELETE'], true)) {
            throw new \InvalidArgumentException('SQLITE_JOURNAL_MODE must be WAL or DELETE.');
        }

        $this->journalMode = $journalMode;
    }

    public function wrap(Driver $driver): Driver
    {
        if (!$driver->getDatabasePlatform() instanceof SqlitePlatform) {
            return $driver;
        }

        return new class($driver, $this->journalMode) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly string $journalMode)
            {
                parent::__construct($driver);
            }

            public function connect(
                #[\SensitiveParameter]
                array $params,
            ): Connection {
                $connection = parent::connect($params);

                $connection->exec('PRAGMA busy_timeout=60000');

                if (!($params['memory'] ?? false)) {
                    $journalMode = $connection->query('PRAGMA journal_mode='.$this->journalMode)->fetchOne();
                    if (strtoupper((string) $journalMode) !== $this->journalMode) {
                        throw new \RuntimeException(sprintf('SQLite could not enable %s journal mode; check filesystem support and database permissions.', $this->journalMode));
                    }
                }

                $connection->exec('PRAGMA synchronous=FULL');

                return $connection;
            }
        };
    }
}
