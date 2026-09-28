<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

final readonly class JsI18nExtension
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    #[AsTwigFunction(name: 'js_i18n_json', isSafe: ['html'])]
    public function jsI18nJson(): string
    {
        $messages = $this->collectJsDomain();

        return json_encode(
            $messages,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE,
        ) ?: '{}';
    }

    /**
     * @return array<string, string>
     */
    private function collectJsDomain(): array
    {
        $locale = $this->translator instanceof LocaleAwareInterface
            ? $this->translator->getLocale()
            : 'fr';

        if ($this->translator instanceof TranslatorBagInterface) {
            $catalogue = $this->translator->getCatalogue($locale);
            /** @var array<string, string> $all */
            $all = $catalogue->all('js');

            foreach ($catalogue->getFallbackCatalogue()?->all('js') ?? [] as $key => $value) {
                $all[$key] ??= $value;
            }

            return $all;
        }

        return [];
    }
}
