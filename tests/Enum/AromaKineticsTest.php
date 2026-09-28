<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\AromaKinetics;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AromaKineticsTest extends TestCase
{
    #[DataProvider('boilingPointProvider')]
    public function testFromBoilingPointMapsToKinetics(?int $bp, ?AromaKinetics $expected): void
    {
        self::assertSame($expected, AromaKinetics::fromBoilingPoint($bp));
    }

    /**
     * @return iterable<string, array{?int, ?AromaKinetics}>
     */
    public static function boilingPointProvider(): iterable
    {
        yield 'null input' => [null, null];
        yield 'very volatile (acetone 56C) is head' => [56, AromaKinetics::HEAD];
        yield 'just below 150 is head' => [149, AromaKinetics::HEAD];
        yield 'inclusive lower boundary 150 is heart' => [150, AromaKinetics::HEART];
        yield 'linalool 198C is heart' => [198, AromaKinetics::HEART];
        yield 'inclusive upper boundary 250 is heart' => [250, AromaKinetics::HEART];
        yield 'eugenol 254C is base' => [254, AromaKinetics::BASE];
        yield 'capsaicin 410C is base' => [410, AromaKinetics::BASE];
    }

    #[DataProvider('labelProvider')]
    public function testLabelIsTranslationKey(AromaKinetics $kinetics, string $expected): void
    {
        self::assertSame($expected, $kinetics->label());
    }

    /**
     * @return iterable<string, array{AromaKinetics, string}>
     */
    public static function labelProvider(): iterable
    {
        yield 'head' => [AromaKinetics::HEAD, 'enum.kinetics.head'];
        yield 'heart' => [AromaKinetics::HEART, 'enum.kinetics.heart'];
        yield 'base' => [AromaKinetics::BASE, 'enum.kinetics.base'];
    }

    public function testEnumIsLowercaseStringBacked(): void
    {
        self::assertSame('head', AromaKinetics::HEAD->value);
        self::assertSame('heart', AromaKinetics::HEART->value);
        self::assertSame('base', AromaKinetics::BASE->value);
        self::assertSame(AromaKinetics::HEART, AromaKinetics::from('heart'));
    }
}
