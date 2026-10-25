<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Psr\Cache\CacheItemPoolInterface;

#[AsCommand(
    name: 'app:ratelimit:cleanup',
    description: 'Remove all rate limits',
)]

# docker compose exec web php bin/console app:ratelimit:cleanup

final class RateLimitCleanupCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'cache.rate_limiter')]
        private readonly CacheItemPoolInterface $rateLimiterCache,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->rateLimiterCache->clear()) {
            $io->error('Rate limits konnten nicht gelöscht werden.');

            return Command::FAILURE;
        }

        $io->success('Alle Rate Limits wurden entfernt.');

        return Command::SUCCESS;
    }
}