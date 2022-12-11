<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="locks")
 * @ORM\Entity()
 */
class Lock
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $owner;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $timeout;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $created;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $token;

    /**
     * @ORM\Column(type="smallint", nullable=true)
     */
    private $scope;

    /**
     * @ORM\Column(type="smallint", nullable=true)
     */
    private $depth;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $uri;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?string
    {
        return $this->owner;
    }

    public function setOwner(?string $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function setTimeout(?int $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function getCreated(): ?int
    {
        return $this->created;
    }

    public function setCreated(?int $created): self
    {
        $this->created = $created;

        return $this;
    }

    public function getToken(): ?string
    {
        if (is_resource($this->token)) {
            $this->token = stream_get_contents($this->token);
        }

        return $this->token;
    }

    public function setToken(?string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getScope(): ?int
    {
        return $this->scope;
    }

    public function setScope(?int $scope): self
    {
        $this->scope = $scope;

        return $this;
    }

    public function getDepth(): ?int
    {
        return $this->depth;
    }

    public function setDepth(?int $depth): self
    {
        $this->depth = $depth;

        return $this;
    }

    public function getUri(): ?string
    {
        if (is_resource($this->uri)) {
            $this->uri = stream_get_contents($this->uri);
        }

        return $this->uri;
    }

    public function setUri(?string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }
}
