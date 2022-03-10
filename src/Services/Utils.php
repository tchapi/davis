<?php

namespace App\Services;

use App\Entity\User;

final class Utils
{
    /**
     * Authentication realm.
     *
     * @var string
     */
    private $authRealm;

    public function __construct(?string $authRealm)
    {
        $this->authRealm = $authRealm ?? User::DEFAULT_AUTH_REALM;
    }

    /**
     * Hash a password according to the realm.
     */
    public function hashPassword(string $username, string $password): string
    {
        return md5($username.':'.$this->authRealm.':'.$password);
    }
}
