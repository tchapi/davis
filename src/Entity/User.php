<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="users")
 * @ORM\Entity()
 * @UniqueEntity("username")
 */
class User
{
    public const DEFAULT_AUTH_REALM = 'SabreDAV';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Assert\NotBlank
     */
    private $username;

    /**
     * @ORM\Column(name="digesta1", type="string", length=255)
     */
    private $password;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        if (is_resource($this->username)) {
            $this->username = stream_get_contents($this->username);
        }

        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        if (is_resource($this->password)) {
            $this->password = stream_get_contents($this->password);
        }

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
