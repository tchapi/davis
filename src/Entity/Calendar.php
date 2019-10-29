<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="calendars")
 * @ORM\Entity(repositoryClass="App\Repository\CalendarRepository")
 */
class Calendar
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $synctoken;

    /**
     * @ORM\Column(type="binary", length=255, nullable=true)
     */
    private $components;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getComponents(): ?string
    {
        return $this->components;
    }

    public function setComponents(?string $components): self
    {
        $this->components = $components;

        return $this;
    }
}
