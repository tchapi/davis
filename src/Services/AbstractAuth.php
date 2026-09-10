<?php

namespace App\Services;

use Sabre\DAV\Auth\Backend\AbstractBasic;

/**
 * Common base for the HTTP Basic authentication backends (internal, IMAP, LDAP).
 *
 * It rejects empty usernames and passwords before any backend is contacted.
 * sabre/dav hands us `['user', '']` for an `Authorization: Basic base64("user:")` header,
 * and an empty password means an *unauthenticated bind* for LDAP servers, which Active
 * Directory (and OpenLDAP with `allow bind_anon_cred`) answers with success, i.e. it
 * would log the caller in as any user.
 *
 * It also rejects usernames that would not survive being put in a principal URI. sabre
 * derives the principal from the login name (`principals/<username>`), so a name containing
 * a slash would address a different, possibly existing, node: `alice/calendar-proxy-write`
 * is exactly the URI Davis uses for alice's delegation proxy. Only structural characters are
 * refused here, not the stricter set required when creating an account, so that an unusual
 * but working username keeps authenticating.
 */
abstract class AbstractAuth extends AbstractBasic
{
    /**
     * @param string $username
     * @param string $password
     */
    final protected function validateUserPass($username, $password): bool
    {
        if (!is_string($username) || !is_string($password) || '' === $username || '' === $password) {
            return false;
        }

        // Anything that would change the shape of `principals/<username>`:
        //   [/\\]            a forward or back slash, which would add a path segment
        //   [\x00-\x20\x7f]  any control character, plus space (0x20) and DEL (0x7f)
        if (1 === preg_match('~[/\\\\]|[\\x00-\\x20\\x7f]~', $username)) {
            return false;
        }

        return $this->checkCredentials($username, $password);
    }

    /**
     * Validates a non-empty username and password against the backend.
     */
    abstract protected function checkCredentials(string $username, string $password): bool;
}
