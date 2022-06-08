<?php

namespace App\Plugins;

use DantSu\OpenStreetMapStaticAPI\LatLng;
use DantSu\OpenStreetMapStaticAPI\Markers;
use DantSu\OpenStreetMapStaticAPI\OpenStreetMap;
use Sabre\CalDAV\Schedule\IMipPlugin as SabreBaseIMipPlugin;
use Sabre\DAV;
use Sabre\VObject\ITip;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * iMIP handler.
 */
final class DavisIMipPlugin extends SabreBaseIMipPlugin
{
    public const MESSAGE_ORIGIN_INDICATOR = '(via Davis)';

    /**
     * @var MailerInterface
     */
    private $mailer;

    /**
     * @var string
     */
    protected $senderEmail;

    /**
     * @var string
     */
    protected $publicDir;

    /**
     * Creates the email handler.
     *
     * @param string $senderEmail. The 'senderEmail' is the email that shows up
     *                             in the 'From:' address. This should
     *                             generally be some kind of no-reply email
     *                             address you own.
     * @param string $publicDir.   The directory where public images are stored.
     */
    public function __construct(MailerInterface $mailer, string $senderEmail, string $publicDir)
    {
        $this->mailer = $mailer;
        $this->senderEmail = $senderEmail;
        $this->publicDir = $publicDir;
    }

    /**
     * Event handler for the 'schedule' event.
     */
    public function schedule(ITip\Message $itip)
    {
        // Not sending any emails if the system considers the update
        // insignificant.
        if (!$itip->significantChange) {
            if (empty($itip->scheduleStatus)) {
                $itip->scheduleStatus = '1.0;We got the message, but it\'s not significant enough to warrant an email';
            }

            return;
        }

        $summary = $itip->message->VEVENT->SUMMARY;

        if ('mailto' !== parse_url($itip->sender, PHP_URL_SCHEME) ||
            'mailto' !== parse_url($itip->recipient, PHP_URL_SCHEME)) {
            return;
        }

        $deliveredLocally = '1.2' === $itip->getScheduleStatus();

        $senderName = $itip->senderName;
        $recipientName = $itip->recipientName;

        // 7 is the length of `mailto:`.
        $senderEmail = substr($itip->sender, 7);
        $recipientEmail = substr($itip->recipient, 7);

        $subject = 'CalDAV message';
        switch (strtoupper($itip->method)) {
            case 'REPLY':
                // In the case of a reply, we need to find the `PARTSTAT` from
                // the user.
                $partstat = (string) $itip->message->VEVENT->ATTENDEE['PARTSTAT'];
                switch (strtoupper($partstat)) {
                    case 'DECLINED':
                        $subject = $senderName.' declined your invitation to "'.$summary.'"';
                        $action = 'DECLINED';
                        break;
                    case 'ACCEPTED':
                        $subject = $senderName.' accepted your invitation to "'.$summary.'"';
                        $action = 'ACCEPTED';
                        break;
                    case 'TENTATIVE':
                        $subject = $senderName.' tentatively accepted your invitation to "'.$summary.'"';
                        $action = 'TENTATIVE';
                        break;
                    default:
                        $itip->scheduleStatus = '5.0;Email not delivered. We didn\'t understand this PARTSTAT.';

                        return;
                }

                break;
            case 'REQUEST':
                $subject = $senderName.' invited you to "'.$summary.'"';
                $action = 'REQUEST';
                break;
            case 'CANCEL':
                $subject = '"'.$summary.'" has been canceled.';
                $action = 'CANCEL';
                break;
        }

        // Construct objects for the mail template
        $dateTime =
            isset($itip->message->VEVENT->DTSTART)
                ? $itip->message->VEVENT->DTSTART->getDateTime()
                : new \DateTime('now');

        $allDay =
            isset($itip->message->VEVENT->DTSTART) &&
            false === $itip->message->VEVENT->DTSTART->hasTime();

        $attendees = [];
        if (isset($itip->message->VEVENT->ATTENDEE)) {
            $_attendees = &$itip->message->VEVENT->ATTENDEE;
            for ($i = 0, $max = count($_attendees); $i < $max; ++$i) {
                $attendee = $_attendees[$i];
                $attendees[] = [
                    'cn' => isset($attendee['CN'])
                            ? (string) $attendee['CN']
                            : (string) $attendee['EMAIL'],
                    'email' => isset($attendee['EMAIL'])
                            ? (string) $attendee['EMAIL']
                            : null,
                    'role' => isset($attendee['ROLE'])
                            ? (string) $attendee['ROLE']
                            : null,
                ];
            }
            usort($attendees, function ($a, $b) {
                if ('CHAIR' === $a['role']) {
                    return -1;
                }

                return 1;
            });
        }

        $notEmpty = function ($property, $else) use ($itip) {
            if (isset($itip->message->VEVENT->$property)) {
                $handle = (string) $itip->message->VEVENT->$property;
                if (!empty($handle)) {
                    return $handle;
                }
            }

            return $else;
        };

        $url = $notEmpty('URL', false);
        $description = $notEmpty('DESCRIPTION', false);
        $location = $notEmpty('LOCATION', false);
        $locationImageDataAsBase64 = false;
        $locationLink = false;

        if (isset($itip->message->VEVENT->{'X-APPLE-STRUCTURED-LOCATION'})) {
            $match = preg_match(
                '/^(geo:)?(?<latitude>\-?\d+\.\d+),(?<longitude>\-?\d+\.\d+)$/',
                (string) $itip->message->VEVENT->{'X-APPLE-STRUCTURED-LOCATION'},
                $coordinates
            );
            if (0 !== $match) {
                $zoom = 16;
                $width = 500;
                $height = 220;

                $latLng = new LatLng($coordinates['latitude'], $coordinates['longitude']);

                // https://github.com/DantSu/php-osm-static-api
                $locationImageDataAsBase64 = (new OpenStreetMap($latLng, $zoom, $width, $height))
                    ->addMarkers(
                        (new Markers($this->publicDir.'/images/marker.png'))
                            ->setAnchor(Markers::ANCHOR_CENTER, Markers::ANCHOR_BOTTOM)
                            ->addMarker(new LatLng($coordinates['latitude'], $coordinates['longitude']))
                    )
                    ->getImage()
                    ->getBase64PNG();

                $locationLink =
                    'https://www.openstreetmap.org'.
                    '/?mlat='.$coordinates['latitude'].
                    '&mlon='.$coordinates['longitude'].
                    '#map='.$zoom.
                    '/'.$coordinates['latitude'].
                    '/'.$coordinates['longitude'];
            }
        }

        $message = (new TemplatedEmail())
            ->from(new Address($this->senderEmail, $senderName.' '.static::MESSAGE_ORIGIN_INDICATOR))
            ->to(new Address($recipientEmail, $recipientName ?? ''))
            ->replyTo(new Address($senderEmail, $senderName ?? ''))
            ->subject($subject);

        if (DAV\Server::$exposeVersion) {
            $message->getHeaders()
                    ->addTextHeader('X-Sabre-Version: ', DAV\Version::VERSION)
                    ->addTextHeader('X-Auto-Response-Suppress', 'OOF, DR, RN, NRN, AutoReply');
        }

        // Now that we have everything, we can set the message body
        $message->htmlTemplate('mails/scheduling.html.twig')
                ->textTemplate('mails/scheduling.txt.twig')
                ->context([
                    'senderName' => $senderName,
                    'summary' => $summary,
                    'action' => $action,
                    'dateTime' => $dateTime,
                    'allDay' => $allDay,
                    'attendees' => $attendees,
                    'location' => $location,
                    'locationImageDataAsBase64' => $locationImageDataAsBase64,
                    'locationLink' => $locationLink,
                    'url' => $url,
                    'description' => $description,
                ]);

        if (false === $deliveredLocally) {
            // Attach the event file (invite.ics)
            $message->attach($itip->message->serialize(), 'invite.ics', 'text/calendar; method='.(string) $itip->method.'; charset=UTF-8');
        }

        $this->mailer->send($message);

        if (false === $deliveredLocally) {
            $itip->scheduleStatus = '1.1;Scheduling message is sent via iMip.';
        }
    }

    /**
     * Returns a bunch of meta-data about the plugin.
     *
     * Providing this information is optional, and is mainly displayed by the
     * Browser plugin.
     *
     * The description key in the returned array may contain html and will not
     * be sanitized.
     *
     * @return array
     */
    public function getPluginInfo()
    {
        return [
            'name' => $this->getPluginName(),
            'description' => 'HTML Email delivery (rfc6047) for CalDAV scheduling',
            'link' => 'http://github.com/tchapi/davis',
        ];
    }
}
