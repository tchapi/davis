<?php

namespace App\Services;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Sabre\DAV\Auth\Backend\AbstractBasic;

final class LDAPAuth extends AbstractBasic
{
    /**
     * LDAP server uri.
     * e.g. ldaps://ldap.example.org.
     *
     * @var string
     */
    private $LDAPAuthUrl;

    /*
     * LDAP dn pattern for binding
     *
     * %u   - gets replaced by full username
     * %U   - gets replaced by user part when the
     *        username is an email address
     * %d   - gets replaced by domain part when the
     *        username is an email address
     * %1-9 - gets replaced by parts of the the domain
     *        split by '.' in reverse order
     *        mail.example.org: %1 = org, %2 = example, %3 = mail
     *
     * A common pattern is "mail=%u"
     * @var string
     */
    private $LDAPDnPattern;

    /*
     * LDAP attribute used for mail
     *
     * @var string
     */
    private $LDAPMailAttribute;

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

    /**
     * Creates the backend object.
     */
    public function __construct(ManagerRegistry $doctrine, Utils $utils, string $LDAPAuthUrl, string $LDAPDnPattern, string $LDAPMailAttribute, bool $autoCreate)
    {
        $this->LDAPAuthUrl = $LDAPAuthUrl;
        $this->LDAPDnPattern = $LDAPDnPattern;
        $this->LDAPMailAttribute = $LDAPMailAttribute ?? 'mail';
        $this->autoCreate = $autoCreate;

        $this->doctrine = $doctrine;
        $this->utils = $utils;
    }

    /**
     * Connects to an LDAP server and tries to authenticate.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    protected function ldapOpen($username, $password)
    {
        $success = false;

        try {
            $ldap = ldap_connect($this->LDAPAuthUrl);
        } catch (\ErrorException $e) {
            error_log($e->getMessage());
        }

        if (!$ldap || !ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            return false;
        }

        // Extract user and domain from username (in the form user@domain.org)
        $user_parts = explode('@', $username, 2);

        $ldap_user = $user_parts[0];

        if (count($user_parts) > 1) {
            $ldap_domain = $user_parts[1];
        } else {
            $ldap_domain = '';
        }

        // Replace common placeholders
        $dn = str_replace(['%u', '%U', '%d'], [$username, $ldap_user, $ldap_domain], $this->LDAPDnPattern);

        // Replace domain parts
        $domain_split = array_reverse(explode('.', $ldap_domain));
        for ($i = 1; $i <= count($domain_split) and $i <= 9; ++$i) {
            $dn = str_replace('%'.$i, $domain_split[$i - 1], $dn);
        }

        try {
            $bind = ldap_bind($ldap, $dn, $password);
            if ($bind) {
                $success = true;
            }
        } catch (\ErrorException $e) {
            error_log($e->getMessage());
            error_log('LDAP Error: '.ldap_error($ldap).' ('.ldap_errno($ldap).')');
        }

        if ($success && $this->autoCreate) {
            $user = $this->doctrine->getRepository(User::class)->findOneBy(['username' => $username]);

            if (!$user) {
                // Default fallback values
                $displayName = $username;
                $email = $username;

                // Try to extract display name and email for this user.
                // NB: We suppose display name is `cn` (email is configurable, generally `mail`)
                try {
                    $search_results = ldap_read($ldap, $dn, '(objectclass=*)', ['cn', $this->LDAPMailAttribute]);
                } catch (\Exception $e) {
                    $search_results = false;
                    // Probably a "No such object" error, ignore and use available credentials (username)
                }

                if (false !== $search_results) {
                    $entry = ldap_get_entries($ldap, $search_results);

                    if (false !== $entry) {
                        if (!empty($entry[0]['cn'])) {
                            $displayName = $entry[0]['cn'][0];
                        }
                        if (!empty($entry[0][$this->LDAPMailAttribute])) {
                            $email = $entry[0][$this->LDAPMailAttribute][0];
                        }
                    }
                }

                $this->utils->createUserWithDefaultObjects($username, $password, $displayName, $email);

                $em = $this->doctrine->getManager();
                $em->flush();
            }
        }

        if (isset($ldap) && $ldap) {
            ldap_close($ldap);
        }

        return $success;
    }

    /**
     * Validates a username and password by trying to authenticate against LDAP.
     *
     * @param string $username
     * @param string $password
     */
    protected function validateUserPass($username, $password): bool
    {
        return $this->ldapOpen($username, $password);
    }
}
