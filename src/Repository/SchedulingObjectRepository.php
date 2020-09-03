<?php

namespace App\Repository;

use App\Entity\SchedulingObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method SchedulingObject|null find($id, $lockMode = null, $lockVersion = null)
 * @method SchedulingObject|null findOneBy(array $criteria, array $orderBy = null)
 * @method SchedulingObject[]    findAll()
 * @method SchedulingObject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SchedulingObjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchedulingObject::class);
    }

    // /**
    //  * @return SchedulingObject[] Returns an array of SchedulingObject objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?SchedulingObject
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
