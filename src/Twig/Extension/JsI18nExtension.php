<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose tout le domaine de traduction `js` au front, en un seul bloc JSON.
 *
 * Évite de lister chaque clé à la main dans base.html.twig (source de drift :
 * une clé ajoutée au catalogue `js.*.yaml` mais oubliée dans le template
 * tombait silencieusement en fallback). Ici, toute clé du domaine `js` est
 * automatiquement disponible côté JS via assets/i18n.js (`t('clé')`).
 *
 * Sécurité : JSON_HEX_TAG|APOS|QUOT|AMP neutralise tout `</script>`, quote ou
 * apostrophe dans une valeur → impossible de casser le contexte
 * <script type="application/json">. Le résultat est destiné à |raw.
 */
final class JsI18nExtension extends AbstractExtension
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('js_i18n_json', $this->jsI18nJson(...), [
                'is_safe' => ['html'],
            ]),
        ];
    }

    /**
     * Retourne le catalogue du domaine `js` (locale courante) en JSON durci.
     */
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

            // Inclut le fallback (clés présentes en fr mais pas dans la locale).
            foreach ($catalogue->getFallbackCatalogue()?->all('js') ?? [] as $key => $value) {
                $all[$key] ??= $value;
            }

            return $all;
        }

        return [];
    }
}
