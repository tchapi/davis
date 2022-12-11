<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="addressbooks")
 * @ORM\Entity()
 * @UniqueEntity(
 *     fields={"principalUri", "uri"},
 *     errorPath="uri",
 *     message="form.uri.unique"
 * )
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
     * @ORM\Column(name="principaluri", type="string", length=255)
     */
    private $principalUri;

    /**
     * @ORM\Column(name="displayname", type="string", length=255)
     */
    private $displayName;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\Regex("/[0-9a-z\-]+/")
     */
    private $uri;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $synctoken;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Card", mappedBy="addressBook")
     */
    private $cards;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\AddressBookChange", mappedBy="addressBook")
     */
    private $changes;

    public function __construct()
    {
        $this->synctoken = 1;
        $this->cards = new ArrayCollection();
        $this->changes = new ArrayCollection();
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

    /**
     * @return Collection|Card[]
     */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    public function addCard(Card $card): self
    {
        if (!$this->cards->contains($card)) {
            $this->cards[] = $card;
            $card->setAddressBook($this);
        }

        return $this;
    }

    public function removeCard(Card $card): self
    {
        if ($this->cards->contains($card)) {
            $this->cards->removeElement($card);
            // set the owning side to null (unless already changed)
            if ($card->getAddressBook() === $this) {
                $card->setAddressBook(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|AddressBookChange[]
     */
    public function getChanges(): Collection
    {
        return $this->changes;
    }

    public function addChange(AddressBookChange $change): self
    {
        if (!$this->changes->contains($change)) {
            $this->changes[] = $change;
            $change->setCalendar($this);
        }

        return $this;
    }

    public function removeChange(AddressBookChange $change): self
    {
        if ($this->changes->contains($change)) {
            $this->changes->removeElement($change);
            // set the owning side to null (unless already changed)
            if ($change->getCalendar() === $this) {
                $change->setCalendar(null);
            }
        }

        return $this;
    }
}
