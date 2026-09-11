<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CalendarSubscription;
use App\Services\ICSCalendarsService;
use Doctrine\Persistence\ManagerRegistry;
use Sabre\CalDAV\Backend\PDO as CalendarBackend;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncICSCalendars extends Command
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private ICSCalendarsService $icsService,
    ) {
        parent::__construct();

        $em = $doctrine->getManager();
        $pdo = $em->getConnection()->getNativeConnection();
        $this->icsService->setBackend(new CalendarBackend($pdo));
    }

    protected function configure(): void
    {
        $this
            ->setName('dav:sync-ics-calendars')
            ->setDescription('Synchronizes the ICS calendars');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->icsService->isEnabled()) {
            $output->writeln('ICS calendars are disabled.');
            $output->writeln('Enable ICS calendars by setting ICSCALENDARS_ENABLED=true.');
            return self::SUCCESS;
        }

        $output->writeln('Start ICS calendars sync ...');
        $p = new ProgressBar($output);
        $p->start();

        $subscriptions = $this->doctrine->getRepository(CalendarSubscription::class)->findAll();

        foreach ($subscriptions as $subscription) {
            $p->advance();
            $this->icsService->sync($subscription);
        }

        $p->finish();
        $output->writeln('');

        return self::SUCCESS;
    }
}
