<?php

namespace App\Plugins;

use Sabre\CalDAV\Schedule\IMipPlugin as SabreBaseIMipPlugin;
use Sabre\DAV;
use Sabre\VObject\ITip;

/**
 * iMIP handler.
 */
class DavisIMipPlugin extends SabreBaseIMipPlugin
{
    const MESSAGE_ORIGIN_INDICATOR = '(via Davis)';

    /**
     * Creates the email handler.
     *
     * @param string $senderEmail. The 'senderEmail' is the email that shows up
     *                             in the 'From:' address. This should
     *                             generally be some kind of no-reply email
     *                             address you own.
     */
    public function __construct($senderEmail)
    {
        $this->senderEmail = $senderEmail;
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
                        $itip->scheduleStatus = '5.0;Email not deliered. We didn\'t understand this PARTSTAT.';

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
        $locationImage = null;
        $locationImageContentId = false;
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
                $locationImage = Swift_Image::fromPath(
                        'http://api.tiles.mapbox.com/v4'.
                        '/mapbox.streets'.
                        '/pin-m-star+285A98'.
                        '('.$coordinates['longitude'].
                        ','.$coordinates['latitude'].
                        ')'.
                        '/'.$coordinates['longitude'].
                        ','.$coordinates['latitude'].
                        ','.$zoom.
                        '/'.$width.'x'.$height.'.png'.
                        '?access_token=pk.eyJ1IjoiZHRvYnNjaGFsbCIsImEiOiIzMzdhNTRhNGNjOGFjOGQ4MDM5ZTJhNGZjYjNmNmE5OCJ9.7ZQOdfvoZW0XIbvjN54Wrg')
                ->setFilename('event_map.png')
                ->setContentType('image/png');
                $locationLink =
                    'http://www.openstreetmap.org'.
                    '/?mlat='.$coordinates['latitude'].
                    '&mlon='.$coordinates['longitude'].
                    '#map='.$zoom.
                    '/'.$coordinates['latitude'].
                    '/'.$coordinates['longitude'];
            }
        }

        $message = (new \Swift_Message($subject))
            ->setFrom([$this->senderEmail.' '.static::MESSAGE_ORIGIN_INDICATOR => $itip->senderName])
            ->setTo([$recipientEmail => $recipientName])
            ->setReplyTo([$senderEmail => $senderName])
            ->setContentType('Content-Type: text/calendar; charset=UTF-8; method='.$itip->method);

        if (DAV\Server::$exposeVersion) {
            $headers = $message->getHeaders();
            $headers->addTextHeader('X-Sabre-Version: ', DAV\Version::VERSION);
        }

        if (null !== $locationImage) {
            $locationImageContentId = $message->embed($locationImage);
        }

        // Now that we have everything, we can set the message body
        $params = [
            'senderName' => $senderName,
            'summary' => $summary,
            'action' => $action,
            'dateTime' => $dateTime,
            'allDay' => $allDay,
            'attendees' => $attendees,
            'location' => $location,
            'locationImageContentId' => $locationImageContentId,
            'locationLink' => $locationLink,
            'url' => $url,
            'description' => $description,
        ];

        $message->setBody(
            $this->renderView(
                'mails/scheduling.html.twig',
                $params
            ),
            'text/html'
        )
        ->addPart(
            $this->renderView(
                'mails/scheduling.txt.twig',
                $params
            ),
            'text/plain'
        );

        if (false === $deliveredLocally) {
            $bodyAsStream = new Stringbuffer\Read();
            $bodyAsStream->initializeWith($itip->message->serialize());

            // Attach the event file (invite.ics)
            $attachment = (new Swift_Attachment())
                  ->setFilename('invite.ics')
                  ->setContentType('text/calendar; method='.(string) $itip->method.'; charset=UTF-8')
                  ->setBody($bodyAsStream);
            $message->attach($attachment);
        }

        $mailer->send($message);

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
