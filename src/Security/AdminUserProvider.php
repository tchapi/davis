<?php

namespace App\Security;

use App\Entity\AdminUser;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class AdminUserProvider implements UserProviderInterface
{
    /**
     * Symfony calls this method if you use features like switch_user
     * or remember_me.
     *
     * If you're not using these features, you do not need to implement
     * this method.
     *
     * @param mixed $username
     *
     * @throws UsernameNotFoundException if the user is not found
     *
     * @return UserInterface
     */
    public function loadUserByUsername($username)
    {
        throw new \Exception('Not implemented, because not needed');
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new \Exception('Not implemented, because not needed');
    }

    /**
     * Refreshes the user after being reloaded from the session.
     *
     * When a user is logged in, at the beginning of each request, the
     * User object is loaded from the session and then this method is
     * called. Your job is to make sure the user's data is still fresh by,
     * for example, re-querying for fresh User data.
     *
     * If your firewall is "stateless: true" (for a pure API), this
     * method is not called.
     *
     * @return UserInterface
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof AdminUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $user;
    }

    /**
     * Tells Symfony to use this provider for this User class.
     *
     * @param mixed $class
     */
    public function supportsClass($class): bool
    {
        return AdminUser::class === $class;
    }
}
