<?php

namespace App\Services;

use App\Entity\AddressBook;
use App\Entity\Calendar;
use App\Entity\CalendarInstance;
use App\Entity\CalendarSubscription;
use App\Entity\Principal;
use App\Entity\SchedulingObject;
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

    /**
     * Hash a password according to the realm.
     * Important note: It is very insecure and this is used only for the legacy sabre/dav implementation.
     */
    public function hashPassword(string $username, string $password): string
    {
        return md5($username.':'.$this->authRealm.':'.$password);
    }

    /**
     * A username is only acceptable if it can be used verbatim in a principal URI.
     */
    public static function isValidUsername(?string $username): bool
    {
        return null !== $username && '' !== $username && 1 === preg_match(User::USERNAME_PATTERN, $username);
    }

    public function createPasswordlessUserWithDefaultObjects(string $username, string $displayName, string $email)
    {
        // Set the password to a random string (but hashed beforehand)
        $password = substr(bin2hex(random_bytes(256)), 0, 48);
        $hash = password_hash($password, PASSWORD_DEFAULT);

        return $this->createUserWithDefaultObjects($username, $displayName, $email, $hash, false);
    }

    /**
     * Return the new user, persisted in the database.
     */
    public function createUserWithDefaultObjects(string $username, string $displayName, string $email, string $hashed_password, bool $isAdmin)
    {
        if (!self::isValidUsername($username)) {
            throw new \InvalidArgumentException(sprintf('Refusing to create the user "%s": a username may only contain letters, digits and the characters _ . @ + \' -', $username));
        }

        $user = new User();
        $user->setUsername($username);
        $user->setPassword($hashed_password);

        $this->createDefaultObjectsForUser($user, $displayName, $email, $isAdmin);

        $em = $this->doctrine->getManager();
        $em->persist($user);

        return $user;
    }

    /**
     * Create the default objects for a new user: principal, default calendar
     * and addressbook, all persisted in the database.
     *
     * Return the new principal
     */
    public function createDefaultObjectsForUser(User $user, string $displayName, string $email, bool $isAdmin)
    {
        $principal = new Principal();
        $principal->setUri($user->getPrincipalUri())
                ->setDisplayName($displayName)
                ->setEmail($email)
                ->setIsAdmin($isAdmin);

        $calendarInstance = new CalendarInstance();
        $calendar = new Calendar();
        $calendarInstance->setPrincipalUri($user->getPrincipalUri())
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
        $addressbook->setPrincipalUri($user->getPrincipalUri())
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

        return $principal;
    }

    public function deleteUser(User $user)
    {
        $entityManager = $this->doctrine->getManager();
        $entityManager->remove($user);

        $principal = $this->doctrine->getRepository(Principal::class)->findOneByUri($user->getPrincipalUri());
        if (!$principal) {
            throw new \Exception('Principal is null');
        }

        $principalProxyRead = $this->doctrine->getRepository(Principal::class)->findOneByUri($principal->getUri().Principal::READ_PROXY_SUFFIX);
        $principalProxyWrite = $this->doctrine->getRepository(Principal::class)->findOneByUri($principal->getUri().Principal::WRITE_PROXY_SUFFIX);

        $entityManager->remove($principal);

        if ($principalProxyRead) {
            $entityManager->remove($principalProxyRead);
        }

        if ($principalProxyWrite) {
            $entityManager->remove($principalProxyWrite);
        }

        $principalUri = $user->getPrincipalUri();

        // Remove calendars and addressbooks
        $calendars = $this->doctrine->getRepository(CalendarInstance::class)->findByPrincipalUriWithCalendars($principalUri);
        foreach ($calendars ?? [] as $instance) {
            // We're only removing the calendar objects / changes / and calendar if the deleted user is an owner,
            // which means that the underlying calendar instance should not have another principal as owner.
            $hasDifferentOwner = $this->doctrine->getRepository(CalendarInstance::class)->hasDifferentOwner($instance->getCalendar()->getId(), $principalUri);
            if (!$hasDifferentOwner) {
                foreach ($instance->getCalendar()->getObjects() ?? [] as $object) {
                    $entityManager->remove($object);
                }
                foreach ($instance->getCalendar()->getChanges() ?? [] as $change) {
                    $entityManager->remove($change);
                }
                // We need to remove the shared versions of this calendar, too
                foreach ($instance->getCalendar()->getInstances() ?? [] as $instances) {
                    $entityManager->remove($instances);
                }
                $entityManager->remove($instance->getCalendar());
            }
            $entityManager->remove($instance);
        }
        $calendarsSubscriptions = $this->doctrine->getRepository(CalendarSubscription::class)->findByPrincipalUri($principalUri);
        foreach ($calendarsSubscriptions ?? [] as $subscription) {
            $entityManager->remove($subscription);
        }
        $schedulingObjects = $this->doctrine->getRepository(SchedulingObject::class)->findByPrincipalUri($principalUri);
        foreach ($schedulingObjects ?? [] as $object) {
            $entityManager->remove($object);
        }

        $addressbooks = $this->doctrine->getRepository(AddressBook::class)->findByPrincipalUri($principalUri);
        foreach ($addressbooks ?? [] as $addressbook) {
            foreach ($addressbook->getCards() ?? [] as $card) {
                $entityManager->remove($card);
            }
            foreach ($addressbook->getChanges() ?? [] as $change) {
                $entityManager->remove($change);
            }
            $entityManager->remove($addressbook);
        }
    }
}
