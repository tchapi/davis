<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AdminUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    private $username;
    private $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @return (Role|string)[] The user roles
     */
    public function getRoles(): array
    {
        return ['ROLE_ADMIN'];
    }

    /**
     * Returns the password used to authenticate the user.
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string|null The salt
     */
    public function getSalt()
    {
        return null;
    }

    /**
     * Returns the username used to authenticate the user.
     *
     * @return string The username
     */
    public function getUsername()
    {
        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /**
     * Nothing to erase: the password this object carries is a throwaway value, never the
     * configured one. The attribute is how Symfony wants an empty implementation declared.
     */
    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }
}
