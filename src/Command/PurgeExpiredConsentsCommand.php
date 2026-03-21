<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CookieConsentRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge-expired-consents',
    description: 'Purge cookie consents older than 13 months (CNIL retention limit)',
)]
class PurgeExpiredConsentsCommand extends Command
{
    public function __construct(
        private readonly CookieConsentRepository $consentRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $before = new \DateTimeImmutable('-13 months');
        $purged = $this->consentRepository->purgeExpired($before);

        $io->success(sprintf('%d consentement(s) purgé(s).', $purged));

        return Command::SUCCESS;
    }
}
