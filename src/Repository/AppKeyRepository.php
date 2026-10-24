<?php

namespace App\Repository;

use App\Entity\AppKey;
use App\Entity\AppKeyUsage;
use App\Entity\Principal;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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

        // We use an update query instead of flush(). We are in a DAV request, and flush()
        // would also save any other pending change.
        $this->createQueryBuilder('k')
            ->update()
            ->set('k.lastUsedAt', ':now')
            ->where('k.id = :id')
            ->setParameter('now', $now, 'datetime_immutable')
            ->setParameter('id', $key->getId())
            ->getQuery()
            ->execute();
    }

    /**
     * @param string[] $scope
     *
     * @return string[] the usernames in the scope that have no account
     */
    public function findUnknownUsers(array $scope): array
    {
        $names = array_values(array_diff($scope, [AppKey::ALL_USERS]));
        if (!$names) {
            return [];
        }

        $known = array_map(
            fn (User $user) => $user->getUsername(),
            $this->getEntityManager()->getRepository(User::class)->findBy(['username' => $names])
        );

        // We compare in PHP because MySQL ignores case when comparing strings, and the scope check does not
        return array_values(array_filter($names, fn (string $name) => !in_array($name, $known, true)));
    }

    /**
     * Finds the account an app key wants to act as: a user with exactly this username, and its principal.
     * Returns null if one of the two is missing.
     */
    public function findAccount(string $login, string $principalUri): ?Principal
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('p AS principal', 'u.username')
            ->from(Principal::class, 'p')
            ->innerJoin(User::class, 'u', 'WITH', 'u.username = :login')
            ->where('p.uri = :uri')
            ->setParameter('login', $login)
            ->setParameter('uri', $principalUri)
            ->getQuery()
            ->getResult();

        // MySQL ignores case when comparing strings, so `ALICE` can return the user or the principal
        // of `alice`. We only accept an exact match for both.
        foreach ($rows as $row) {
            if ($row['username'] === $login && $row['principal']->getUri() === $principalUri) {
                return $row['principal'];
            }
        }

        return null;
    }

    /**
     * Records that the key acted as this login. Like touch(), it writes at most once a minute
     * per login. It uses the connection directly, so it does not save other pending changes.
     */
    public function recordUse(AppKey $key, string $login, \DateTimeImmutable $now): void
    {
        $connection = $this->getEntityManager()->getConnection();
        $format = $connection->getDatabasePlatform()->getDateTimeFormatString();

        $lastUsedAt = $connection->fetchOne(
            'SELECT last_used_at FROM app_key_usages WHERE app_key_id = ? AND login = ?',
            [$key->getId(), $login]
        );

        if (false === $lastUsedAt) {
            try {
                $connection->insert('app_key_usages', [
                    'app_key_id' => $key->getId(),
                    'login' => $login,
                    'first_used_at' => $now->format($format),
                    'last_used_at' => $now->format($format),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Another request from the same client added the row first
            }

            return;
        }

        if (new \DateTimeImmutable($lastUsedAt) <= $now->modify('-'.AppKey::LAST_USED_THROTTLE.' seconds')) {
            $connection->update(
                'app_key_usages',
                ['last_used_at' => $now->format($format)],
                ['app_key_id' => $key->getId(), 'login' => $login]
            );
        }
    }

    /**
     * Removes a deleted user from every key that lists them. The caller flushes the changes
     * together with the rest of the deletion.
     */
    public function forgetUser(string $login, \DateTimeImmutable $now): void
    {
        foreach ($this->findAll() as $key) {
            $key->forgetUser($login, $now);
        }
    }

    /**
     * @return array<int, AppKeyUsage[]> the usages of each key, by key id, most recent first
     */
    public function findUsagesByKey(): array
    {
        $usages = $this->getEntityManager()->getRepository(AppKeyUsage::class)->findBy([], ['lastUsedAt' => 'DESC', 'id' => 'DESC']);

        $byKey = [];
        foreach ($usages as $usage) {
            $byKey[$usage->getAppKey()->getId()][] = $usage;
        }

        return $byKey;
    }
}
