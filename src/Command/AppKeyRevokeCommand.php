<?php

namespace App\Command;

use App\Entity\AppKey;
use App\Services\AppKeysFeature;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// The description is set in configure(), not here. With a description here, Symfony wraps the
// command in a LazyCommand, which is always enabled, so isEnabled() below would have no effect.
#[AsCommand(name: 'davis:app-key:revoke')]
class AppKeyRevokeCommand extends Command
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private AppKeysFeature $appKeys,
    ) {
        parent::__construct();
    }

    // When APP_KEYS_ENABLED is off, the command is not registered
    public function isEnabled(): bool
    {
        return $this->appKeys->isEnabled();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Revoke an app key')
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
