<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Support;

/**
 * Deterministic glob matcher for route URIs. The only wildcard is "*" (any characters,
 * including "/"). Matching is case-sensitive and applied to the normalized URI.
 */
final class PatternMatcher
{
    /**
     * Normalize a route URI: no leading/trailing slash; the root route becomes "/".
     */
    public static function normalizeUri(string $uri): string
    {
        $trimmed = trim($uri, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    /**
     * @return string|null An error message, or null when the pattern is usable.
     */
    public function validatePattern(mixed $pattern): ?string
    {
        if (! is_string($pattern)) {
            return 'pattern must be a string, got '.get_debug_type($pattern);
        }

        if (trim($pattern) === '') {
            return 'pattern must not be empty';
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $pattern) === 1) {
            return 'pattern must not contain control characters';
        }

        return null;
    }

    public function matches(string $pattern, string $uri): bool
    {
        $regex = '#^'.str_replace('\*', '.*', preg_quote(self::normalizeUri($pattern), '#')).'$#su';

        return preg_match($regex, self::normalizeUri($uri)) === 1;
    }

    /**
     * @param  list<string>  $patterns
     */
    public function matchesAny(array $patterns, string $uri): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->matches($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }
}
