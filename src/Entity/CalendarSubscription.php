<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="calendarsubscriptions")
 * @ORM\Entity()
 */
class CalendarSubscription
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
     * @ORM\Column(name="principaluri", type="string", length=255)
     */
    private $principalUri;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $source;

    /**
     * @ORM\Column(name="displayname", type="string", length=255, nullable=true)
     */
    private $displayName;

    /**
     * @ORM\Column(name="refreshrate", type="string", length=10, nullable=true)
     */
    private $refreshRate;

    /**
     * @ORM\Column(name="calendarorder", type="integer")
     */
    private $calendarOrder;

    /**
     * @ORM\Column(name="calendarcolor", type="string", length=10, nullable=true)
     */
    private $calendarColor;

    /**
     * @ORM\Column(name="striptodos", type="smallint", nullable=true)
     */
    private $stripTodos;

    /**
     * @ORM\Column(name="stripalarms", type="smallint", nullable=true)
     */
    private $stripAlarms;

    /**
     * @ORM\Column(name="stripattachments", type="smallint", nullable=true)
     */
    private $stripAttachments;

    /**
     * @ORM\Column(name="lastmodified", type="integer", nullable=true)
     */
    private $lastModified;

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

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;

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

    public function getRefreshRate(): ?string
    {
        return $this->refreshRate;
    }

    public function setRefreshRate(?string $refreshRate): self
    {
        $this->refreshRate = $refreshRate;

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
        if (is_resource($this->calendarColor)) {
            $this->calendarColor = stream_get_contents($this->calendarColor);
        }

        return $this->calendarColor;
    }

    public function setCalendarColor(?string $calendarColor): self
    {
        $this->calendarColor = $calendarColor;

        return $this;
    }

    public function getStripTodos(): ?int
    {
        return $this->stripTodos;
    }

    public function setStripTodos(?int $stripTodos): self
    {
        $this->stripTodos = $stripTodos;

        return $this;
    }

    public function getStripAlarms(): ?int
    {
        return $this->stripAlarms;
    }

    public function setStripAlarms(?int $stripAlarms): self
    {
        $this->stripAlarms = $stripAlarms;

        return $this;
    }

    public function getStripAttachments(): ?int
    {
        return $this->stripAttachments;
    }

    public function setStripAttachments(?int $stripAttachments): self
    {
        $this->stripAttachments = $stripAttachments;

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
