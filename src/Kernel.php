<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();
        $timezone = $this->getContainer()->getParameter('timezone');
        if ('' === $timezone) {
            return;
        }
        try {
            date_default_timezone_set($timezone);
        } catch (\Exception $e) {
            // We don't crash the app, the setting will be flagged as incorrect in the dashboard
        }
    }
}
