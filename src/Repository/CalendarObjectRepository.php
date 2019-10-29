<?php

namespace App\Repository;

use App\Entity\CalendarObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method CalendarObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method CalendarObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method CalendarObject[]    findAll()
 * @method CalendarObject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CalendarObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CalendarObject::class);
    }

    // /**
    //  * @return CalendarObject[] Returns an array of CalendarObject objects
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
    public function findOneBySomeField($value): ?CalendarObject
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
