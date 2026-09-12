<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Achievement;
use App\Entity\AchievementTranslation;
use App\Entity\AlchemyFlavors;
use App\Entity\AlchemyFlavorsTranslation;
use App\Entity\AromaticCompound;
use App\Entity\AromaticCompoundTranslation;
use App\Entity\AromaticGroups;
use App\Entity\AromaticGroupsTranslation;
use App\Entity\CookingTips;
use App\Entity\CookingTipsTranslation;
use App\Entity\PreparationMethods;
use App\Entity\PreparationMethodsTranslation;
use App\Entity\PreparationTips;
use App\Entity\PreparationTipsTranslation;
use App\Entity\Spices;
use App\Entity\SpiceTranslation;
use App\Entity\SpicyType;
use App\Entity\SpicyTypeTranslation;
use App\Entity\Translation\TranslatableInterface;
use App\Entity\Translation\TranslationInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Amorce les lignes de traduction de TOUTES les entités traduisibles (pattern
 * Translation Table) pour une locale cible, en copiant le contenu FR canonique
 * comme point de départ (à retravailler ensuite par un traducteur).
 *
 * Idempotent : ne touche pas une traduction déjà existante (sauf --overwrite,
 * qui met à jour la ligne en place — pas de delete/insert → pas de collision
 * sur l'unique (owner, locale)).
 * Le FR n'a JAMAIS besoin de ligne (il vit sur l'entité et sert de fallback COALESCE).
 *
 *   app:i18n:seed-translations en
 *   app:i18n:seed-translations es --overwrite
 */
#[AsCommand(
    name: 'app:i18n:seed-translations',
    description: 'Amorce les traductions de toutes les entités pour une locale (copie du FR canonique).',
)]
final class SeedTranslationsCommand extends Command
{
    private const SUPPORTED = ['en', 'es'];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('locale', InputArgument::REQUIRED, 'Locale cible (en|es)')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Met à jour les traductions existantes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locale = (string) $input->getArgument('locale');
        $overwrite = (bool) $input->getOption('overwrite');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $io->error(sprintf('Locale non supportée : %s (attendu : %s).', $locale, implode('|', self::SUPPORTED)));

            return Command::INVALID;
        }

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($this->seeders($locale) as $label => $seeder) {
            [$created, $skipped] = $seeder($overwrite);
            $totalCreated += $created;
            $totalSkipped += $skipped;
            $io->writeln(sprintf('  %-22s %d écrite(s), %d conservée(s)', $label, $created, $skipped));
        }

        $this->em->flush();

        $io->success(sprintf(
            '%d traduction(s) %s écrite(s), %d conservée(s). Contenu = copie FR à retravailler.',
            $totalCreated,
            strtoupper($locale),
            $totalSkipped,
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array<string, callable(bool): array{int, int}>
     */
    private function seeders(string $locale): array
    {
        return [
            'spices' => fn (bool $ow): array => $this->seedEach(
                $this->em->getRepository(Spices::class)->findAll(),
                $locale,
                $ow,
                function (TranslatableInterface $e, ?TranslationInterface $existing) use ($locale): void {
                    \assert($e instanceof Spices);
                    $t = $existing instanceof SpiceTranslation ? $existing : new SpiceTranslation();
                    $t->setName((string) $e->getName())
                        ->setDescription($e->getDescription())
                        ->setCooking($e->getCooking())
                        ->setInformations($e->getInformations())
                        ->setBenefits($e->getBenefits())
                        ->setLocale($locale);

                    if (! $existing instanceof SpiceTranslation) {
                        $e->addTranslation($t);
                        $this->em->persist($t);
                    }
                },
            ),
            'aromatic_groups' => fn (bool $ow): array => $this->seedEach(
                $this->em->getRepository(AromaticGroups::class)->findAll(),
                $locale,
                $ow,
                function (TranslatableInterface $e, ?TranslationInterface $existing) use ($locale): void {
                    \assert($e instanceof AromaticGroups);
                    $t = $existing instanceof AromaticGroupsTranslation ? $existing : new AromaticGroupsTranslation();
                    $t->setName((string) $e->getName())
                        ->setDescription($e->getDescription())
                        ->setCooking($e->getCooking())
                        ->setInformations($e->getInformations())
                        ->setLocale($locale);

                    if (! $existing instanceof AromaticGroupsTranslation) {
                        $e->addTranslation($t);
                        $this->em->persist($t);
                    }
                },
            ),
            'aromatic_compounds' => fn (bool $ow): array => $this->seedEach(
                $this->em->getRepository(AromaticCompound::class)->findAll(),
                $locale,
                $ow,
                function (TranslatableInterface $e, ?TranslationInterface $existing) use ($locale): void {
                    \assert($e instanceof AromaticCompound);
                    $t = $existing instanceof AromaticCompoundTranslation ? $existing : new AromaticCompoundTranslation();
                    $t->setName((string) $e->getName())
                        ->setDescription($e->getDescription())
                        ->setCooking($e->getCooking())
                        ->setInformations($e->getInformations())
                        ->setLocale($locale);

                    if (! $existing instanceof AromaticCompoundTranslation) {
                        $e->addTranslation($t);
                        $this->em->persist($t);
                    }
                },
            ),
            'achievements' => fn (bool $ow): array => $this->seedEach(
                $this->em->getRepository(Achievement::class)->findAll(),
                $locale,
                $ow,
                function (TranslatableInterface $e, ?TranslationInterface $existing) use ($locale): void {
                    \assert($e instanceof Achievement);
                    $t = $existing instanceof AchievementTranslation ? $existing : new AchievementTranslation();
                    $t->setName($e->getName())
                        ->setDescription($e->getDescription())
                        ->setLocale($locale);

                    if (! $existing instanceof AchievementTranslation) {
                        $e->addTranslation($t);
                        $this->em->persist($t);
                    }
                },
            ),
            'cooking_tips' => fn (bool $ow): array => $this->seedEach(
                $this->em->getRepository(CookingTips::class)->findAll(),
                $locale,
                $ow,
                function (TranslatableInterface $e, ?TranslationInterface $existing) use ($locale): void {
                    \assert($e instanceof CookingTips);
                    $t = $existing instanceof CookingTipsTranslation ? $existing : new CookingTipsTranslation();
                    $t->setCookingStep($e->getCookingStep())
                        ->setText($e->getText())
                        ->setTitle($e->getTitle())
                        ->setAdvantages($e->getAdvantages())
                        ->setLocale($locale);

                    if (! $existing instanceof CookingTipsTranslation) {
                        $e->addTranslation($t);
                        $this->em->persist($t);
                    }
                },
            ),
            'preparation_methods' => fn (bool $ow): array => $this->seedEach(
                $this->em->getRepository(PreparationMethods::class)->findAll(),
                $locale,
                $ow,
                function (TranslatableInterface $e, ?TranslationInterface $existing) use ($locale): void {
                    \assert($e instanceof PreparationMethods);
                    $t = $existing instanceof PreparationMethodsTranslation ? $existing : new PreparationMethodsTranslation();
                    $t->setName($e->getName())
                        ->setDescription($e->getDescription())
                        ->setTools($e->getTools())
                        ->setInformations($e->getInformations())
                        ->setAdvice($e->getAdvice())
                        ->setLocale($locale);

                    if (! $existing instanceof PreparationMethodsTranslation) {
                        $e->addTranslation($t);
                        $this->em->persist($t);
                    }
                },
            ),
            'alchemy_flavors' => fn (bool $ow): array => $this->seedEach(
                $this->em->getRepository(AlchemyFlavors::class)->findAll(),
                $locale,
                $ow,
                function (TranslatableInterface $e, ?TranslationInterface $existing) use ($locale): void {
                    \assert($e instanceof AlchemyFlavors);
                    $t = $existing instanceof AlchemyFlavorsTranslation ? $existing : new AlchemyFlavorsTranslation();
                    $t->setName((string) $e->getName())
                        ->setDescription($e->getDescription())
                        ->setCooking($e->getCooking())
                        ->setInformations($e->getInformations())
                        ->setLocale($locale);

                    if (! $existing instanceof AlchemyFlavorsTranslation) {
                        $e->addTranslation($t);
                        $this->em->persist($t);
                    }
                },
            ),
            'spicy_types' => fn (bool $ow): array => $this->seedEach(
                $this->em->getRepository(SpicyType::class)->findAll(),
                $locale,
                $ow,
                function (TranslatableInterface $e, ?TranslationInterface $existing) use ($locale): void {
                    \assert($e instanceof SpicyType);
                    $t = $existing instanceof SpicyTypeTranslation ? $existing : new SpicyTypeTranslation();
                    $t->setName((string) $e->getName())
                        ->setDescription($e->getDescription())
                        ->setCooking($e->getCooking())
                        ->setInformations($e->getInformations())
                        ->setLocale($locale);

                    if (! $existing instanceof SpicyTypeTranslation) {
                        $e->addTranslation($t);
                        $this->em->persist($t);
                    }
                },
            ),
            'preparation_tips' => fn (bool $ow): array => $this->seedEach(
                $this->em->getRepository(PreparationTips::class)->findAll(),
                $locale,
                $ow,
                function (TranslatableInterface $e, ?TranslationInterface $existing) use ($locale): void {
                    \assert($e instanceof PreparationTips);
                    $t = $existing instanceof PreparationTipsTranslation ? $existing : new PreparationTipsTranslation();
                    $t->setText($e->getText())
                        ->setTitle($e->getTitle())
                        ->setAdvantages($e->getAdvantages())
                        ->setLocale($locale);

                    if (! $existing instanceof PreparationTipsTranslation) {
                        $e->addTranslation($t);
                        $this->em->persist($t);
                    }
                },
            ),
        ];
    }

    /**
     * @param list<TranslatableInterface>                                  $owners
     * @param callable(TranslatableInterface, ?TranslationInterface): void $upsert
     *
     * @return array{int, int} [écrites, conservées]
     */
    private function seedEach(array $owners, string $locale, bool $overwrite, callable $upsert): array
    {
        $created = 0;
        $skipped = 0;

        foreach ($owners as $owner) {
            $existing = $owner->getTranslation($locale);

            // Ne JAMAIS écraser une traduction relue par un humain, même avec --overwrite.
            if ($existing !== null && $existing->isReviewed()) {
                ++$skipped;
                continue;
            }

            if ($existing !== null && ! $overwrite) {
                ++$skipped;
                continue;
            }

            $upsert($owner, $existing);
            ++$created;
        }

        return [$created, $skipped];
    }
}
