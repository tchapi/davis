<?php

namespace App\Services;

use App\Repository\UserRepository;
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
     * Doctrine User repository.
     *
     * @var UserRepository
     */
    private $userRepository;

    public function __construct(UserRepository $userRepository, Utils $utils)
    {
        $this->utils = $utils;
        $this->userRepository = $userRepository;
    }

    /**
     * {@inheritdoc}
     */
    protected function validateUserPass($username, $password): bool
    {
        $user = $this->userRepository->findOneByUsername($username);

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
