<?php

namespace App\Repository;

use App\Entity\Lock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Lock|null find($id, $lockMode = null, $lockVersion = null)
 * @method Lock|null findOneBy(array $criteria, array $orderBy = null)
 * @method Lock[]    findAll()
 * @method Lock[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class LockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lock::class);
    }

    // /**
    //  * @return Lock[] Returns an array of Lock objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('l.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Lock
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
