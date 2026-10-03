<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:seo:check',
    description: 'Échoue si SEO_INDEXABLE est faux (robots.txt en Disallow: /). À lancer au déploiement prod.',
)]
final class CheckSeoIndexableCommand extends Command
{
    public function __construct(
        #[Autowire(env: 'bool:SEO_INDEXABLE')]
        private readonly bool $indexable,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (! $this->indexable) {
            $io->error('SEO_INDEXABLE=false : robots.txt bloque tout le site (Disallow: /). Passer SEO_INDEXABLE=true en prod.');

            return Command::FAILURE;
        }

        $io->success('SEO_INDEXABLE=true : le site est indexable.');

        return Command::SUCCESS;
    }
}
