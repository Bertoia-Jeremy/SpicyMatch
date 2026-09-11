<?php

declare(strict_types=1);

namespace App\Service\PubChem;

final readonly class PubChemCompoundProperties
{
    public function __construct(
        public ?float $logP,
        public ?string $formula,
        public ?int $cid,
        public ?string $inchiKey,
    ) {
    }
}
