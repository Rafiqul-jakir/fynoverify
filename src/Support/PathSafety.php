<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Support;

/**
 * Shared path helpers for evidence paths and report destinations.
 */
final class PathSafety
{
    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:/', $path) === 1;
    }

    /**
     * Split a project-relative path on "/" and "\", dropping empty and "." segments.
     *
     * @return list<string>
     */
    public static function segments(string $path): array
    {
        $segments = preg_split('#[\\\\/]+#', $path) ?: [];

        return array_values(array_filter($segments, static fn (string $s): bool => $s !== '' && $s !== '.'));
    }

    /**
     * @param  list<string>  $segments
     */
    public static function containsTraversal(array $segments): bool
    {
        return in_array('..', $segments, true);
    }

    /**
     * True when $path equals $root or lies beneath it. Both must already be real paths.
     */
    public static function within(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
    }
}
