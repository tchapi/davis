<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="addressbooks")
 * @ORM\Entity(repositoryClass="App\Repository\AddressBookRepository")
 */
class AddressBook
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(name="principaluri", type="binary", length=255)
     */
    private $principalUri;

    /**
     * @ORM\Column(name="displayname", type="string", length=255)
     */
    private $displayName;

    /**
     * @ORM\Column(type="binary", length=255)
     */
    private $uri;

    /**
     * @ORM\Column(type="text")
     */
    private $description;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $synctoken;

    public function __construct()
    {
        $this->synctoken = 1;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrincipalUri(): ?string
    {
        if (is_resource($this->principalUri)) {
            $this->principalUri = stream_get_contents($this->principalUri);
        }

        return $this->principalUri;
    }

    public function setPrincipalUri(string $principalUri): self
    {
        $this->principalUri = $principalUri;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getSynctoken(): ?string
    {
        return $this->synctoken;
    }

    public function setSynctoken(string $synctoken): self
    {
        $this->synctoken = $synctoken;

        return $this;
    }
}
