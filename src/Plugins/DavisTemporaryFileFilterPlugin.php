<?php

namespace App\Plugins;

use Sabre\DAV\TemporaryFileFilterPlugin;
use Sabre\DAVACL\Plugin as AclPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\Uri;

/**
 * sabre/dav's TemporaryFileFilterPlugin intercepts the junk files desktop clients
 * write next to real files (.DS_Store, Thumbs.db, ._*, *.swp, ...) and stores them
 * outside the DAV tree. Because those files never exist in the tree, the ACL plugin
 * never checks anything for them: without this subclass, anybody (authenticated or
 * not) could store, read and delete such files under any path.
 *
 * We make temporary files obey the privileges of the directory they would live in,
 * exactly like a real file would.
 */
final class DavisTemporaryFileFilterPlugin extends TemporaryFileFilterPlugin
{
    /**
     * Same contract as the parent: false stops the request (the plugin answered it), null
     * lets the regular handlers run.
     *
     * @return bool|null
     */
    public function beforeMethod(RequestInterface $request, ResponseInterface $response)
    {
        $path = $request->getPath();
        if (false === $this->isTempFile($path)) {
            return;
        }

        // This is a permission check: it must fail closed, never be skipped silently.
        $acl = $this->server->getPlugin('acl');
        if (!$acl instanceof AclPlugin) {
            throw new \LogicException('The ACL plugin must be registered for '.self::class.' to work: temporary files would otherwise bypass every permission check.');
        }

        [$parent] = Uri\split($path);

        // Temporary files are not nodes of the tree, so the finer-grained checks the ACL
        // plugin does on real files (write-content on an existing file for PUT, for
        // instance) cannot apply. We check the privilege on the parent directory that the
        // matching operation on a real file would require.
        $privilege = match ($request->getMethod()) {
            'PUT' => '{DAV:}bind',
            'DELETE' => '{DAV:}unbind',
            default => '{DAV:}read',
        };

        // Throws NotAuthenticated (401) for anonymous users and NeedPrivileges (403) otherwise
        $acl->checkPrivileges($parent ?? '', $privilege);

        return parent::beforeMethod($request, $response);
    }
}
