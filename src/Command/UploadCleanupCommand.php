<?php

namespace App\Command;

use App\Service\UploadCleanupService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:uploads:cleanup',
    description: 'Remove files and metadata whose 24-hour retention period has expired.',
)]
final class UploadCleanupCommand extends Command
{
    public function __construct(private readonly UploadCleanupService $cleanup)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Run cleanup once and exit.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('once')) {
            $deleted = $this->cleanup->deleteExpired();
            $output->writeln(sprintf('Deleted %d expired upload(s).', $deleted));

            return Command::SUCCESS;
        }

        while (true) {
            $deleted = $this->cleanup->deleteExpired();
            $output->writeln(sprintf('Deleted %d expired upload(s).', $deleted));
            sleep(60);
        }
    }
}
