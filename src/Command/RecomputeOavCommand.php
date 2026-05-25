<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\OdtMatrix;
use App\Message\RecomputeOavTableMessage;
use App\MessageHandler\RecomputeOavTableHandler;
use App\Repository\SpiceActiveCompoundRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Déclenche le recalcul de la table spice_active_compound (vue matérialisée OAV).
 *
 * Le handler reconstruit toujours les 3 matrices (air, water, oil) en une seule passe.
 * Transaction InnoDB unique sur les 3 INSERT — atomique, zéro downtime.
 *
 * Usage :
 *   bin/console app:recompute:oav         # dispatch async (Messenger)
 *   bin/console app:recompute:oav --sync  # exécution synchrone directe
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §6.6
 */
#[AsCommand(
    name: 'app:recompute:oav',
    description: 'Recalcule la table spice_active_compound (vue matérialisée OAV — toutes matrices)'
)]
final class RecomputeOavCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly RecomputeOavTableHandler $handler,
        private readonly SpiceActiveCompoundRepository $spiceActiveCompoundRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'sync',
            null,
            InputOption::VALUE_NONE,
            'Exécution synchrone directe (appel du handler sans passer par le transport Messenger)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sync = (bool) $input->getOption('sync');

        $matrices = implode(', ', array_column(OdtMatrix::cases(), 'value'));
        $before = $this->spiceActiveCompoundRepository->countTotal();
        $io->info(sprintf('OAV avant rebuild : %d lignes (toutes matrices) — rebuild : %s', $before, $matrices));

        $message = new RecomputeOavTableMessage($sync ? 'console_sync' : 'console_async');

        if ($sync) {
            $io->section('Rebuild synchrone (handler direct) — toutes matrices');
            ($this->handler)($message);
            $after = $this->spiceActiveCompoundRepository->countTotal();
            $io->success(sprintf('Rebuild terminé — %d lignes OAV-actives (toutes matrices).', $after));
        } else {
            $io->section('Dispatch asynchrone via Messenger — toutes matrices');
            $this->messageBus->dispatch($message);
            $io->success('Message dispatché. Lancer le worker : bin/console messenger:consume async --limit=1');
        }

        return Command::SUCCESS;
    }
}
