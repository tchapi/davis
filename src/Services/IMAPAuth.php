<?php

namespace App\Services;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Sabre\DAV\Auth\Backend\IMAP;

final class IMAPAuth extends IMAP
{
    /**
     * Doctrine registry.
     *
     * @var \Doctrine\Persistence\ManagerRegistry
     */
    private $doctrine;

    /**
     * Utils class.
     *
     * @var Utils
     */
    private $utils;

    /**
     * Should we auto create the user upon successful
     * login if it does not exist yet.
     *
     * @var bool
     */
    private $autoCreate;

    public function __construct(ManagerRegistry $doctrine, Utils $utils, string $IMAPAuthUrl, bool $autoCreate)
    {
        parent::__construct($IMAPAuthUrl);

        $this->autoCreate = $autoCreate;

        $this->doctrine = $doctrine;
        $this->utils = $utils;
    }

    /**
     * Connects to an IMAP server and tries to authenticate.
     * If the user does not exist, create it.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    protected function imapOpen($username, $password)
    {
        $success = parent::imapOpen($username, $password);

        // Auto-create the user if it does not already exist in the database
        if ($success && $this->autoCreate) {
            $user = $this->doctrine->getRepository(User::class)->findOneBy(['username' => $username]);

            if (!$user) {
                // We only have a username, so we use it for displayname and email
                $this->utils->createUserWithDefaultObjects($username, $password, $username, $username);

                $em = $this->doctrine->getManager();
                $em->flush();
            }
        }

        return $success;
    }
}
