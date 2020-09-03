<?php

namespace App\Repository;

use App\Entity\CalendarChange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CalendarChange|null find($id, $lockMode = null, $lockVersion = null)
 * @method CalendarChange|null findOneBy(array $criteria, array $orderBy = null)
 * @method CalendarChange[]    findAll()
 * @method CalendarChange[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CalendarChangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CalendarChange::class);
    }

    // /**
    //  * @return CalendarChange[] Returns an array of CalendarChange objects
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
    public function findOneBySomeField($value): ?CalendarChange
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
