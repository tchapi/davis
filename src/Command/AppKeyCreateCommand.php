<?php

namespace App\Command;

use App\Entity\AppKey;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'davis:app-key:create',
    description: 'Create an app key that can act on behalf of users',
)]
class AppKeyCreateCommand extends Command
{
    public function __construct(
        private ManagerRegistry $doctrine,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'A name to recognise the key by, usually the service that will hold it')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A username the key can act as (repeatable); none means every user')
            ->addOption('expires-at', null, InputOption::VALUE_REQUIRED, 'When the key stops working, e.g. "2030-01-01 00:00" or "+30 days"');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $expiresAt = null;
        if (null !== ($value = $input->getOption('expires-at'))) {
            try {
                $expiresAt = new \DateTimeImmutable($value);
            } catch (\Exception $e) {
                $io->error(sprintf('"%s" is not a valid date: %s', $value, $e->getMessage()));

                return self::FAILURE;
            }
        }

        $key = AppKey::create($input->getArgument('name'), AppKey::normaliseScope($input->getOption('user')), $expiresAt);
        $secret = $key->issueSecret();

        $entityManager = $this->doctrine->getManager();
        $entityManager->persist($key);
        $entityManager->flush();

        $io->definitionList(
            ['Name' => $key->getName()],
            ['App id' => $key->getAppId()],
            ['Secret' => $secret],
            ['Scope' => implode(', ', $key->getScope())],
            ['Basic username' => '<login>*'.$key->getAppId()],
        );
        $io->warning('The secret is not stored and will not be shown again.');

        return self::SUCCESS;
    }
}
