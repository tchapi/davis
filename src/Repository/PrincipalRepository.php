<?php

namespace App\Repository;

use App\Entity\Principal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Principal|null find($id, $lockMode = null, $lockVersion = null)
 * @method Principal|null findOneBy(array $criteria, array $orderBy = null)
 * @method Principal[]    findAll()
 * @method Principal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PrincipalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Principal::class);
    }

    // /**
    //  * @return Principal[] Returns an array of Principal objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Principal
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
