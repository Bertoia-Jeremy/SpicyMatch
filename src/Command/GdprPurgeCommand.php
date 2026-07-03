<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ContactRepository;
use App\Repository\GdprRequestRepository;
use App\Repository\UsersRepository;
use App\Service\Gdpr\UserAnonymizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:gdpr:purge',
    description: 'Purge contacts (12 months), GDPR requests (6 years) and anonymize soft-deleted users (30 days)',
)]
class GdprPurgeCommand extends Command
{
    public const CONTACT_RETENTION = '-12 months';

    public const GDPR_REQUEST_RETENTION = '-6 years';

    public const DELETED_USER_GRACE = '-30 days';

    public function __construct(
        private readonly ContactRepository $contactRepository,
        private readonly GdprRequestRepository $gdprRequestRepository,
        private readonly UsersRepository $usersRepository,
        private readonly UserAnonymizer $userAnonymizer,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $purgedContacts = $this->contactRepository->purgeCreatedBefore(new \DateTimeImmutable(self::CONTACT_RETENTION));
        $purgedRequests = $this->gdprRequestRepository->purgeCreatedBefore(
            new \DateTimeImmutable(self::GDPR_REQUEST_RETENTION)
        );

        $users = $this->usersRepository->findAnonymizableDeletedBefore(
            new \DateTimeImmutable(self::DELETED_USER_GRACE)
        );

        foreach ($users as $user) {
            $this->userAnonymizer->anonymize($user);
        }

        if ([] !== $users) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            '%d contact(s) purgé(s), %d demande(s) RGPD purgée(s), %d compte(s) anonymisé(s).',
            $purgedContacts,
            $purgedRequests,
            count($users),
        ));

        return Command::SUCCESS;
    }
}
