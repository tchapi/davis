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
    public function beforeMethod(RequestInterface $request, ResponseInterface $response)
    {
        $path = $request->getPath();
        if (false === $this->isTempFile($path)) {
            return;
        }

        $acl = $this->server->getPlugin('acl');
        if ($acl instanceof AclPlugin) {
            [$parent] = Uri\split($path);

            $privilege = match ($request->getMethod()) {
                'PUT' => '{DAV:}bind',
                'DELETE' => '{DAV:}unbind',
                default => '{DAV:}read',
            };

            // Throws NotAuthenticated (401) for anonymous users and NeedPrivileges (403) otherwise
            $acl->checkPrivileges($parent ?? '', $privilege);
        }

        return parent::beforeMethod($request, $response);
    }
}
