<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\UserProgression;
use App\Entity\UserStat;
use App\Gamification\Strategy\MatchXpStrategy;
use App\Gamification\Strategy\SpiceReadXpStrategy;
use App\Repository\GameSessionRepository;
use App\Repository\SpiceViewRepository;
use App\Repository\SpicyMatchHistoryRepository;
use App\Repository\UserAchievementRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backfill-gamification',
    description: 'Create UserProgression + UserStat for existing users and recompute counters and XP.',
)]
class BackfillGamificationCommand extends Command
{
    public function __construct(
        private readonly UsersRepository $usersRepository,
        private readonly SpicyMatchHistoryRepository $historyRepository,
        private readonly SpiceViewRepository $spiceViewRepository,
        private readonly GameSessionRepository $gameSessionRepository,
        private readonly UserAchievementRepository $userAchievementRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report the changes without writing them.');
        $this->addOption('xp', null, InputOption::VALUE_NONE, 'Also recompute UserProgression::$xp from scratch.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $recomputeXp = (bool) $input->getOption('xp');

        $matchCounts = $this->historyRepository->countGroupedByUser();
        $uniqueSpiceCounts = $this->historyRepository->countDistinctSpicesGroupedByUser();
        $discoveryCounts = $this->spiceViewRepository->countDistinctSpicesGroupedByUser();
        $viewCounts = $this->spiceViewRepository->countGroupedByUser();
        $gameScores = $this->gameSessionRepository->sumFinishedScoreGroupedByUser();
        $badgeXp = $recomputeXp ? $this->userAchievementRepository->sumXpRewardGroupedByProgression() : [];

        $created = 0;
        $xpChanged = 0;
        $xpDelta = 0;

        foreach ($this->usersRepository->findAll() as $user) {
            $userId = (int) $user->getId();
            $progression = $user->getProgression();

            if ($progression === null) {
                $progression = new UserProgression();
                $progression->setUser($user);
                $user->setProgression($progression);
                $this->em->persist($progression);
                ++$created;
            }

            if ($user->getStats() === null) {
                $stats = new UserStat();
                $stats->setUser($user);
                $user->setStats($stats);
                $this->em->persist($stats);
            }

            $progression->setTotalMatches($matchCounts[$userId] ?? 0);
            $progression->setUniqueSpicesUsed($uniqueSpiceCounts[$userId] ?? 0);
            $progression->setDiscoveries($discoveryCounts[$userId] ?? 0);

            if (! $recomputeXp) {
                continue;
            }

            $progressionId = $progression->getId();
            $target = ($matchCounts[$userId] ?? 0) * MatchXpStrategy::XP_PER_MATCH
                + ($viewCounts[$userId] ?? 0) * SpiceReadXpStrategy::XP_PER_NEW_VIEW
                + ($gameScores[$userId] ?? 0)
                + ($progressionId === null ? 0 : ($badgeXp[$progressionId] ?? 0));

            if ($target === $progression->getXp()) {
                continue;
            }

            ++$xpChanged;
            $xpDelta += $target - $progression->getXp();
            $progression->setXp($target);
        }

        if ($dryRun) {
            $this->em->clear();
            $io->warning('Dry-run : aucune écriture.');
        } else {
            $this->em->flush();
        }

        $io->success(\sprintf('%d progression(s) créée(s).', $created));

        if ($recomputeXp) {
            $io->success(\sprintf('XP recalculée sur %d progression(s), delta global %+d.', $xpChanged, $xpDelta));
        }

        return Command::SUCCESS;
    }
}
