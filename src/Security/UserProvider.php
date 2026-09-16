<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    private $doctrine;

    private $adminLogin;
    private $adminPassword;

    public function __construct(ManagerRegistry $doctrine, string $adminLogin, string $adminPassword)
	{
		$this->doctrine = $doctrine;
        $this->adminLogin = $adminLogin;
        $this->adminPassword = $adminPassword;
    }

    /**
     * Symfony calls this method if you use features like switch_user
     * or remember_me.
     *
     * If you're not using these features, you do not need to implement
     * this method.
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
		if ($identifier == $this->adminLogin) {
        	return new AdminUser($identifier, bin2hex(random_bytes(64)));
		}

		$user = $this->doctrine->getRepository(User::class)->findOneByUsername($identifier);
		if (!$user) {
            // instead of throwing an exception, return a fake user: this will
            // fail during authentication since the user does not exist
            return new NormalUser($identifier, '', 0);
		}

        return new NormalUser($identifier, $user->getPassword(), $user->getId());
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
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if ((!$user instanceof AdminUser) && (!$user instanceof NormalUser)) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $user;
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass($class): bool
    {
        return (AdminUser::class === $class) || (NormalUser::class === $class);
    }
}
