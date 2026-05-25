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
 * Usage :
 *   bin/console app:recompute:oav                   # dispatch async (Messenger), matrice air
 *   bin/console app:recompute:oav --sync            # exécution synchrone, matrice air
 *   bin/console app:recompute:oav --sync --matrix=water  # synchrone, matrice eau
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
        $validMatrices = implode('|', array_column(OdtMatrix::cases(), 'value'));

        $this->addOption(
            'sync',
            null,
            InputOption::VALUE_NONE,
            'Exécution synchrone directe (appel du handler sans passer par le transport Messenger)',
        )->addOption(
            'matrix',
            null,
            InputOption::VALUE_REQUIRED,
            sprintf('Matrice ODT cible pour le calcul OAV (%s)', $validMatrices),
            OdtMatrix::AIR->value,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sync = (bool) $input->getOption('sync');

        // Validation de la matrice
        $matrixRaw = (string) $input->getOption('matrix');
        $matrix = OdtMatrix::tryFrom($matrixRaw);

        if ($matrix === null) {
            $validMatrices = implode(', ', array_column(OdtMatrix::cases(), 'value'));
            $io->error(sprintf('Matrice invalide : "%s". Valeurs acceptées : %s', $matrixRaw, $validMatrices));

            return Command::FAILURE;
        }

        $before = $this->spiceActiveCompoundRepository->countTotal();
        $io->info(sprintf(
            'OAV avant rebuild : %d composés actifs — matrice cible : %s (%s)',
            $before,
            $matrix->value,
            $matrix->label(),
        ));

        $message = new RecomputeOavTableMessage($sync ? 'console_sync' : 'console_async', $matrix);

        if ($sync) {
            $io->section(sprintf('Rebuild synchrone (handler direct) — matrice %s', $matrix->label()));
            // Appel direct du handler — contourne le transport async
            ($this->handler)($message);
            $after = $this->spiceActiveCompoundRepository->countTotal();
            $io->success(sprintf('Rebuild terminé — %d composés OAV-actifs insérés.', $after));
        } else {
            $io->section(sprintf('Dispatch asynchrone via Messenger — matrice %s', $matrix->label()));
            $this->messageBus->dispatch($message);
            $io->success('Message dispatché. Lancer le worker : bin/console messenger:consume async --limit=1');
        }

        return Command::SUCCESS;
    }
}
