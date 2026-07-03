<?php

declare(strict_types=1);

namespace App\Tests\ValueObject;

use App\ValueObject\CasNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CasNumberTest extends TestCase
{
    // ── CAS réels des 15 composés (checksum doit valider) ──────────────────────

    /**
     * @return array<string, array{string}>
     */
    public static function validCasProvider(): array
    {
        return [
            'eugenol' => ['97-53-0'],
            'cinnamaldehyde' => ['104-55-2'],
            'trans-anethole' => ['104-46-1'],
            'estragole' => ['140-67-0'],
            'linalool' => ['78-70-6'],
            'terpinen-4-ol' => ['562-74-3'],
            'geraniol' => ['106-24-1'],
            'd-limonene' => ['5989-27-5'],
            'capsaicin' => ['404-86-4'],
            'piperine' => ['94-62-2'],
            'thymol' => ['89-83-8'],
            'carvacrol' => ['499-75-2'],
            'curcumin' => ['458-37-7'],
            'zingerone' => ['122-48-5'],
            'safranal' => ['116-26-7'],
            'r-carvone' => ['6485-40-1'],
            'water-shortest' => ['7732-18-5'],
        ];
    }

    #[DataProvider('validCasProvider')]
    public function testValidCasIsAccepted(string $cas): void
    {
        self::assertTrue(CasNumber::isValid($cas), $cas.' devrait être valide');
        self::assertSame($cas, (string) CasNumber::fromString($cas));
    }

    // ── Checksum invalide ───────────────────────────────────────────────────────

    public function testWrongChecksumIsRejected(): void
    {
        // 97-53-0 est valide ; 97-53-1 a un mauvais checksum
        self::assertFalse(CasNumber::isValid('97-53-1'));
    }

    public function testTransposedDigitsFailChecksum(): void
    {
        // Eugénol 97-53-0 → digits transposés 79-53-0 : checksum ne colle plus
        self::assertFalse(CasNumber::isValid('79-53-0'));
    }

    public function testFromStringThrowsOnBadChecksum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('chiffre de contrôle');
        CasNumber::fromString('97-53-1');
    }

    // ── Format invalide ───────────────────────────────────────────────────────

    /**
     * @return array<string, array{string}>
     */
    public static function malformedProvider(): array
    {
        return [
            'empty' => [''],
            'no dashes' => ['97530'],
            'single block' => ['97-530'],
            'block2 one digit' => ['97-5-3'],
            'check two digits' => ['97-53-00'],
            'letters' => ['9A-53-0'],
            'block1 too long' => ['12345678-90-1'],
            'block1 one digit' => ['9-53-0'],
        ];
    }

    #[DataProvider('malformedProvider')]
    public function testMalformedIsRejected(string $cas): void
    {
        self::assertFalse(CasNumber::isValid($cas), $cas.' devrait être rejeté');
    }

    public function testFromStringThrowsOnMalformed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('format invalide');
        CasNumber::fromString('not-a-cas');
    }

    // ── Normalisation / robustesse ──────────────────────────────────────────────

    public function testTrimsWhitespace(): void
    {
        self::assertTrue(CasNumber::isValid('  97-53-0  '));
        self::assertSame('97-53-0', (string) CasNumber::fromString('  97-53-0  '));
    }

    // ── equals ───────────────────────────────────────────────────────────────

    public function testEquals(): void
    {
        $a = CasNumber::fromString('97-53-0');
        $b = CasNumber::fromString('97-53-0');
        $c = CasNumber::fromString('104-55-2');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testIsReadonly(): void
    {
        self::assertTrue((new \ReflectionClass(CasNumber::class))->isReadOnly());
    }
}
