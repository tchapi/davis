<?php

namespace App\Repository;

use App\Entity\CalendarSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CalendarSubscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method CalendarSubscription|null findOneBy(array $criteria, array $orderBy = null)
 * @method CalendarSubscription[]    findAll()
 * @method CalendarSubscription[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CalendarSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CalendarSubscription::class);
    }

    // /**
    //  * @return CalendarSubscription[] Returns an array of CalendarSubscription objects
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
    public function findOneBySomeField($value): ?CalendarSubscription
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
