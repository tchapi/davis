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

    /**
     * A username ends up in the principal URI (`principals/<username>`), so it must not carry
     * anything that would change that path's structure. Letters, digits and `_ . @ + ' -` are allowed:
     * the punctuation is what shows up in mail-derived login names. Enforced when a user is created; existing
     * accounts are left alone so that an odd username created before this rule stays editable.
     */
    public const USERNAME_PATTERN = '/^[a-zA-Z0-9_.@+\'-]+$/';

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255, groups: ['creation'])]
    #[Assert\Regex(pattern: self::USERNAME_PATTERN, message: 'form.username.invalid', groups: ['creation'])]
    private $username;

    #[ORM\Column(name: 'digesta1', type: 'string', length: 255)]
    private $password;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * A user's principal is always `principals/<username>`.
     */
    public function getPrincipalUri(): string
    {
        return Principal::PREFIX.$this->username;
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
