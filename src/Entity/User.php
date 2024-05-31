<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity()]
#[ORM\Table(name: '`users`')]
#[UniqueEntity('username')]
class User
{
    public const DEFAULT_AUTH_REALM = 'SabreDAV';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank]
    private $username;

    #[ORM\Column(name: 'digesta1', type: 'string', length: 255)]
    private $password;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    // $password _can_ be NULL here, in the case when we edit a user
    // and do not change its password
    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }
}
