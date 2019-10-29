<?php

namespace App\Repository;

use App\Entity\PropertyStorage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method PropertyStorage|null find($id, $lockMode = null, $lockVersion = null)
 * @method PropertyStorage|null findOneBy(array $criteria, array $orderBy = null)
 * @method PropertyStorage[]    findAll()
 * @method PropertyStorage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PropertyStorageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PropertyStorage::class);
    }

    // /**
    //  * @return PropertyStorage[] Returns an array of PropertyStorage objects
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
    public function findOneBySomeField($value): ?PropertyStorage
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
