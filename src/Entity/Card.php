<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="cards")
 * @ORM\Entity()
 */
class Card
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\AddressBook", inversedBy="cards")
     * @ORM\JoinColumn(name="addressbookid", nullable=false)
     */
    private $addressBook;

    /**
     * @ORM\Column(name="carddata", type="blob", nullable=true)
     */
    private $cardData;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $uri;

    /**
     * @ORM\Column(name="lastmodified", type="integer", nullable=true)
     */
    private $lastModified;

    /**
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    private $etag;

    /**
     * @ORM\Column(type="integer")
     */
    private $size;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAddressBook(): ?AddressBook
    {
        return $this->addressBook;
    }

    public function setAddressBook(?AddressBook $addressBook): self
    {
        $this->addressBook = $addressBook;

        return $this;
    }

    public function getCardData(): ?string
    {
        return $this->cardData;
    }

    public function setCardData(?string $cardData): self
    {
        $this->cardData = $cardData;

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

    public function getLastModified(): ?int
    {
        return $this->lastModified;
    }

    public function setLastModified(?int $lastModified): self
    {
        $this->lastModified = $lastModified;

        return $this;
    }

    public function getEtag(): ?string
    {
        if (is_resource($this->etag)) {
            $this->etag = stream_get_contents($this->etag);
        }

        return $this->etag;
    }

    public function setEtag(?string $etag): self
    {
        $this->etag = $etag;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }
}
