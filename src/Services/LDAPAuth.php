<?php

namespace App\Services;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;

final class LDAPAuth extends AbstractAuth
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
     * Indicates what to do with certificate.
     * see https://www.php.net/manual/en/ldap.constants.php#constant.ldap-opt-x-tls-require-cert.
     */
    private $LDAPCertificateCheckingStrategy;

    /**
     * Creates the backend object.
     */
    public function __construct(ManagerRegistry $doctrine, Utils $utils, string $LDAPAuthUrl, string $LDAPDnPattern, ?string $LDAPMailAttribute, bool $autoCreate, ?string $LDAPCertificateCheckingStrategy)
    {
        $this->LDAPAuthUrl = $LDAPAuthUrl;
        $this->LDAPDnPattern = $LDAPDnPattern;
        $this->LDAPMailAttribute = $LDAPMailAttribute ?? 'mail';
        $this->autoCreate = $autoCreate;
        $this->LDAPCertificateCheckingStrategy = $LDAPCertificateCheckingStrategy ?? 'try';

        $this->doctrine = $doctrine;
        $this->utils = $utils;
    }

    /**
     * Returns the username as the directory spells it, or null when it cannot be determined.
     */
    private function canonicalUsernameFor($ldap, string $dn): ?string
    {
        try {
            $read = ldap_read($ldap, $dn, '(objectclass=*)', ['dn']);
        } catch (\Exception $e) {
            $read = false;
        }

        if (false === $read) {
            return null;
        }

        $entries = ldap_get_entries($ldap, $read);
        $matchedDn = $entries[0]['dn'] ?? null;

        if (!is_string($matchedDn) || '' === $matchedDn) {
            return null;
        }

        // With the second argument set, only the values are returned, not the attribute names
        $rdns = ldap_explode_dn($matchedDn, 1);

        return (false !== $rdns && isset($rdns[0]) && '' !== $rdns[0]) ? $rdns[0] : null;
    }

    /**
     * Builds the bind DN for a username by filling the placeholders of LDAP_DN_PATTERN.
     *
     * Every substituted value is escaped for a DN context: without that, a username such as
     * `someone,ou=admins` would not be a value inside the DN but extra structure, changing
     * which entry we bind against.
     */
    protected function buildDn(string $username): string
    {
        $escape = static fn (string $value): string => ldap_escape($value, '', LDAP_ESCAPE_DN);

        // Extract user and domain from username (in the form user@domain.org)
        $user_parts = explode('@', $username, 2);

        $ldap_user = $user_parts[0];
        $ldap_domain = $user_parts[1] ?? '';

        // Replace common placeholders
        $dn = str_replace(
            ['%u', '%U', '%d'],
            [$escape($username), $escape($ldap_user), $escape($ldap_domain)],
            $this->LDAPDnPattern
        );

        // Replace domain parts
        $domain_split = array_reverse(explode('.', $ldap_domain));
        for ($i = 1; $i <= count($domain_split) and $i <= 9; ++$i) {
            $dn = str_replace('%'.$i, $escape($domain_split[$i - 1]), $dn);
        }

        return $dn;
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
        switch ($this->LDAPCertificateCheckingStrategy) {
            case 'never':
                $cert_strategy = LDAP_OPT_X_TLS_NEVER;
                break;
            case 'hard':
                $cert_strategy = LDAP_OPT_X_TLS_HARD;
                break;
            case 'demand':
                $cert_strategy = LDAP_OPT_X_TLS_DEMAND;
                break;
            case 'allow':
                $cert_strategy = LDAP_OPT_X_TLS_ALLOW;
                break;
            case 'try':
                $cert_strategy = LDAP_OPT_X_TLS_TRY;
                break;
            default:
                error_log('Invalid certificate checking strategy: '.$this->LDAPCertificateCheckingStrategy);

                return false;
        }

        if (false === ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, $cert_strategy)) {
            error_log('LDAP Error (ldap_set_option with '.$cert_strategy.'): failed');

            return false;
        }

        try {
            $ldap = ldap_connect($this->LDAPAuthUrl);
        } catch (\Exception $e) {
            error_log('LDAP Error (ldap_connect with '.$this->LDAPAuthUrl.'): '.$e->getMessage());

            return false;
        }

        if (false === $ldap) {
            error_log('LDAP Error (ldap_connect with '.$this->LDAPAuthUrl.'): provided LDAP URI does not seems plausible');

            return false;
        }

        if (!ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3)) {
            error_log('LDAP Error (ldap_set_option): could not set LDAP_OPT_PROTOCOL_VERSION to 3');

            return false;
        }

        $dn = $this->buildDn($username);

        $success = false;
        try {
            $bind = ldap_bind($ldap, $dn, $password);
            if ($bind) {
                $success = true;
            }
        } catch (\Exception $e) {
            error_log('LDAP Error (ldap_bind to '.$this->LDAPAuthUrl.'): '.ldap_error($ldap).' ('.ldap_errno($ldap).')');
        }

        if ($success) {
            // Directories match names case-insensitively, so `ALICE` binds against `uid=alice`
            // just as well as `alice` does. Take the spelling the server actually matched:
            // read the entry back and use the value of the first RDN of the DN it returns.
            // Deriving it from the DN rather than from a fixed attribute keeps this working
            // whatever LDAP_DN_PATTERN is built on (uid, cn, sAMAccountName, mail...).
            $canonical = $this->canonicalUsernameFor($ldap, $dn);

            if (null !== $canonical) {
                $this->setCanonicalUsername($canonical);
                $username = $canonical;
            }
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

                try {
                    $this->utils->createPasswordlessUserWithDefaultObjects($username, $displayName, $email);
                    $this->doctrine->getManager()->flush();
                } catch (\Throwable $e) {
                    // Letting the login through without a principal would leave the account
                    // authenticated but unusable: no calendar home, so clients fall back to the
                    // server root and every write is refused.
                    error_log('LDAP Error (could not create the user "'.$username.'"): '.$e->getMessage());

                    $success = false;
                }
            }
        }

        if (isset($ldap) && $ldap) {
            ldap_close($ldap);
        }

        return $success;
    }

    /**
     * Validates a username and password by trying to authenticate against LDAP.
     */
    protected function checkCredentials(string $username, string $password): bool
    {
        return $this->ldapOpen($username, $password);
    }
}
