<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="schedulingobjects")
 * @ORM\Entity()
 */
class SchedulingObject
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(name="principaluri", type="string", length=255, nullable=true)
     */
    private $principalUri;

    /**
     * @ORM\Column(name="calendardata", type="blob", nullable=true)
     */
    private $calendarData;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Regex("/[0-9a-z\-]+/")
     */
    private $uri;

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

    public function setPrincipalUri(?string $principalUri): self
    {
        $this->principalUri = $principalUri;

        return $this;
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
