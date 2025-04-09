<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity()]
#[ORM\Table(name: 'schedulingobjects')]
class SchedulingObject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(name: 'principaluri', type: 'string', length: 255, nullable: true)]
    private $principalUri;

    /**
     * The length corresponds to MEDIUMTEXT in MySQL.
     */
    #[ORM\Column(name: 'calendardata', type: 'text', length: 16777215, nullable: true)]
    private $calendarData;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Regex("/[0-9a-z\-]+/")]
    private $uri;

    #[ORM\Column(name: 'lastmodified', type: 'bigint', nullable: true)]
    private $lastModified;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $etag;

    #[ORM\Column(type: 'integer')]
    private $size;

    public function getId(): ?int
    {
        return $this->id;
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
