<?php

declare(strict_types=1);

namespace App\Service\Icon;

use App\Entity\Achievement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

final readonly class IconUsageCollector
{
    private const string TOKEN_PATTERN = '/(?<![a-z0-9-])fa-([a-z0-9]+(?:-[a-z0-9]+)*)/';

    /**
     * @var list<string>
     */
    private const array SCANNED_DIRECTORIES = ['templates', 'assets', 'src'];

    /**
     * @var list<string>
     */
    private const array EXCLUDED_DIRECTORIES = ['vendor', 'build'];

    /**
     * @var list<string>
     */
    private const array SCANNED_EXTENSIONS = ['*.twig', '*.js', '*.css', '*.php'];

    /**
     * @var list<string>
     */
    private const array STYLE_TOKENS = ['solid', 'regular', 'brands'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<string>
     */
    public function collect(): array
    {
        $tokens = [];
        foreach ($this->sources() as $content) {
            if (preg_match_all(self::TOKEN_PATTERN, $content, $matches) > 0) {
                foreach ($matches[1] as $token) {
                    $tokens[$token] = true;
                }
            }
        }

        foreach (self::STYLE_TOKENS as $style) {
            unset($tokens[$style]);
        }

        $names = array_map(strval(...), array_keys($tokens));
        sort($names);

        return $names;
    }

    /**
     * @return iterable<string>
     */
    private function sources(): iterable
    {
        $finder = new Finder()
            ->files()
            ->in(array_map(fn (string $dir): string => $this->projectDir . '/' . $dir, self::SCANNED_DIRECTORIES))
            ->exclude(self::EXCLUDED_DIRECTORIES)
            ->name(self::SCANNED_EXTENSIONS);

        foreach ($finder as $file) {
            yield $file->getContents();
        }

        yield implode(' ', $this->em->createQueryBuilder()
            ->select('DISTINCT a.icon')
            ->from(Achievement::class, 'a')
            ->getQuery()
            ->getSingleColumnResult());
    }
}
