<?php

namespace App\Command;

use App\Entity\AppKey;
use App\Services\AppKeysFeature;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// The description is set in configure(), not here. With a description here, Symfony wraps the
// command in a LazyCommand, which is always enabled, so isEnabled() below would have no effect.
#[AsCommand(name: 'davis:app-key:prune')]
class AppKeyPruneCommand extends Command
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
            ->setDescription('Delete the app keys revoked a while ago')
            ->addArgument('days', InputArgument::REQUIRED, 'Delete the keys revoked at least this many days ago (0 for every revoked key)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the keys that would be deleted, delete nothing')
            ->setHelp(<<<'HELP'
                This command deletes the app keys that were revoked at least the given number of days ago, with
                their usage history (which users each key acted as, and when). Choose the number of days
                according to how long you want to keep that history for.

                A key whose revocation date is in the future (an expiry) is still active and is never touched.
                Keys that were never revoked are not touched either, use <info>davis:app-key:revoke</info> first.

                Davis does not run this on its own. To do it every night, for example:

                  <comment>0 3 * * * cd /path/to/davis && bin/console davis:app-key:prune 90 -q</comment>

                Use <info>--dry-run</info> to see what would be deleted.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = $input->getArgument('days');

        if (!ctype_digit($days)) {
            $io->error(sprintf('"%s" is not a number of days, expected an integer >= 0.', $days));

            return self::INVALID;
        }

        $dryRun = $input->getOption('dry-run');
        $cutoff = (new \DateTimeImmutable())->sub(new \DateInterval(sprintf('P%dD', (int) $days)));

        $repository = $this->doctrine->getRepository(AppKey::class);
        $keys = $repository->findRevokedBefore($cutoff);

        if (!$keys) {
            $io->note(sprintf('No app key revoked before %s, nothing to delete.', $cutoff->format('Y-m-d H:i')));

            return self::SUCCESS;
        }

        foreach ($keys as $key) {
            $io->writeln(sprintf('%s %s revoked %s', $key->getAppId(), $key->getName(), $key->getRevokedAt()->format('Y-m-d H:i')));

            if (!$dryRun) {
                $repository->delete($key);
            }
        }

        if ($dryRun) {
            $io->note(sprintf('Dry run: %d app key(s) would be deleted.', count($keys)));
        } else {
            $io->success(sprintf('Deleted %d app key(s).', count($keys)));
        }

        return self::SUCCESS;
    }
}
