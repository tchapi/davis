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
     * @var TranslatorInterface
     */
    private $trans;

    /**
     * Doctrine registry.
     *
     * @var ManagerRegistry
     */
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine, TranslatorInterface $trans, ?string $authRealm)
    {
        $this->authRealm = $authRealm ?? User::DEFAULT_AUTH_REALM;
        $this->trans = $trans;
        $this->doctrine = $doctrine;
    }

    public function getRandomBytes($nbBytes = 32)
    {
        $bytes = openssl_random_pseudo_bytes($nbBytes, $strong);
        if (false !== $bytes && true === $strong) {
            return $bytes;
        }
        else {
            throw new \Exception("Unable to generate secure token from OpenSSL.");
        }
    }

    public function generatePassword($length){
        return substr(preg_replace("/[^a-zA-Z0-9]/", "", base64_encode($this->getRandomBytes($length+1))),0,$length);
    }

    public function createPasswordlessUserWithDefaultObjects(string $username, string $displayName, string $email)
    {
        $user = new User();
        $user->setUsername($username);

        // Set the password to a secure randomly generated password
        $user->setPassword($this->generatePassword(12));

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
