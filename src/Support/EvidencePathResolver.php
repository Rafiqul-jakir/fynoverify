<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Support;

/**
 * Validates that a configured evidence path is a project-relative path to an existing,
 * readable, regular file inside the project root. File contents are never read or executed.
 *
 * Symlink policy: a symlink is accepted only if its fully resolved target is still inside
 * the project root; anything resolving outside is rejected.
 */
final class EvidencePathResolver
{
    /**
     * Static checks only (no filesystem access).
     *
     * @return string|null An error message, or null when the path is syntactically acceptable.
     */
    public function validateSyntax(string $path): ?string
    {
        $trimmed = trim($path);

        if ($trimmed === '') {
            return 'path must not be empty';
        }

        if (str_contains($trimmed, "\0")) {
            return 'path must not contain NUL bytes';
        }

        if (PathSafety::isAbsolute($trimmed)) {
            return 'path must be project-relative, not absolute';
        }

        $segments = PathSafety::segments($trimmed);

        if ($segments === []) {
            return 'path must name a file';
        }

        if (PathSafety::containsTraversal($segments)) {
            return 'path must not contain ".." segments';
        }

        return null;
    }

    /**
     * @return string|null An error message, or null when the evidence file is acceptable.
     */
    public function check(string $path, string $basePath): ?string
    {
        $syntaxError = $this->validateSyntax($path);

        if ($syntaxError !== null) {
            return $syntaxError;
        }

        $base = realpath($basePath);

        if ($base === false) {
            return 'project base path is not accessible';
        }

        $target = $base.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, PathSafety::segments(trim($path)));
        $real = realpath($target);

        if ($real === false) {
            return 'file does not exist';
        }

        if (! PathSafety::within($real, $base)) {
            return 'path resolves outside the project root';
        }

        if (! is_file($real)) {
            return 'path is not a regular file';
        }

        if (! is_readable($real)) {
            return 'file is not readable';
        }

        return null;
    }
}
