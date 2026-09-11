<?php

declare(strict_types=1);

namespace App\Service\PubChem;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PubChemPropertyFetcher
{
    private const string PUBCHEM_BASE = 'https://pubchem.ncbi.nlm.nih.gov/rest/pug';

    private const string INCHI_KEY_PATTERN = '/^[A-Z]{14}-[A-Z]{10}-[A-Z]$/';

    /**
     * Doit correspondre à AromaticCompound::$formula (`length: 30`).
     */
    private const int FORMULA_MAX_LENGTH = 30;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function fetch(string $cas): PubChemCompoundProperties
    {
        $url = self::PUBCHEM_BASE.'/compound/name/'.urlencode($cas).'/property/XLogP,MolecularFormula,InChIKey/JSON';

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
                'max_duration' => 15,
            ]);

            if (200 !== $response->getStatusCode()) {
                return new PubChemCompoundProperties(null, null, null, null);
            }

            $data = $response->toArray();
            $props = $data['PropertyTable']['Properties'][0] ?? null;
            if (! is_array($props)) {
                return new PubChemCompoundProperties(null, null, null, null);
            }

            return new PubChemCompoundProperties(
                logP: $this->extractLogP($props),
                formula: $this->extractFormula($props),
                cid: $this->extractCid($props),
                inchiKey: $this->extractInchiKey($props),
            );
        } catch (ExceptionInterface) {
            return new PubChemCompoundProperties(null, null, null, null);
        }
    }

    /**
     * @param array<string, mixed> $props
     */
    private function extractLogP(array $props): ?float
    {
        $raw = $props['XLogP'] ?? null;

        return is_numeric($raw) && is_finite((float) $raw) ? (float) $raw : null;
    }

    /**
     * @param array<string, mixed> $props
     */
    private function extractFormula(array $props): ?string
    {
        $raw = $props['MolecularFormula'] ?? null;

        return is_string($raw) && \strlen($raw) <= self::FORMULA_MAX_LENGTH ? $raw : null;
    }

    /**
     * @param array<string, mixed> $props
     */
    private function extractCid(array $props): ?int
    {
        $raw = $props['CID'] ?? null;

        if (is_int($raw) && $raw > 0) {
            return $raw;
        }

        if (is_string($raw) && ctype_digit($raw) && (int) $raw > 0) {
            return (int) $raw;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $props
     */
    private function extractInchiKey(array $props): ?string
    {
        $raw = $props['InChIKey'] ?? null;

        return is_string($raw) && 1 === preg_match(self::INCHI_KEY_PATTERN, $raw) ? $raw : null;
    }
}
