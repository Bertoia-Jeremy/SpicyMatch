<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AromaticGroups;
use App\Entity\Spices;
use App\Repository\SpicesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:import:spices-catalog',
    description: 'Amorce le catalogue d\'épices depuis une sélection CSV (bootstrap FlavorGraph, idempotent)'
)]
final class ImportSpicesCatalogCommand extends Command
{
    private const DEFAULT_FILE = 'data/flavorgraph/selection_100.csv';
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;
    private const REVIEW_GROUP_NAME = 'À réviser';
    private const REVIEW_GROUP_COLOR = '#9ca3af';

    /**
     * @var list<string>
     */
    private const DELETED_SLUGS = ['piment-espelette', 'poivre-long'];

    public function __construct(
        private readonly SpicesRepository $spicesRepository,
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', 'f', InputOption::VALUE_OPTIONAL, 'Chemin CSV de sélection', self::DEFAULT_FILE)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans écriture en BDD');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $path = $this->resolvePath((string) $input->getOption('file'));
        if (null === $path) {
            $io->error('Fichier hors périmètre autorisé (data/flavorgraph/) ou introuvable.');

            return Command::FAILURE;
        }

        $rows = $this->readCsv($path);
        if (null === $rows) {
            $io->error('CSV illisible ou en-tête invalide.');

            return Command::FAILURE;
        }

        $reviewGroup = $this->resolveReviewGroup();
        $groupsById = $this->preloadGroups();

        $created = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            if ('new' !== $row['kind']) {
                continue;
            }

            $slug = trim($row['slug']);
            if ('' === $slug || '' === trim($row['spice_fr'])) {
                continue;
            }

            if (null !== $this->spicesRepository->findOneBy([
                'slug' => $slug,
            ])) {
                ++$skipped;

                continue;
            }

            $group = $reviewGroup;
            $gid = trim($row['aromatic_group_id']);
            if ('' !== $gid && isset($groupsById[(int) $gid])) {
                $group = $groupsById[(int) $gid];
            }

            $spice = new Spices();
            $spice->setName(trim($row['spice_fr']));
            $spice->setSlug($slug);
            $spice->setAromaticGroups($group);
            $spice->setImageSize(0);
            $now = new \DateTimeImmutable();
            $spice->setCreatedAt($now);
            $spice->setUpdatedAt($now);

            $this->em->persist($spice);
            ++$created;
        }

        $deleted = $this->softDeleteRemoved();

        if (! $dryRun) {
            $this->em->flush();
        }

        $io->success(\sprintf(
            '%s : %d créées, %d déjà présentes, %d soft-deleted.',
            $dryRun ? 'DRY-RUN' : 'Import',
            $created,
            $skipped,
            $deleted,
        ));

        return Command::SUCCESS;
    }

    private function resolvePath(string $file): ?string
    {
        $candidate = str_starts_with($file, '/') ? $file : $this->projectDir.'/'.$file;
        $real = realpath($candidate);
        if (false === $real || ! is_file($real) || filesize($real) > self::MAX_FILE_SIZE) {
            return null;
        }

        $allowed = realpath($this->projectDir.'/data/flavorgraph');
        if (false === $allowed || ! str_starts_with($real, $allowed.'/')) {
            return null;
        }

        return $real;
    }

    /**
     * @return list<array{kind: string, spice_fr: string, slug: string, aromatic_group_id: string}>|null
     */
    private function readCsv(string $path): ?array
    {
        $handle = fopen($path, 'rb');
        if (false === $handle) {
            return null;
        }

        $header = fgetcsv($handle, escape: '\\');
        if (false === $header || ! \in_array('slug', $header, true)) {
            fclose($handle);

            return null;
        }

        $idx = array_flip($header);
        $rows = [];
        while (false !== ($cols = fgetcsv($handle, escape: '\\'))) {
            $rows[] = [
                'kind' => $cols[$idx['kind']] ?? '',
                'spice_fr' => $cols[$idx['spice_fr']] ?? '',
                'slug' => $cols[$idx['slug']] ?? '',
                'aromatic_group_id' => $cols[$idx['aromatic_group_id']] ?? '',
            ];
        }
        fclose($handle);

        return $rows;
    }

    private function resolveReviewGroup(): AromaticGroups
    {
        $repo = $this->em->getRepository(AromaticGroups::class);
        $group = $repo->findOneBy([
            'name' => self::REVIEW_GROUP_NAME,
        ]);
        if ($group instanceof AromaticGroups) {
            return $group;
        }

        $group = new AromaticGroups();
        $group->setName(self::REVIEW_GROUP_NAME);
        $group->setColor(self::REVIEW_GROUP_COLOR);
        $now = new \DateTimeImmutable();
        $group->setCreatedAt($now);
        $group->setUpdatedAt($now);
        $this->em->persist($group);

        return $group;
    }

    /**
     * @return array<int, AromaticGroups>
     */
    private function preloadGroups(): array
    {
        $byId = [];
        foreach ($this->em->getRepository(AromaticGroups::class)->findAll() as $group) {
            $byId[$group->getId()] = $group;
        }

        return $byId;
    }

    private function softDeleteRemoved(): int
    {
        $count = 0;
        foreach (self::DELETED_SLUGS as $slug) {
            $spice = $this->spicesRepository->findOneBy([
                'slug' => $slug,
            ]);
            if ($spice instanceof Spices && null === $spice->getDeletedAt()) {
                $spice->setDeletedAt(new \DateTimeImmutable());
                ++$count;
            }
        }

        return $count;
    }
}
