<?php

namespace App\Services;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Sabre\DAV\Auth\Backend\AbstractBasic;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

final class IMAPAuth extends AbstractBasic
{
    /**
     * Doctrine registry.
     *
     * @var ManagerRegistry
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

    /**
     * IMAP server in the form host[:port].
     *
     * @var string
     */
    private $IMAPAuthUrl;

    /**
     * IMAP encryption method. Could be ssl, tls or false.
     *
     * @var mixed (string or bool)
     */
    private $IMAPEncryptionMethod;

    /**
     * Should we validate the certificate?
     *
     * @var bool
     */
    private $IMAPCertificateValidation;

    public function __construct(ManagerRegistry $doctrine, Utils $utils, string $IMAPAuthUrl, bool $autoCreate, string $IMAPEncryptionMethod, bool $IMAPCertificateValidation)
    {
        $this->IMAPAuthUrl = $IMAPAuthUrl;

        // We're making sure that only ssl, tls or 'false' are passed down to the IMAP client
        $IMAPEncryptionMethodCleaned = strtolower($IMAPEncryptionMethod);
        if ('false' === $IMAPEncryptionMethodCleaned) {
            $this->IMAPEncryptionMethod = false;
        } elseif ('tls' === $IMAPEncryptionMethodCleaned) {
            $this->IMAPEncryptionMethod = 'tls';
        } else {
            $this->IMAPEncryptionMethod = 'ssl';
        }
        $this->IMAPCertificateValidation = $IMAPCertificateValidation;

        $this->autoCreate = $autoCreate;

        $this->doctrine = $doctrine;
        $this->utils = $utils;
    }

    /**
     * Connects to an IMAP server and tries to authenticate.
     * If the user does not exist, create it.
     */
    protected function imapOpen(string $username, string $password): bool
    {
        $cm = new ClientManager($options = []);

        $components = parse_url($this->IMAPAuthUrl);

        if (!$components) {
            error_log('IMAP Error (parsing IMAP url "'.$this->IMAPAuthUrl.'" ): '.$e->getMessage());

            return false;
        }

        // Trying to choose the best port if it was not provided
        if ($components['port']) {
            $port = $components['port'];
        } elseif (false === $this->IMAPEncryptionMethod) {
            $port = 143;
        } else {
            $port = 993;
        }

        // Create a new instance of the IMAP client manually
        $client = $cm->make([
            'host' => $components['host'],
            'port' => $port,
            'encryption' => $this->IMAPEncryptionMethod,
            'validate_cert' => $this->IMAPCertificateValidation,
            'username' => $username,
            'password' => $password,
            'protocol' => 'imap',
        ]);

        try {
            $client->connect();
            $client->disconnect();
            $success = true;
        } catch (\Exception $e) {
            error_log('IMAP Error (connection): '.$e->getMessage());
            $success = false;
        }

        // Auto-create the user if it does not already exist in the database
        if ($success && $this->autoCreate) {
            $user = $this->doctrine->getRepository(User::class)->findOneBy(['username' => $username]);

            if (!$user) {
                // We only have a username, so we use it for displayname and email
                $this->utils->createPasswordlessUserWithDefaultObjects($username, $username, $username);

                $em = $this->doctrine->getManager();

                try {
                    $em->flush();
                } catch (\Exception $e) {
                    error_log('IMAP Error (flush): '.$e->getMessage());
                }
            }
        }

        return $success;
    }

    /**
     * Validates a username and password by trying to authenticate against IMAP.
     */
    protected function validateUserPass($username, $password): bool
    {
        return $this->imapOpen($username, $password);
    }
}
