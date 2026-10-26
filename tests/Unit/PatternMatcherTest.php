<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Unit;

use Rafiquljakir\FynoVerify\Support\PatternMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PatternMatcherTest extends TestCase
{
    public function test_it_normalizes_uris_including_the_root(): void
    {
        $this->assertSame('/', PatternMatcher::normalizeUri('/'));
        $this->assertSame('/', PatternMatcher::normalizeUri(''));
        $this->assertSame('api/users', PatternMatcher::normalizeUri('/api/users/'));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function cases(): array
    {
        return [
            'prefix wildcard matches' => ['api/*', 'api/documents/{document}', true],
            'prefix wildcard does not match other prefix' => ['api/*', 'web/documents', false],
            'exact match' => ['api/ping', 'api/ping', true],
            'exact does not match longer' => ['api/ping', 'api/ping/more', false],
            'leading slash in pattern is normalized' => ['/api/*', 'api/x', true],
            'root pattern matches root' => ['/', '/', true],
            'root pattern does not match others' => ['/', 'api/x', false],
            'star matches root' => ['*', '/', true],
            'regex metacharacters are literal' => ['api/v1.0/*', 'api/v1x0/y', false],
            'dots match literally' => ['api/v1.0/*', 'api/v1.0/y', true],
            'case sensitive' => ['api/*', 'API/x', false],
        ];
    }

    #[DataProvider('cases')]
    public function test_matching(string $pattern, string $uri, bool $expected): void
    {
        $this->assertSame($expected, (new PatternMatcher)->matches($pattern, $uri));
    }

    public function test_matches_any(): void
    {
        $matcher = new PatternMatcher;

        $this->assertTrue($matcher->matchesAny(['web/*', 'api/*'], 'api/x'));
        $this->assertFalse($matcher->matchesAny([], 'api/x'));
    }

    public function test_pattern_validation(): void
    {
        $matcher = new PatternMatcher;

        $this->assertNull($matcher->validatePattern('api/*'));
        $this->assertNotNull($matcher->validatePattern(''));
        $this->assertNotNull($matcher->validatePattern('   '));
        $this->assertNotNull($matcher->validatePattern(42));
        $this->assertNotNull($matcher->validatePattern("api/\n*"));
    }
}
