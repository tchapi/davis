<?php

namespace App\Services;

use Sabre\DAV\Auth\Backend\AbstractBasic;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

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
     * The username as the backend spells it, when that differs from what the client sent.
     */
    private ?string $canonicalUsername = null;

    /**
     * @param string $username
     * @param string $password
     */
    final protected function validateUserPass($username, $password): bool
    {
        $this->canonicalUsername = null;

        if (!is_string($username) || !is_string($password) || '' === $username || '' === $password) {
            return false;
        }

        if (self::breaksPrincipalUri($username)) {
            return false;
        }

        return $this->checkCredentials($username, $password);
    }

    /**
     * Validates a non-empty username and password against the backend.
     */
    abstract protected function checkCredentials(string $username, string $password): bool;

    /**
     * Backends call this when the directory spells the username differently from what the
     * client sent — LDAP matches `ALICE` against `uid=alice` quite happily. The principal is
     * then built from that spelling instead, so the login, the account and the principal URI
     * cannot drift apart and produce a second, empty account.
     */
    protected function setCanonicalUsername(string $username): void
    {
        // It ends up in a principal URI like any other username
        if ('' !== $username && !self::breaksPrincipalUri($username)) {
            $this->canonicalUsername = $username;
        }
    }

    /**
     * @return array{0: bool, 1: string}
     */
    public function check(RequestInterface $request, ResponseInterface $response)
    {
        $result = parent::check($request, $response);

        if (true === $result[0] && null !== $this->canonicalUsername) {
            return [true, $this->principalPrefix.$this->canonicalUsername];
        }

        return $result;
    }

    private static function breaksPrincipalUri(string $username): bool
    {
        // Anything that would change the shape of `principals/<username>`:
        //   [/\\]            a forward or back slash, which would add a path segment
        //   [\x00-\x20\x7f]  any control character, plus space (0x20) and DEL (0x7f)
        return 1 === preg_match('~[/\\\\]|[\\x00-\\x20\\x7f]~', $username);
    }
}
