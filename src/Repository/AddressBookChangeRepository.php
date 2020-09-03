<?php

namespace App\Repository;

use App\Entity\AddressBookChange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AddressBookChange|null find($id, $lockMode = null, $lockVersion = null)
 * @method AddressBookChange|null findOneBy(array $criteria, array $orderBy = null)
 * @method AddressBookChange[]    findAll()
 * @method AddressBookChange[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AddressBookChangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AddressBookChange::class);
    }

    // /**
    //  * @return AddressBookChange[] Returns an array of AddressBookChange objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?AddressBookChange
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
