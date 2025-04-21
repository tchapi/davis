<?php

namespace App\Command;

use App\Entity\User;
use App\Services\BirthdayService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncBirthdayCalendars extends Command
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private BirthdayService $birthdayService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('dav:sync-birthday-calendar')
            ->setDescription('Synchronizes the birthday calendar')
            ->addArgument('username',
                InputArgument::OPTIONAL,
                'Username for whom the birthday calendar will be synchronized');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('username');

        if (!is_null($username)) {
            if (!$this->doctrine->getRepository(User::class)->findOneByUsername($username)) {
                throw new \InvalidArgumentException("User <$username> is unknown.");
            }

            $output->writeln("Start birthday calendar sync for $username");
            $this->birthdayService->syncUser($username);

            return self::SUCCESS;
        }

        $output->writeln('Start birthday calendar sync for all users ...');
        $p = new ProgressBar($output);
        $p->start();

        $users = $this->doctrine->getRepository(User::class)->findAll();

        foreach ($users as $user) {
            $p->advance();
            $this->birthdayService->syncUser($user->getUsername());
        }

        $p->finish();
        $output->writeln('');

        return self::SUCCESS;
    }
}
