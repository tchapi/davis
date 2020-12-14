<?php

namespace App\Repository;

use App\Entity\GroupMember;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method GroupMember|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupMember|null findOneBy(array $criteria, array $orderBy = null)
 * @method GroupMember[]    findAll()
 * @method GroupMember[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GroupMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupMember::class);
    }

    // /**
    //  * @return GroupMember[] Returns an array of GroupMember objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('g.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?GroupMember
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
