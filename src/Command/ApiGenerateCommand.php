<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'api:generate',
    description: 'Generate a new API key',
    help: 'This command allows you to generate a new API key',
)]
class ApiGenerateCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('api:generate')
            ->setDescription('Generate a new API key')
            ->setHelp('This command allows you to generate a new API key')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $apiKey = bin2hex(random_bytes(32));

        $io->success($apiKey);
        $io->warning('Set the API key in your .env file as API_KEY, as it won\'t be stored otherwise.');

        return self::SUCCESS;
    }
}