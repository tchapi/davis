<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class AdminUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /** @var non-empty-string */
    private string $username;
    private string $password;

    /**
     * @param non-empty-string $username
     */
    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * @return string[] The user roles
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
