<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Support;

/**
 * Writes a report file to an explicitly requested, project-relative destination.
 *
 * Policy:
 *  - Absolute paths and ".." segments are rejected.
 *  - The extension must match the format (.json, or .md/.markdown).
 *  - Source/config/dependency directories and well-known project files are refused.
 *  - Symlinks are never written through, and the nearest existing parent must resolve
 *    inside the project root.
 *  - An existing file is only replaced when $force is true.
 */
final class ReportWriter
{
    /** Top-level directories that reports must never be written into. */
    private const FORBIDDEN_TOP_LEVEL = [
        'vendor', 'node_modules', '.git', '.github', 'app', 'config', 'routes',
        'database', 'src', 'tests', 'bootstrap',
    ];

    /** File names that reports must never overwrite, wherever they are. */
    private const FORBIDDEN_NAMES = [
        'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
        'readme.md', 'changelog.md', 'license.md',
    ];

    /**
     * @param  'json'|'markdown'  $format
     * @return string The project-relative path that was written (forward slashes).
     *
     * @throws ReportWriteException
     */
    public function write(string $path, string $format, string $content, string $basePath, bool $force): string
    {
        $base = realpath($basePath);

        if ($base === false) {
            throw new ReportWriteException('The project base path is not accessible.');
        }

        $path = trim($path);

        if ($path === '' || str_contains($path, "\0")) {
            throw new ReportWriteException('--output must be a non-empty path without NUL bytes.');
        }

        if (PathSafety::isAbsolute($path)) {
            throw new ReportWriteException('--output must be project-relative; absolute paths are not supported.');
        }

        $segments = PathSafety::segments($path);

        if ($segments === [] || PathSafety::containsTraversal($segments)) {
            throw new ReportWriteException('--output must name a file inside the project and must not contain ".." segments.');
        }

        $fileName = $segments[count($segments) - 1];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = $format === 'json' ? ['json'] : ['md', 'markdown'];

        if (! in_array($extension, $allowed, true)) {
            throw new ReportWriteException(sprintf(
                '--output for --format=%s must end in .%s.',
                $format,
                implode(' or .', $allowed),
            ));
        }

        if (in_array(strtolower($segments[0]), self::FORBIDDEN_TOP_LEVEL, true)
            || in_array(strtolower($fileName), self::FORBIDDEN_NAMES, true)) {
            throw new ReportWriteException(sprintf(
                'Refusing to write a report to "%s": it would be placed in, or replace, a source/config/dependency location. Choose a path such as storage/app/fynoverify/report.%s.',
                implode('/', $segments),
                $allowed[0],
            ));
        }

        $target = $base.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments);
        $directory = dirname($target);

        $probe = $directory;
        while (! file_exists($probe) && $probe !== dirname($probe)) {
            $probe = dirname($probe);
        }

        $realProbe = realpath($probe);

        if ($realProbe === false || ! PathSafety::within($realProbe, $base)) {
            throw new ReportWriteException('--output resolves outside the project root.');
        }

        if (! is_dir($realProbe)) {
            throw new ReportWriteException('--output has a parent path component that is not a directory.');
        }

        if (is_link($target)) {
            throw new ReportWriteException('--output points at a symbolic link; refusing to write through it.');
        }

        if (file_exists($target)) {
            if (! is_file($target)) {
                throw new ReportWriteException('--output exists and is not a regular file.');
            }

            if (! $force) {
                throw new ReportWriteException(sprintf('"%s" already exists. Re-run with --force to replace it.', implode('/', $segments)));
            }
        }

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new ReportWriteException(sprintf('Could not create the directory for "%s".', implode('/', $segments)));
        }

        if (@file_put_contents($target, $content, LOCK_EX) === false) {
            throw new ReportWriteException(sprintf('Could not write "%s"; check permissions.', implode('/', $segments)));
        }

        return implode('/', $segments);
    }
}
