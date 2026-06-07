<?php

declare(strict_types=1);

namespace App\Entity\Translation;

/**
 * Contrat commun des entités de traduction (pattern Translation Table).
 *
 * Chaque entité traduisible (Spices, AromaticCompound, …) possède une table
 * `*_translation` dédiée : une ligne par (entité, locale). Le contenu FR
 * canonique reste sur l'entité d'origine et sert de fallback (COALESCE) ;
 * les lignes de traduction n'existent que pour les locales non-FR renseignées.
 *
 * Choix d'architecture (cf. plan i18n) : Translation Table plutôt que
 * Gedmo/Knp, pour permettre une hydratation BATCH (JOIN filtré locale +
 * COALESCE) sans listener postLoad → zéro N+1 sur le hot-path du moteur OAV.
 */
interface TranslationInterface
{
    public function getLocale(): string;

    public function setLocale(string $locale): static;

    /**
     * Traduction relue/validée par un humain. false = amorce (copie FR) à
     * retravailler. Le seed ne doit JAMAIS écraser une ligne reviewed=true,
     * même avec --overwrite.
     */
    public function isReviewed(): bool;

    public function setReviewed(bool $reviewed): static;
}
