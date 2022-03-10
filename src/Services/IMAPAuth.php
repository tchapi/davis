<?php

namespace App\Services;

use App\Entity\AddressBook;
use App\Entity\Calendar;
use App\Entity\CalendarInstance;
use App\Entity\Principal;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Sabre\DAV\Auth\Backend\IMAP;
use Symfony\Contracts\Translation\TranslatorInterface;

final class IMAPAuth extends IMAP
{
    /**
     * Doctrine registry.
     *
     * @var \Doctrine\Persistence\ManagerRegistry
     */
    private $doctrine;

    /**
     * The translation service.
     *
     * @var \Symfony\Contracts\Translation\TranslatorInterface
     */
    private $trans;

    /**
     * Should we auto create the user upon successful
     * login if it does not exist yet.
     *
     * @var bool
     */
    private $autoCreate;

    public function __construct(ManagerRegistry $doctrine, TranslatorInterface $trans, string $IMAPAuthUrl, bool $autoCreate)
    {
        parent::__construct($IMAPAuthUrl);

        $this->doctrine = $doctrine;
        $this->trans = $trans;
        $this->autoCreate = $autoCreate;
    }

    /**
     * Connects to an IMAP server and tries to authenticate.
     * If the user does not exist, create it.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    protected function imapOpen($username, $password)
    {
        $success = parent::imapOpen($username, $password);

        // Auto-create the user if it does not already exist in the database
        if ($success && $this->autoCreate) {
            $user = $this->doctrine->getRepository(User::class)->findOneBy(['username' => $username]);

            if (!$user) {
                // We only have this
                $displayName = $username;
                $email = $username;

                $user = new User();
                $user->setUsername($username);

                // FIXME: Should we need to set the password here ?
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $user->setPassword($hash);
                // ------------------------------------------------

                // Create principal, default calendar and addressbook
                $principal = new Principal();
                $principal->setUri(Principal::PREFIX.$username)
                          ->setDisplayName($displayName)
                          ->setEmail($email)
                          ->setIsAdmin(false);

                $calendarInstance = new CalendarInstance();
                $calendar = new Calendar();
                $calendarInstance->setPrincipalUri(Principal::PREFIX.$username)
                         ->setUri('default') // No risk of collision since unicity is guaranteed by the new user principal
                         ->setDisplayName($this->trans->trans('default.calendar.title'))
                         ->setDescription($this->trans->trans('default.calendar.description', ['user' => $displayName]))
                         ->setCalendar($calendar);

                // Enable delegation by default
                $principalProxyRead = new Principal();
                $principalProxyRead->setUri($principal->getUri().Principal::READ_PROXY_SUFFIX)
                                   ->setIsMain(false);

                $principalProxyWrite = new Principal();
                $principalProxyWrite->setUri($principal->getUri().Principal::WRITE_PROXY_SUFFIX)
                                   ->setIsMain(false);

                $addressbook = new AddressBook();
                $addressbook->setPrincipalUri(Principal::PREFIX.$username)
                         ->setUri('default') // No risk of collision since unicity is guaranteed by the new user principal
                         ->setDisplayName($this->trans->trans('default.addressbook.title'))
                         ->setDescription($this->trans->trans('default.addressbook.description', ['user' => $displayName]));

                // Persist all items
                $em = $this->doctrine->getManager();
                $em->persist($principalProxyRead);
                $em->persist($principalProxyWrite);
                $em->persist($calendarInstance);
                $em->persist($addressbook);
                $em->persist($principal);
                $em->persist($user);

                $em->flush();
            }
        }

        return $success;
    }
}
