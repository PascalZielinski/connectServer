<?php

namespace App\Command;

use App\Repository\TrustedDeviceRepository;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:auth:cleanup',
    description: 'Deletes expired unverified users and stale pending devices.',
)]
final class CleanupAuthCommand extends Command
{
    private const DEVICE_GRACE = '-24 hours';
    private const INTERVAL_SECONDS = 1800;

    public function __construct(
        private readonly UserRepository $users,
        private readonly TrustedDeviceRepository $devices,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Run cleanup once and exit.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('once')) {
            $this->runCleanup($output);

            return Command::SUCCESS;
        }

        while (true) {
            try {
                $this->runCleanup($output);
            } catch (\Throwable $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');

                // Prozess beenden, Docker startet den Container neu (frischer EM, frische DB-Verbindung)
                return Command::FAILURE;
            }

            sleep(self::INTERVAL_SECONDS);
        }
    }

    private function runCleanup(OutputInterface $output): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $users = $this->users->deleteExpiredUnverified($now);
        $devices = $this->devices->deleteStalePending($now->modify(self::DEVICE_GRACE));

        $output->writeln(sprintf('Deleted %d unverified user(s), %d stale pending device(s).', $users, $devices));
    }
}