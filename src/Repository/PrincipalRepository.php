<?php

namespace App\Repository;

use App\Entity\Principal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

    /**
     * @return Principal[] Returns an array of Principal objects
     */
    public function findAllExceptPrincipal(string $principalUri)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isMain = :isMain')
            ->andWhere('p.uri <> :val')
            ->setParameter('isMain', true)
            ->setParameter('val', $principalUri)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<array{Principal, userId: int}>
     */
    public function findAllMainPrincipalsWithUserIds(): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('u.id AS userId')
            ->leftJoin(
                \App\Entity\User::class,
                'u',
                \Doctrine\ORM\Query\Expr\Join::WITH,
                'CONCAT(:prefix, u.username) = p.uri'
            )
            ->andWhere('p.isMain = :isMain')
            ->setParameter('isMain', true)
            ->setParameter('prefix', Principal::PREFIX)
            ->getQuery()
        ->getResult();
    }
}
