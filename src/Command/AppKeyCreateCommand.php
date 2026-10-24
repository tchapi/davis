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
#[AsCommand(name: 'davis:app-key:create')]
class AppKeyCreateCommand extends Command
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
            ->setDescription('Create an app key that can act on behalf of users')
            ->addArgument('name', InputArgument::REQUIRED, 'A name to recognise the key by, usually the service that will hold it')
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A username the key can act as. You can repeat it. The key can see everything this user can see, including shared and delegated calendars. To let the key act as an admin, add the admin here')
            ->addOption('all-users', null, InputOption::VALUE_NONE, 'Let the key act as all users except admins')
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

        $scope = $input->getOption('user');
        if ($input->getOption('all-users')) {
            $scope[] = AppKey::ALL_USERS;
        }

        $scope = AppKey::normaliseScope($scope);
        if (!$scope) {
            $io->error('Choose the users the key can act as, with --user (you can repeat it) or --all-users.');

            return self::INVALID;
        }

        $unknown = $this->doctrine->getRepository(AppKey::class)->findUnknownUsers($scope);
        if ($unknown) {
            $io->error(sprintf('These users do not exist: %s.', implode(', ', $unknown)));

            return self::INVALID;
        }

        $key = AppKey::create($input->getArgument('name'), $scope, $expiresAt);
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
