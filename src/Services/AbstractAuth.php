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

        return $this->checkCredentials($username, $password);
    }

    /**
     * Validates a non-empty username and password against the backend.
     */
    abstract protected function checkCredentials(string $username, string $password): bool;
}
