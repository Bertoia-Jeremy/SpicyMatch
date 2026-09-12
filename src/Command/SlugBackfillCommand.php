<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AlchemyFlavors;
use App\Entity\AlchemyFlavorsTranslation;
use App\Entity\AromaticCompound;
use App\Entity\AromaticCompoundTranslation;
use App\Entity\AromaticGroups;
use App\Entity\AromaticGroupsTranslation;
use App\Entity\PreparationMethods;
use App\Entity\PreparationMethodsTranslation;
use App\Entity\Spices;
use App\Entity\SpiceTranslation;
use App\Entity\SpicyType;
use App\Entity\SpicyTypeTranslation;
use App\Entity\Translation\Sluggable;
use App\Entity\Translation\TranslationInterface;
use App\Service\Slug\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:slug:backfill',
    description: 'Amorce les slugs manquants des entités traduisibles et de leurs traductions.',
)]
final class SlugBackfillCommand extends Command
{
    /**
     * @var list<class-string>
     */
    private const CLASSES = [
        Spices::class,
        AromaticGroups::class,
        AromaticCompound::class,
        PreparationMethods::class,
        AlchemyFlavors::class,
        SpicyType::class,
        SpiceTranslation::class,
        AromaticGroupsTranslation::class,
        AromaticCompoundTranslation::class,
        PreparationMethodsTranslation::class,
        AlchemyFlavorsTranslation::class,
        SpicyTypeTranslation::class,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SlugGenerator $generator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les slugs sans les persister');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $total = 0;

        foreach (self::CLASSES as $class) {
            $rows = $this->em->getRepository($class)
                ->findAll();

            /** @var array<string, array<string, true>> $used */
            $used = [];

            foreach ($rows as $row) {
                $slug = $row->getSlug();
                if ($slug !== null && $slug !== '') {
                    $used[$this->scope($row)][$slug] = true;
                }
            }

            $written = 0;

            foreach ($rows as $row) {
                if ($row->getSlug() !== null && $row->getSlug() !== '') {
                    continue;
                }

                $name = $row->getName();
                if ($name === null || $name === '') {
                    continue;
                }

                $scope = $this->scope($row);
                $slug = $this->generator->unique(
                    $name,
                    static fn (string $candidate): bool => isset($used[$scope][$candidate]),
                );
                $used[$scope][$slug] = true;

                if (! $dryRun) {
                    $row->setSlug($slug);
                }

                ++$written;
                ++$total;
            }

            $short = substr((string) strrchr('\\' . $class, '\\'), 1);
            $io->writeln(sprintf('  %-32s %d slug(s)', $short, $written));
        }

        if (! $dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf('%d slug(s) %s.', $total, $dryRun ? 'à générer (dry-run)' : 'générés'));

        return Command::SUCCESS;
    }

    private function scope(Sluggable $row): string
    {
        $key = $row::class;

        if ($row instanceof TranslationInterface) {
            $key .= '|' . $row->getLocale();
        }

        return $key;
    }
}
