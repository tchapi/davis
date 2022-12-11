<?php

namespace App\Services;

use App\Entity\AddressBook;
use App\Entity\Calendar;
use App\Entity\CalendarInstance;
use App\Entity\Principal;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;

final class Utils
{
    /**
     * Authentication realm.
     *
     * @var string
     */
    private $authRealm;

    /**
     * The translation service.
     *
     * @var \Symfony\Contracts\Translation\TranslatorInterface
     */
    private $trans;

    /**
     * Doctrine registry.
     *
     * @var \Doctrine\Persistence\ManagerRegistry
     */
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine, TranslatorInterface $trans, ?string $authRealm)
    {
        $this->authRealm = $authRealm ?? User::DEFAULT_AUTH_REALM;
        $this->trans = $trans;
        $this->doctrine = $doctrine;
    }

    /**
     * Hash a password according to the realm.
     * Important note: It is very insecure and this is used only for the legacy sabre/dav implementation.
     */
    public function hashPassword(string $username, string $password): string
    {
        return md5($username.':'.$this->authRealm.':'.$password);
    }

    public function createUserWithDefaultObjects(string $username, string $password, string $displayName, string $email)
    {
        $user = new User();
        $user->setUsername($username);

        // Set the password (but hashed beforehand)
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $user->setPassword($hash);

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
    }
}
