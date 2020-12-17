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
    public function findSharedInstancesOfInstance(int $calendarId)
    {
        return $this->createQueryBuilder('c')
            ->leftJoin(Principal::class, 'p', \Doctrine\ORM\Query\Expr\Join::WITH, 'c.principalUri = p.uri')
            ->addSelect('p.displayName', 'p.email')
            ->where('c.calendar = :id')
            ->setParameter('id', $calendarId)
            ->andWhere('c.access != :ownerAccess')
            ->setParameter('ownerAccess', CalendarInstance::ACCESS_OWNER)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return CalendarInstance Returns a CalendarInstance object
     */
    public function findSharedInstanceOfInstanceFor(int $calendarId, string $principalUri)
    {
        return $this->createQueryBuilder('c')
            ->where('c.calendar = :id')
            ->setParameter('id', $calendarId)
            ->andWhere('c.access != :ownerAccess')
            ->setParameter('ownerAccess', CalendarInstance::ACCESS_OWNER)
            ->andWhere('c.principalUri = :principalUri')
            ->setParameter('principalUri', $principalUri)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
