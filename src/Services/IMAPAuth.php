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
     * IMAP server host.
     *
     * @var string
     */
    private $IMAPHost;

    /**
     * IMAP server port.
     *
     * @var int
     */
    private $IMAPPort;

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
        $components = parse_url($IMAPAuthUrl);

        if (!$components) {
            throw new Exception('IMAP Error (parsing IMAP url "'.$IMAPAuthUrl.'"): '.$e->getMessage());
        }

        $this->IMAPHost = $components['host'] ?? null;

        // Trying to choose the best port if it was not provided,
        // defaulting to 993 (secure)
        if (isset($components['port'])) {
            $this->IMAPPort = $components['port'];
        } elseif (false === $this->IMAPEncryptionMethod) {
            $this->IMAPPort = 143;
        } else {
            $this->IMAPPort = 993;
        }

        // We're making sure that only ssl, tls or 'false' are passed down to the IMAP client,
        // defaulting to SSL
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
     * If the user does not exist, create it (depending on the autoCreate flag).
     */
    protected function imapOpen(string $username, string $password): bool
    {
        $cm = new ClientManager($options = []);

        // Create a new instance of the IMAP client manually
        $client = $cm->make([
            'host' => $this->IMAPHost,
            'port' => $this->IMAPPort,
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
