<?php

declare(strict_types=1);

namespace App\Command;

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
 * Usage :
 *   bin/console app:recompute:oav           # dispatch async (Messenger)
 *   bin/console app:recompute:oav --sync    # exécution synchrone directe (scripts de déploiement)
 *
 * @see ARCHITECTURE_MOTEUR_COMPATIBILITE.md §6.6
 */
#[AsCommand(
    name: 'app:recompute:oav',
    description: 'Recalcule la table spice_active_compound (vue matérialisée OAV)'
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
            'Exécution synchrone directe (appel du handler sans passer par le transport Messenger)'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sync = (bool) $input->getOption('sync');

        $before = $this->spiceActiveCompoundRepository->countTotal();
        $io->info(sprintf('OAV avant rebuild : %d composés actifs.', $before));

        if ($sync) {
            $io->section('Rebuild synchrone (handler direct)');
            // Appel direct du handler — contourne le transport async
            ($this->handler)(new RecomputeOavTableMessage('console_sync'));
            $after = $this->spiceActiveCompoundRepository->countTotal();
            $io->success(sprintf('Rebuild terminé — %d composés OAV-actifs insérés.', $after));
        } else {
            $io->section('Dispatch asynchrone via Messenger');
            $this->messageBus->dispatch(new RecomputeOavTableMessage('console_async'));
            $io->success('Message dispatché. Lancer le worker : bin/console messenger:consume async --limit=1');
        }

        return Command::SUCCESS;
    }
}
