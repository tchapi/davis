<?php

namespace App\Command;

use App\Repository\AppKeyRepository;
use App\Services\AppKeysFeature;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// The description is set in configure(), not here. With a description here, Symfony wraps the
// command in a LazyCommand, which is always enabled, so isEnabled() below would have no effect.
#[AsCommand(name: 'davis:app-key:delete')]
class AppKeyDeleteCommand extends Command
{
    public function __construct(
        private AppKeyRepository $repository,
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
            ->setDescription('Delete a revoked (or expired) app key')
            ->addArgument('app-id', InputArgument::REQUIRED, 'The app id of the key, as shown by davis:app-key:list')
            ->setHelp('This command deletes a revoked (or expired) app key for good, with its usage history. An active key (including one with an expiry date still ahead) must be revoked first.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $appId = $input->getArgument('app-id');

        $key = $this->repository->findOneByAppId($appId);

        if (null === $key) {
            $io->error(sprintf('No app key with the app id %s.', $appId));

            return self::FAILURE;
        }

        if ($key->isActive(new \DateTimeImmutable())) {
            $io->error(sprintf('%s is still active. Revoke it first with davis:app-key:revoke.', $appId));

            return self::FAILURE;
        }

        $this->repository->delete([$key]);

        $io->success(sprintf('Deleted %s.', $appId));

        return self::SUCCESS;
    }
}
