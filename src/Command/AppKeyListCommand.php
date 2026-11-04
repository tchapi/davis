<?php

namespace App\Command;

use App\Entity\AppKey;
use App\Services\AppKeysFeature;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// The description is set in configure(), not here. With a description here, Symfony wraps the
// command in a LazyCommand, which is always enabled, so isEnabled() below would have no effect.
#[AsCommand(name: 'davis:app-key:list')]
class AppKeyListCommand extends Command
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
            ->setDescription('List app keys')
            ->setHelp('This command lists every app key, revoked ones included. Secrets are never shown.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repository = $this->doctrine->getRepository(AppKey::class);
        $keys = $repository->findAllNewestFirst();
        $usages = $repository->findUsagesByKey();

        if (!$keys) {
            $io->note('No app key yet.');

            return self::SUCCESS;
        }

        $now = new \DateTimeImmutable();
        $rows = [];
        foreach ($keys as $key) {
            if (null === $key->getRevokedAt()) {
                $status = 'active';
            } elseif ($key->isActive($now)) {
                $status = 'expires '.$key->getRevokedAt()->format('Y-m-d H:i');
            } else {
                $status = 'revoked '.$key->getRevokedAt()->format('Y-m-d H:i');
            }

            // The 5 most recent logins, then a count of the others
            $logins = array_map(fn ($usage) => $usage->getLogin(), $usages[$key->getId()] ?? []);
            $usedAs = implode(', ', array_slice($logins, 0, 5)).(count($logins) > 5 ? sprintf(' and %d more', count($logins) - 5) : '');

            $rows[] = [
                $key->getName(),
                $key->getAppId(),
                implode(', ', $key->getScope()),
                $key->getCreatedAt()->format('Y-m-d H:i'),
                $key->getLastUsedAt()?->format('Y-m-d H:i') ?? 'never',
                '' === $usedAs ? 'nobody' : $usedAs,
                $status,
            ];
        }

        $io->table(['Name', 'App id', 'Scope', 'Created', 'Last used', 'Used as', 'Status'], $rows);

        return self::SUCCESS;
    }
}
