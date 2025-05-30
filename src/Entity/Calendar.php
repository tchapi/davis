<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
#[ORM\Table(name: 'calendars')]
class Calendar
{
    public const COMPONENT_EVENTS = 'VEVENT';
    public const COMPONENT_TODOS = 'VTODO';
    public const COMPONENT_NOTES = 'VJOURNAL';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $synctoken;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $components;

    #[ORM\OneToMany(targetEntity: "App\Entity\CalendarObject", mappedBy: 'calendar')]
    private $objects;

    #[ORM\OneToMany(targetEntity: "App\Entity\CalendarChange", mappedBy: 'calendar')]
    private $changes;

    #[ORM\OneToMany(targetEntity: "App\Entity\CalendarInstance", mappedBy: 'calendar')]
    private $instances;

    public function __construct()
    {
        $this->synctoken = 1;
        $this->components = static::COMPONENT_EVENTS;
        $this->objects = new ArrayCollection();
        $this->changes = new ArrayCollection();
    }

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

    /**
     * @return Collection|CalendarObject[]
     */
    public function getObjects(): Collection
    {
        return $this->objects;
    }

    public function addObject(CalendarObject $object): self
    {
        if (!$this->objects->contains($object)) {
            $this->objects[] = $object;
            $object->setCalendar($this);
        }

        return $this;
    }

    public function removeObject(CalendarObject $object): self
    {
        if ($this->objects->contains($object)) {
            $this->objects->removeElement($object);
            // set the owning side to null (unless already changed)
            if ($object->getCalendar() === $this) {
                $object->setCalendar(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|CalendarChange[]
     */
    public function getChanges(): Collection
    {
        return $this->changes;
    }

    public function addChange(CalendarChange $change): self
    {
        if (!$this->changes->contains($change)) {
            $this->changes[] = $change;
            $change->setCalendar($this);
        }

        return $this;
    }

    public function removeChange(CalendarChange $change): self
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

    /**
     * @return Collection|CalendarInstance[]
     */
    public function getInstances(): Collection
    {
        return $this->instances;
    }
}
