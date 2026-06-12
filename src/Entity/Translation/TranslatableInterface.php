<?php

declare(strict_types=1);

namespace App\Entity\Translation;

/**
 * Contrat des entités traduisibles (côté propriétaire du pattern Translation Table).
 *
 * Seul le lookup générique est exposé ici : getTranslation(locale). Les méthodes
 * addTranslation()/removeTranslation() restent typées concrètement sur chaque
 * entité (le type de la traduction diffère) — non unifiables sans casser le
 * typage. Le retour covariant (?XxxTranslation) satisfait ?TranslationInterface.
 */
interface TranslatableInterface
{
    public function getTranslation(string $locale): ?TranslationInterface;
}
