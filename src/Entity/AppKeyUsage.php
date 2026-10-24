<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Records which users an app key has acted as, and when. There is one row per key and login.
 * A key with `*` can act as many users, so this tells you whose data it used.
 */
#[ORM\Entity]
#[ORM\Table(name: 'app_key_usages')]
#[ORM\UniqueConstraint(name: 'uniq_app_key_usages_key_login', columns: ['app_key_id', 'login'])]
class AppKeyUsage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: AppKey::class)]
    #[ORM\JoinColumn(name: 'app_key_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private $appKey;

    #[ORM\Column(type: 'string', length: 255)]
    private $login;

    #[ORM\Column(name: 'first_used_at', type: 'datetime_immutable')]
    private $firstUsedAt;

    #[ORM\Column(name: 'last_used_at', type: 'datetime_immutable')]
    private $lastUsedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAppKey(): ?AppKey
    {
        return $this->appKey;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function getFirstUsedAt(): ?\DateTimeImmutable
    {
        return $this->firstUsedAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }
}
