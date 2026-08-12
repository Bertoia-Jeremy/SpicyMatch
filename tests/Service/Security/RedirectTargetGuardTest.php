<?php

declare(strict_types=1);

namespace App\Tests\Service\Security;

use App\Security\RedirectTargetGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RedirectTargetGuardTest extends TestCase
{
    private RedirectTargetGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new RedirectTargetGuard();
    }

    #[DataProvider('safeTargetsProvider')]
    public function testIsSafeAcceptsSafeTargets(?string $target): void
    {
        self::assertTrue($this->guard->isSafe($target));
    }

    public static function safeTargetsProvider(): iterable
    {
        yield 'relative path' => ['/fr/education/briefing'];
        yield 'relative path with query string' => ['/fr/education/briefing?mode=survival&difficulty=easy'];
        yield 'root path' => ['/'];
    }

    #[DataProvider('unsafeTargetsProvider')]
    public function testIsSafeRejectsUnsafeTargets(?string $target): void
    {
        self::assertFalse($this->guard->isSafe($target));
    }

    public static function unsafeTargetsProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'protocol-relative' => ['//evil.com/phishing'];
        yield 'absolute http url' => ['http://evil.com'];
        yield 'absolute https url' => ['https://evil.com'];
        yield 'javascript scheme embedded' => ['/redirect?next=javascript://alert(1)'];
        yield 'no leading slash' => ['evil.com'];
        yield 'backslash host bypass' => ['/\\evil.com'];
        yield 'backslash-slash host bypass' => ['/\\/evil.com'];
        yield 'double backslash host bypass' => ['/\\\\evil.com'];
        yield 'tab host bypass' => ["/\t/evil.com"];
        yield 'newline host bypass' => ["/\n/evil.com"];
        yield 'carriage return host bypass' => ["/\r/evil.com"];
        yield 'embedded space' => ['/ /evil.com'];
    }

    #[DataProvider('safeOrNullProvider')]
    public function testSafeOrNull(mixed $target, ?string $expected): void
    {
        self::assertSame($expected, $this->guard->safeOrNull($target));
    }

    public static function safeOrNullProvider(): iterable
    {
        yield 'safe path passes through' => ['/fr/education/briefing', '/fr/education/briefing'];
        yield 'unsafe path becomes null' => ['https://evil.com', null];
        yield 'non-string becomes null' => [123, null];
        yield 'null stays null' => [null, null];
    }
}
