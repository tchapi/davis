<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * Invitations are sent while a calendar client is storing an event, so a broken MAILER_DSN shows
 * up as a missing email and nothing else. This sends one message with the same settings.
 */
#[AsCommand(
    name: 'davis:mail:test',
    description: 'Send a test email to check MAILER_DSN and INVITE_FROM_ADDRESS',
)]
class MailTestCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
        private ?string $inviteAddress = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'The address to send the test email to')
            ->setHelp('This command sends a test email through the configured mailer, the way scheduling invitations are sent.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = $input->getArgument('to');

        if (!$this->inviteAddress) {
            $io->error('INVITE_FROM_ADDRESS is not set, so Davis does not send scheduling invitations at all.');

            return self::FAILURE;
        }

        $io->text(sprintf('Sending a test email from <info>%s</info> to <info>%s</info>…', $this->inviteAddress, $to));

        try {
            $this->mailer->send(
                (new Email())
                    ->from(new Address($this->inviteAddress, 'Davis'))
                    ->to(new Address($to))
                    ->subject('Davis test email')
                    ->text("This is a test email from Davis.\n\nIf you received it, scheduling invitations can be delivered with the current configuration.\n")
            );
        } catch (RfcComplianceException $e) {
            $io->error(sprintf('"%s" is not a valid email address: %s', $to, $e->getMessage()));

            return self::FAILURE;
        } catch (TransportExceptionInterface $e) {
            $io->error('The email could not be sent: '.$e->getMessage());
            $io->note('Check MAILER_DSN. Davis logs the same error and keeps saving events when this happens.');

            return self::FAILURE;
        }

        $io->success('The email was handed to the transport. Check the destination mailbox.');

        return self::SUCCESS;
    }
}
