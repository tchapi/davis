<?php

namespace App\Repository;

use App\Entity\CalendarInstance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

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

    // /**
    //  * @return CalendarInstance[] Returns an array of CalendarInstance objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?CalendarInstance
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
