<?php

declare(strict_types=1);

namespace App\Tests\Service\PubChem;

use App\Service\PubChem\PubChemPropertyFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PubChemPropertyFetcherTest extends TestCase
{
    public function testFetchExtractsAllPropertiesFromNominalResponse(): void
    {
        $body = json_encode([
            'PropertyTable' => [
                'Properties' => [[
                    'CID' => 3314,
                    'XLogP' => 2.0,
                    'MolecularFormula' => 'C10H12O2',
                    'InChIKey' => 'RRAFCDWBNXTKKO-UHFFFAOYSA-N',
                ]],
            ],
        ], \JSON_THROW_ON_ERROR);

        $fetcher = new PubChemPropertyFetcher(new MockHttpClient(new MockResponse($body)));
        $properties = $fetcher->fetch('97-53-0');

        self::assertSame(2.0, $properties->logP);
        self::assertSame('C10H12O2', $properties->formula);
        self::assertSame(3314, $properties->cid);
        self::assertSame('RRAFCDWBNXTKKO-UHFFFAOYSA-N', $properties->inchiKey);
    }

    public function testFetchRejectsMalformedInchiKey(): void
    {
        $body = json_encode([
            'PropertyTable' => [
                'Properties' => [[
                    'CID' => 3314,
                    'InChIKey' => 'not-a-valid-inchikey',
                ]],
            ],
        ], \JSON_THROW_ON_ERROR);

        $fetcher = new PubChemPropertyFetcher(new MockHttpClient(new MockResponse($body)));
        $properties = $fetcher->fetch('97-53-0');

        self::assertNull($properties->inchiKey);
        self::assertSame(3314, $properties->cid);
    }

    public function testFetchRejectsNonNumericCid(): void
    {
        $body = json_encode([
            'PropertyTable' => [
                'Properties' => [[
                    'CID' => 'not-a-number',
                ]],
            ],
        ], \JSON_THROW_ON_ERROR);

        $fetcher = new PubChemPropertyFetcher(new MockHttpClient(new MockResponse($body)));
        $properties = $fetcher->fetch('97-53-0');

        self::assertNull($properties->cid);
    }

    public function testFetchRejectsZeroCid(): void
    {
        $body = json_encode([
            'PropertyTable' => [
                'Properties' => [[
                    'CID' => 0,
                ]],
            ],
        ], \JSON_THROW_ON_ERROR);

        $fetcher = new PubChemPropertyFetcher(new MockHttpClient(new MockResponse($body)));
        $properties = $fetcher->fetch('97-53-0');

        self::assertNull($properties->cid);
    }

    public function testFetchReturnsAllNullOnNonSuccessStatus(): void
    {
        $fetcher = new PubChemPropertyFetcher(new MockHttpClient(new MockResponse('', [
            'http_code' => 404,
        ])));
        $properties = $fetcher->fetch('unknown-cas');

        self::assertNull($properties->logP);
        self::assertNull($properties->formula);
        self::assertNull($properties->cid);
        self::assertNull($properties->inchiKey);
    }

    public function testFetchReturnsAllNullOnTransportException(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new TransportException('Network error');
        });

        $fetcher = new PubChemPropertyFetcher($client);
        $properties = $fetcher->fetch('97-53-0');

        self::assertNull($properties->logP);
        self::assertNull($properties->formula);
        self::assertNull($properties->cid);
        self::assertNull($properties->inchiKey);
    }

    public function testFetchReturnsAllNullOnMalformedJson(): void
    {
        $fetcher = new PubChemPropertyFetcher(new MockHttpClient(new MockResponse('<html>not json</html>')));
        $properties = $fetcher->fetch('97-53-0');

        self::assertNull($properties->logP);
        self::assertNull($properties->formula);
        self::assertNull($properties->cid);
        self::assertNull($properties->inchiKey);
    }

    public function testFetchRejectsOverLongFormula(): void
    {
        $body = json_encode([
            'PropertyTable' => [
                'Properties' => [[
                    'MolecularFormula' => str_repeat('C', 31),
                ]],
            ],
        ], \JSON_THROW_ON_ERROR);

        $fetcher = new PubChemPropertyFetcher(new MockHttpClient(new MockResponse($body)));
        $properties = $fetcher->fetch('97-53-0');

        self::assertNull($properties->formula);
    }
}
