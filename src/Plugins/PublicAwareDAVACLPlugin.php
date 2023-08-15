<?php

namespace App\Plugins;

use App\Entity\CalendarInstance;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class PublicAwareDAVACLPlugin extends \Sabre\DAVACL\Plugin
{
    /**
     * We override this method so that public objects can be seen correctly in the browser,
     * with the assets (css, images).
     */
    public function beforeMethod(RequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getQueryParameters();
        if (isset($params['sabreAction']) && 'asset' === $params['sabreAction']) {
            return;
        }

        return parent::beforeMethod($request, $response);
    }

    public function getAcl($node): array
    {
        $acl = parent::getAcl($node);

        if ($node instanceof \Sabre\CalDAV\Calendar) {
            if (CalendarInstance::ACCESS_PUBLIC === $node->getShareAccess()) {
                $acl[] = [
                    'principal' => '{DAV:}unauthenticated',
                    'privilege' => '{DAV:}read',
                    'protected' => false,
                ];
            }
        }

        return $acl;
    }
}
