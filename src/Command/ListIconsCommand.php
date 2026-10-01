<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Icon\IconUsageCollector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'app:icons:list',
    description: 'Recense les jetons Font Awesome utilisés (templates, assets, sources, BDD) pour le subset.',
)]
final class ListIconsCommand extends Command
{
    public const string DEFAULT_OUTPUT = 'var/fontawesome-tokens.json';

    public function __construct(
        private readonly IconUsageCollector $collector,
        private readonly Filesystem $filesystem,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('output', null, InputOption::VALUE_REQUIRED, 'Fichier JSON de sortie', self::DEFAULT_OUTPUT);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $target = (string) $input->getOption('output');
        $tokens = $this->collector->collect();

        $this->filesystem->dumpFile($target, json_encode([
            'tokens' => $tokens,
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR) . "\n");
        $io->success(\sprintf('%d jetons écrits dans %s', \count($tokens), $target));

        return Command::SUCCESS;
    }
}
