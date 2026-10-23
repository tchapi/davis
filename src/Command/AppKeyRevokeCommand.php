<?php

namespace App\Command;

use App\Entity\AppKey;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'davis:app-key:revoke',
    description: 'Revoke an app key',
)]
class AppKeyRevokeCommand extends Command
{
    public function __construct(
        private ManagerRegistry $doctrine,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('app-id', InputArgument::REQUIRED, 'The app id of the key, as shown by davis:app-key:list')
            ->setHelp('This command revokes an app key right away. Every client using it is refused from then on.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $appId = $input->getArgument('app-id');

        $key = $this->doctrine->getRepository(AppKey::class)->findOneByAppId($appId);

        if (null === $key) {
            $io->error(sprintf('No app key with the app id %s.', $appId));

            return self::FAILURE;
        }

        $now = new \DateTimeImmutable();

        if (!$key->isActive($now)) {
            $io->note(sprintf('%s was already revoked on %s.', $appId, $key->getRevokedAt()->format('Y-m-d H:i')));

            return self::SUCCESS;
        }

        $key->revoke($now);
        $this->doctrine->getManager()->flush();

        $io->success(sprintf('Revoked %s.', $appId));

        return self::SUCCESS;
    }
}
