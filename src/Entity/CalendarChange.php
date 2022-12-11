<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="calendarchanges")
 * @ORM\Entity()
 */
class CalendarChange
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
    private $uri;

    /**
     * @ORM\Column(type="integer")
     */
    private $synctoken;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Calendar", inversedBy="changes")
     * @ORM\JoinColumn(name="calendarid", nullable=false)
     */
    private $calendar;

    /**
     * @ORM\Column(type="smallint")
     */
    private $operation;

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

    public function getSynctoken(): ?int
    {
        return $this->synctoken;
    }

    public function setSynctoken(int $synctoken): self
    {
        $this->synctoken = $synctoken;

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

    public function getOperation(): ?int
    {
        return $this->operation;
    }

    public function setOperation(int $operation): self
    {
        $this->operation = $operation;

        return $this;
    }
}
