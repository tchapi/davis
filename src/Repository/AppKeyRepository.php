<?php

namespace App\Repository;

use App\Entity\AppKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AppKey|null find($id, $lockMode = null, $lockVersion = null)
 * @method AppKey|null findOneBy(array $criteria, array $orderBy = null)
 * @method AppKey[]    findAll()
 * @method AppKey[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AppKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppKey::class);
    }

    public function findOneByAppId(string $appId): ?AppKey
    {
        return $this->findOneBy(['appId' => $appId]);
    }

    /**
     * @return AppKey[]
     */
    public function findAllNewestFirst(): array
    {
        return $this->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    public function touch(AppKey $key, \DateTimeImmutable $now): void
    {
        $key->setLastUsedAt($now);

        // a targeted update and not a flush(): we are inside a DAV request and must not write
        // whatever else happens to be in the unit of work
        $this->createQueryBuilder('k')
            ->update()
            ->set('k.lastUsedAt', ':now')
            ->where('k.id = :id')
            ->setParameter('now', $now, 'datetime_immutable')
            ->setParameter('id', $key->getId())
            ->getQuery()
            ->execute();
    }
}
