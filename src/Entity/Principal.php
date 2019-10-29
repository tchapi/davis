<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="principals")
 * @ORM\Entity(repositoryClass="App\Repository\PrincipalRepository")
 */
class Principal
{
    const PREFIX = 'principals/';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="binary", length=255)
     */
    private $uri;

    /**
     * @ORM\Column(type="binary", length=255, nullable=true)
     */
    private $email;

    /**
     * @ORM\Column(name="displayname", type="string", length=255, nullable=true)
     */
    private $displayName;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUri(): ?string
    {
        if (is_resource($this->uri)) {
            $this->uri = stream_get_contents($this->uri);
        }

        return $this->uri;
    }

    public function setUri(string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    public function getUsername(): ?string
    {
        return str_replace(self::PREFIX, '', $this->getUri());
    }

    public function getEmail(): ?string
    {
        if (is_resource($this->email)) {
            $this->email = stream_get_contents($this->email);
        }

        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }
}
