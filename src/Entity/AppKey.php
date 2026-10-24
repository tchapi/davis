<?php

namespace App\Entity;

use App\Repository\AppKeyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AppKeyRepository::class)]
#[ORM\Table(name: 'app_keys')]
#[ORM\UniqueConstraint(name: 'uniq_app_keys_app_id', columns: ['app_id'])]
class AppKey
{
    public const APP_ID_PREFIX = 'app_';
    public const ALL_USERS = '*';
    // DAV clients send credentials on every request. We only update the last use date
    // once per minute to avoid a database write on each request.
    public const LAST_USED_THROTTLE = 60;

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private $name;

    #[ORM\Column(name: 'app_id', type: 'string', length: 64)]
    private $appId;

    #[ORM\Column(name: 'secret_hash', type: 'string', length: 255)]
    private $secretHash;

    /**
     * @var string[]
     */
    #[ORM\Column(type: 'json')]
    private $scope = [self::ALL_USERS];

    #[ORM\Column(name: 'revoked_at', type: 'datetime_immutable', nullable: true)]
    private $revokedAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private $createdAt;

    #[ORM\Column(name: 'last_used_at', type: 'datetime_immutable', nullable: true)]
    private $lastUsedAt;

    /**
     * @param string[] $scope
     */
    public static function create(string $name, array $scope = [self::ALL_USERS], ?\DateTimeImmutable $revokedAt = null): self
    {
        return (new self())
            ->setName($name)
            ->setScope($scope)
            ->setRevokedAt($revokedAt)
            ->setAppId(self::APP_ID_PREFIX.bin2hex(random_bytes(12)))
            ->setCreatedAt(new \DateTimeImmutable());
    }

    /**
     * Returns the plain secret, which is not kept anywhere.
     */
    public function issueSecret(): string
    {
        $secret = bin2hex(random_bytes(32));
        $this->secretHash = password_hash($secret, PASSWORD_DEFAULT);

        return $secret;
    }

    /**
     * @return string[]
     */
    public static function parseScope(string $text): array
    {
        return self::normaliseScope(preg_split('/[\r\n,]+/', $text));
    }

    /**
     * @param string[] $scope
     *
     * @return string[]
     */
    public static function normaliseScope(array $scope): array
    {
        $scope = array_values(array_unique(array_filter(array_map('trim', $scope), fn (string $login) => '' !== $login)));

        if (!$scope || in_array(self::ALL_USERS, $scope, true)) {
            return [self::ALL_USERS];
        }

        return $scope;
    }

    public function allows(string $login): bool
    {
        return in_array(self::ALL_USERS, $this->scope, true) || in_array($login, $this->scope, true);
    }

    public function isActive(\DateTimeImmutable $now): bool
    {
        return null === $this->revokedAt || $this->revokedAt > $now;
    }

    public function accepts(string $login, string $secret, \DateTimeImmutable $now): bool
    {
        return $this->isActive($now) && $this->allows($login) && password_verify($secret, $this->secretHash);
    }

    public function wasUsedWithin(int $seconds, \DateTimeImmutable $now): bool
    {
        return null !== $this->lastUsedAt && $this->lastUsedAt > $now->modify("-{$seconds} seconds");
    }

    public function revoke(\DateTimeImmutable $at): self
    {
        if (null === $this->revokedAt || $this->revokedAt > $at) {
            $this->revokedAt = $at;
        }

        return $this;
    }

    public function isAllUsers(): bool
    {
        return in_array(self::ALL_USERS, $this->scope, true);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getAppId(): ?string
    {
        return $this->appId;
    }

    public function setAppId(string $appId): self
    {
        $this->appId = $appId;

        return $this;
    }

    public function getSecretHash(): ?string
    {
        return $this->secretHash;
    }

    public function setSecretHash(string $secretHash): self
    {
        $this->secretHash = $secretHash;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getScope(): array
    {
        return $this->scope;
    }

    /**
     * @param string[] $scope
     */
    public function setScope(array $scope): self
    {
        $this->scope = self::normaliseScope($scope);

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeImmutable $revokedAt): self
    {
        $this->revokedAt = $revokedAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }
}
