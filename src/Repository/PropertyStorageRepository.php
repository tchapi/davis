<?php

namespace App\Repository;

use App\Entity\PropertyStorage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method PropertyStorage|null find($id, $lockMode = null, $lockVersion = null)
 * @method PropertyStorage|null findOneBy(array $criteria, array $orderBy = null)
 * @method PropertyStorage[]    findAll()
 * @method PropertyStorage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PropertyStorageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyStorage::class);
    }

    /**
     * Deletes the dead properties stored for a path and everything below it.
     *
     * sabre writes these rows and only removes them when a node is deleted over DAV. The admin UI
     * deletes rows directly in the database, so it has to remove them here too. Otherwise they stay
     * behind and show up on the next calendar or address book that uses the same path.
     *
     * @param string[] $paths e.g. `calendars/jane/work`, which also removes `calendars/jane/work/*`
     */
    public function deletePathsAndTheirChildren(array $paths): int
    {
        if (!$paths) {
            return 0;
        }

        $conditions = [];
        $parameters = [];

        foreach ($paths as $path) {
            $conditions[] = 'path = ?';
            $parameters[] = $path;

            // `_` and `%` are LIKE wildcards and a username can contain `_`, so they need
            // escaping. We use `!` rather than a backslash, which is written differently on
            // each engine.
            $conditions[] = "path LIKE ? ESCAPE '!'";
            $parameters[] = str_replace(['!', '_', '%'], ['!!', '!_', '!%'], $path).'/%';
        }

        return (int) $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM propertystorage WHERE '.implode(' OR ', $conditions),
            $parameters
        );
    }
}
