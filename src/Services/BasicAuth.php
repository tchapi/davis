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
    protected function validateUserPass($username, $password)
    {
        $user = $this->userRepository->findOneByUsername($username);

        if (!$user) {
            return false;
        }

        $hash = $this->utils->hashPassword($username, $password);

        if ($hash === $user->getPassword()) {
            $this->currentUser = $username;

            return true;
        }

        return false;
    }
}
