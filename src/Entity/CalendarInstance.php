<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="calendarinstances")
 * @ORM\Entity(repositoryClass="App\Repository\CalendarInstanceRepository")
 */
class CalendarInstance
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Calendar")
     * @ORM\JoinColumn(name="calendarid", nullable=false)
     */
    private $calendar;

    /**
     * @ORM\Column(name="principaluri", type="binary", length=255, nullable=true)
     */
    private $principalUri;

    /**
     * @ORM\Column(type="smallint")
     */
    private $access;

    /**
     * @ORM\Column(name="displayname", type="string", length=255, nullable=true)
     */
    private $displayName;

    /**
     * @ORM\Column(type="binary", length=255, nullable=true)
     */
    private $uri;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\Column(name="calendarorder", type="integer")
     */
    private $calendarOrder;

    /**
     * @ORM\Column(name="calendarcolor", type="binary", length=10, nullable=true)
     */
    private $calendarColor;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $timezone;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $transparent;

    /**
     * @ORM\Column(name="share_href", type="binary", length=255, nullable=true)
     */
    private $shareHref;

    /**
     * @ORM\Column(name="share_displayname", type="string", length=255, nullable=true)
     */
    private $shareDisplayName;

    /**
     * @ORM\Column(name="share_invitestatus", type="integer")
     */
    private $shareInviteStatus;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPrincipalUri(): ?string
    {
        return $this->principalUri;
    }

    public function setPrincipalUri(?string $principalUri): self
    {
        $this->principalUri = $principalUri;

        return $this;
    }

    public function getAccess(): ?int
    {
        return $this->access;
    }

    public function setAccess(int $access): self
    {
        $this->access = $access;

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

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setUri(?string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCalendarOrder(): ?int
    {
        return $this->calendarOrder;
    }

    public function setCalendarOrder(int $calendarOrder): self
    {
        $this->calendarOrder = $calendarOrder;

        return $this;
    }

    public function getCalendarColor(): ?string
    {
        return $this->calendarColor;
    }

    public function setCalendarColor(?string $calendarColor): self
    {
        $this->calendarColor = $calendarColor;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getTransparent(): ?int
    {
        return $this->transparent;
    }

    public function setTransparent(?int $transparent): self
    {
        $this->transparent = $transparent;

        return $this;
    }

    public function getShareHref(): ?string
    {
        return $this->shareHref;
    }

    public function setShareHref(?string $shareHref): self
    {
        $this->shareHref = $shareHref;

        return $this;
    }

    public function getShareDisplayName(): ?string
    {
        return $this->shareDisplayName;
    }

    public function setShareDisplayName(?string $shareDisplayName): self
    {
        $this->shareDisplayName = $shareDisplayName;

        return $this;
    }

    public function getShareInviteStatus(): ?int
    {
        return $this->shareInviteStatus;
    }

    public function setShareInviteStatus(int $shareInviteStatus): self
    {
        $this->shareInviteStatus = $shareInviteStatus;

        return $this;
    }
}
