<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="calendarobjects")
 *
 * @ORM\Entity()
 */
class CalendarObject
{
    /**
     * @ORM\Id()
     *
     * @ORM\GeneratedValue()
     *
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(name="calendardata", type="text", nullable=true, length=16777215)
     * The length corresponds to MEDIUMTEXT in MySQL
     */
    private $calendarData;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $uri;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Calendar", inversedBy="objects")
     *
     * @ORM\JoinColumn(name="calendarid", nullable=false)
     */
    private $calendar;

    /**
     * @ORM\Column(name="lastmodified", type="integer", nullable=true)
     */
    private $lastModified;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $etag;

    /**
     * @ORM\Column(type="integer")
     */
    private $size;

    /**
     * @ORM\Column(name="componenttype", type="string", length=255, nullable=true)
     */
    private $componentType;

    /**
     * @ORM\Column(name="firstoccurence", type="integer", nullable=true)
     */
    private $firstOccurence;

    /**
     * @ORM\Column(name="lastoccurence", type="integer", nullable=true)
     */
    private $lastOccurence;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $uid;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCalendarData(): ?string
    {
        return $this->calendarData;
    }

    public function setCalendarData(?string $calendarData): self
    {
        $this->calendarData = $calendarData;

        return $this;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setUri(?string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    public function getCalendar(): ?Calendar
    {
        return $this->calendar;
    }

    public function setCalendar(?Calendar $calendar): self
    {
        $this->calendar = $calendar;

        return $this;
    }

    public function getLastModifier(): ?int
    {
        return $this->lastModifier;
    }

    public function setLastModifier(?int $lastModifier): self
    {
        $this->lastModifier = $lastModifier;

        return $this;
    }

    public function getEtag(): ?string
    {
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

    public function getComponentType(): ?string
    {
        return $this->componentType;
    }

    public function setComponentType(?string $componentType): self
    {
        $this->componentType = $componentType;

        return $this;
    }

    public function getFirstOccurence(): ?int
    {
        return $this->firstOccurence;
    }

    public function setFirstOccurence(?int $firstOccurence): self
    {
        $this->firstOccurence = $firstOccurence;

        return $this;
    }

    public function getLastOccurence(): ?int
    {
        return $this->lastOccurence;
    }

    public function setLastOccurence(?int $lastOccurence): self
    {
        $this->lastOccurence = $lastOccurence;

        return $this;
    }

    public function getUid(): ?string
    {
        return $this->uid;
    }

    public function setUid(?string $uid): self
    {
        $this->uid = $uid;

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
}
