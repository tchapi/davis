<?php

namespace App\Repository;

use App\Entity\CalendarInstance;
use App\Entity\Principal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CalendarInstance|null find($id, $lockMode = null, $lockVersion = null)
 * @method CalendarInstance|null findOneBy(array $criteria, array $orderBy = null)
 * @method CalendarInstance[]    findAll()
 * @method CalendarInstance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CalendarInstanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CalendarInstance::class);
    }

    /**
     * @return CalendarInstance[] Returns an array of CalendarInstance objects
     */
    public function findSharedInstancesOfInstance(int $calendarId, bool $withCalendar = false)
    {
        $query = $this->createQueryBuilder('c')
            ->leftJoin(Principal::class, 'p', \Doctrine\ORM\Query\Expr\Join::WITH, 'c.principalUri = p.uri')
            ->where('c.calendar = :id')
            ->setParameter('id', $calendarId)
            ->andWhere('c.access NOT IN (:ownerAccess)')
            ->setParameter('ownerAccess', CalendarInstance::getOwnerAccesses());

        if ($withCalendar) {
            // Returns CalendarInstances as arrays, with displayName and email of the owner
            return $query->addSelect('p.displayName', 'p.email')
                ->getQuery()
                ->getArrayResult();
        } else {
            // Returns CalendarInstances as objects
            return $query->getQuery()
                ->getResult();
        }
    }

    /**
     * @return CalendarInstance Returns a CalendarInstance object
     */
    public function findSharedInstanceOfInstanceFor(int $calendarId, string $principalUri)
    {
        return $this->createQueryBuilder('c')
            ->where('c.calendar = :id')
            ->setParameter('id', $calendarId)
            ->andWhere('c.access NOT IN (:ownerAccess)')
            ->setParameter('ownerAccess', CalendarInstance::getOwnerAccesses())
            ->andWhere('c.principalUri = :principalUri')
            ->setParameter('principalUri', $principalUri)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasDifferentOwner(int $calendarId, string $principalUri): bool
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.calendar = :id')
            ->setParameter('id', $calendarId)
            ->andWhere('c.access IN (:ownerAccess)')
            ->setParameter('ownerAccess', CalendarInstance::getOwnerAccesses())
            ->andWhere('c.principalUri != :principalUri')
            ->setParameter('principalUri', $principalUri)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Get counts of calendar objects by component type for a calendar instance.
     *
     * @param int $calendarId The ID of the calendar
     *
     * @return array An associative array with keys 'events', 'notes', 'tasks' containing their respective counts
     */
    public function getObjectCountsByComponentType(int $calendarId): array
    {
        $objectRepository = $this->getEntityManager()->getRepository(\App\Entity\CalendarObject::class);

        // Instead of three separate queries, get all counts in a single query
        $results = $objectRepository->createQueryBuilder('o')
            ->select('o.componentType, COUNT(o.id) as count')
            ->where('o.calendar = :calendarId')
            ->setParameter('calendarId', $calendarId)
            ->groupBy('o.componentType')
            ->getQuery()
            ->getResult();

        $componentTypeMap = [
            \App\Entity\Calendar::COMPONENT_EVENTS => 'events',
            \App\Entity\Calendar::COMPONENT_NOTES => 'notes',
            \App\Entity\Calendar::COMPONENT_TODOS => 'tasks',
        ];

        $counts = [
            'events' => 0,
            'notes' => 0,
            'tasks' => 0,
        ];

        // Map query results to the expected keys
        foreach ($results as $result) {
            if (isset($componentTypeMap[$result['componentType']])) {
                $counts[$componentTypeMap[$result['componentType']]] = (int) $result['count'];
            }
        }

        return $counts;
    }
}
