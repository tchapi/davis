<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: "App\Repository\CalendarInstanceRepository")]
#[ORM\Table(name: 'calendarinstances')]
#[UniqueEntity(fields: ['principalUri', 'uri'], errorPath: 'uri', message: 'form.uri.unique')]
class CalendarInstance
{
    public const INVITE_NORESPONSE = 1;
    public const INVITE_ACCEPTED = 2;
    public const INVITE_DECLINED = 3;
    public const INVITE_INVALID = 4;

    public const ACCESS_NOTSHARED = 0;
    public const ACCESS_SHAREDOWNER = 1;
    public const ACCESS_READ = 2;
    public const ACCESS_READWRITE = 3;
    public const ACCESS_NOACCESS = 4;

    // Used to identify a public calendar, available to anyone without logging in.
    // It can't be shared, and it's owned by the principal.
    public const ACCESS_PUBLIC = 10;

    public static function getOwnerAccesses(): array
    {
        return [
            self::ACCESS_NOTSHARED,
            self::ACCESS_SHAREDOWNER,
            self::ACCESS_PUBLIC,
        ];
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: "App\Entity\Calendar", cascade: ['persist'], inversedBy: 'instances')]
    #[ORM\JoinColumn(name: 'calendarid', nullable: false)]
    private $calendar;

    #[ORM\Column(name: 'principaluri', type: 'string', length: 255, nullable: true)]
    private $principalUri;

    #[ORM\Column(type: 'smallint', options: ['default' => 1])]
    private $access;

    #[ORM\Column(name: 'displayname', type: 'string', length: 255, nullable: true)]
    private $displayName;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Regex("/[0-9a-z\-]+/")]
    private $uri;

    #[ORM\Column(type: 'text', nullable: true)]
    private $description;

    #[ORM\Column(name: 'calendarorder', type: 'integer', options: ['default' => 0])]
    private $calendarOrder;

    #[ORM\Column(name: 'calendarcolor', type: 'string', length: 10, nullable: true)]
    #[Assert\Regex("/\#[0-9A-F]{6}/")]
    private $calendarColor;

    #[ORM\Column(type: 'text', nullable: true)]
    private $timezone;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $transparent;

    #[ORM\Column(name: 'share_href', type: 'string', length: 255, nullable: true)]
    private $shareHref;

    #[ORM\Column(name: 'share_displayname', type: 'string', length: 255, nullable: true)]
    private $shareDisplayName;

    #[ORM\Column(name: 'share_invitestatus', type: 'integer', options: ['default' => 2])]
    private $shareInviteStatus;

    public function __construct()
    {
        $this->shareInviteStatus = self::INVITE_ACCEPTED;
        $this->transparent = 0;
        $this->calendarOrder = 0;
        $this->access = self::ACCESS_SHAREDOWNER;
    }

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

    public function isShared(): bool
    {
        return !in_array($this->access, self::getOwnerAccesses());
    }

    public function isPublic(): bool
    {
        return self::ACCESS_PUBLIC === $this->access;
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
