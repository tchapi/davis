<?php

namespace App\Services;

use Symfony\Bundle\FrameworkBundle\Routing\Attribute\AsRoutingConditionService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Tells if app keys are turned on (APP_KEYS_ENABLED, off by default).
 * When they are off, the login check ignores them, and the pages and commands are not available.
 */
#[AsRoutingConditionService(alias: 'app_keys')]
final class AppKeysFeature
{
    public function __construct(
        #[Autowire('%app_keys_enabled%')]
        private bool $enabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
