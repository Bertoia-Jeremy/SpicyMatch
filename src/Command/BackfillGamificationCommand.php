<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\UserProgression;
use App\Entity\UserStat;
use App\Repository\SpiceViewRepository;
use App\Repository\SpicyMatchHistoryRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backfill-gamification',
    description: 'Create UserProgression + UserStat for existing users and recalculate counters.',
)]
class BackfillGamificationCommand extends Command
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly SpicyMatchHistoryRepository $historyRepository,
        private readonly SpiceViewRepository $spiceViewRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $users = $this->usersRepository->findAll();
        $created = 0;

        foreach ($users as $user) {
            $needsFlush = false;

            if (null === $user->getProgression()) {
                $progression = new UserProgression();
                $progression->setUser($user);
                $user->setProgression($progression);
                $this->em->persist($progression);
                $needsFlush = true;
            }

            $progression = $user->getProgression();

            if (null === $user->getStats()) {
                $stats = new UserStat();
                $stats->setUser($user);
                $user->setStats($stats);
                $this->em->persist($stats);
                $needsFlush = true;
            }

            // Recalculate counters from existing data
            $matchCount = $this->historyRepository->countByUser($user);
            $progression->setTotalMatches($matchCount);

            $uniqueSpices = $this->historyRepository->countDistinctSpicesByUser($user);
            $progression->setUniqueSpicesUsed($uniqueSpices);

            $discoveries = $this->spiceViewRepository->countDistinctSpicesByUser($user);
            $progression->setDiscoveries($discoveries);

            if ($needsFlush) {
                ++$created;
            }
        }

        $this->em->flush();

        $io->success(\sprintf('%d utilisateur(s) initialisé(s) pour la gamification.', $created));

        return Command::SUCCESS;
    }
}
