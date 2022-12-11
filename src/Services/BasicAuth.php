<?php

namespace App\Services;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Sabre\DAV\Auth\Backend\AbstractBasic;

final class BasicAuth extends AbstractBasic
{
    /**
     * Utils class.
     *
     * @var Utils
     */
    private $utils;

    /**
     * Doctrine registry.
     *
     * @var \Doctrine\Persistence\ManagerRegistry
     */
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine, Utils $utils)
    {
        $this->utils = $utils;
        $this->doctrine = $doctrine;
    }

    /**
     * {@inheritdoc}
     */
    protected function validateUserPass($username, $password): bool
    {
        $user = $this->doctrine->getRepository(User::class)->findOneByUsername($username);

        if (!$user) {
            return false;
        }

        if ('$2y$' === substr($user->getPassword(), 0, 4)) {
            // Use password_verify with secure passwords
            return password_verify($password, $user->getPassword());
        } else {
            // Use unsecure legacy password hashing (from legacy sabre/dav implementation)
            return $user->getPassword() === $this->utils->hashPassword($username, $password);
        }
    }
}
