<?php

namespace App\Command;

use App\Entity\AppKey;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'davis:app-key:list',
    description: 'List app keys',
)]
class AppKeyListCommand extends Command
{
    public function __construct(
        private ManagerRegistry $doctrine,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command lists every app key, revoked ones included. Secrets are never shown.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $keys = $this->doctrine->getRepository(AppKey::class)->findAllNewestFirst();

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

            $rows[] = [
                $key->getName(),
                $key->getAppId(),
                implode(', ', $key->getScope()),
                $key->getCreatedAt()->format('Y-m-d H:i'),
                $key->getLastUsedAt()?->format('Y-m-d H:i') ?? 'never',
                $status,
            ];
        }

        $io->table(['Name', 'App id', 'Scope', 'Created', 'Last used', 'Status'], $rows);

        return self::SUCCESS;
    }
}
