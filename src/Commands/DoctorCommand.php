<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Commands;

use Rafiquljakir\FynoVerify\Config\ConfigurationValidator;
use Rafiquljakir\FynoVerify\FynoVerify;
use Rafiquljakir\FynoVerify\Reports\ReportRenderer;
use Rafiquljakir\FynoVerify\Support\EvidencePathResolver;
use Rafiquljakir\FynoVerify\Support\RouteInspector;
use Rafiquljakir\FynoVerify\Support\Text;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Read-only diagnostics. It prints versions, counts and project-relative paths only; it never
 * prints environment values, credentials or connection strings, and never changes any file.
 */
final class DoctorCommand extends BaseCommand
{
    private const MIN_PHP = '8.2.0';

    /** Laravel majors declared in composer.json. Declared, not a promise of tested support. */
    private const DECLARED_LARAVEL_MAJORS = [11, 12];

    protected $signature = 'fyno:doctor
        {--format=table : Output format: table or json}';

    protected $description = 'Diagnose fynoVerify installation and configuration (read-only).';

    public function handle(
        ConfigurationValidator $validator,
        RouteInspector $inspector,
        EvidencePathResolver $paths,
    ): int {
        return $this->guard(function () use ($validator, $inspector, $paths): int {
            $format = $this->formatOption(['table', 'json']);

            if ($format === null) {
                return $this->failWith('Unsupported --format. Use one of: table, json.');
            }

            $validation = null;
            $checks = [];

            $checks[] = $this->check('PHP version', function (): array {
                return version_compare(PHP_VERSION, self::MIN_PHP, '>=')
                    ? $this->result('pass', 'PHP '.PHP_VERSION.'.')
                    : $this->result('fail', 'PHP '.PHP_VERSION.' is below the required '.self::MIN_PHP.'.', 'Upgrade PHP to 8.2 or newer.');
            });

            $checks[] = $this->check('Laravel version', function (): array {
                $version = (string) $this->laravel->version();
                $major = (int) explode('.', $version)[0];

                return in_array($major, self::DECLARED_LARAVEL_MAJORS, true)
                    ? $this->result('pass', 'Laravel '.$version.' is within the declared range (11.x to 12.x).')
                    : $this->result('warning', 'Laravel '.$version.' is outside the declared range (11.x to 12.x).', 'The package may still work, but this combination is untested.');
            });

            $checks[] = $this->check('Project base path', function (): array {
                $base = base_path();

                return is_dir($base)
                    ? $this->result('pass', 'The project base path is available.')
                    : $this->result('fail', 'The project base path is not a directory.', 'Run the command from a Laravel project root.');
            });

            $checks[] = $this->check('Configuration', function () use ($validator, &$validation): array {
                $validation = $validator->validate(config(FynoVerify::CONFIG_KEY));

                if (! $validation->isValid()) {
                    return $this->result('fail', implode(' ', $validation->errors), 'Fix the listed keys in config/fynoVerify.php (publish it with: php artisan vendor:publish --tag=fynoVerify-config).');
                }

                return $validation->warnings === []
                    ? $this->result('pass', 'Configuration loaded and valid (defaults apply to omitted keys).')
                    : $this->result('warning', implode(' ', $validation->warnings), 'Remove or correct the unknown keys.');
            });

            $checks[] = $this->check('Route registration', function () use ($inspector): array {
                $count = count($inspector->all());

                return $count > 0
                    ? $this->result('pass', $count.' route(s) can be inspected.')
                    : $this->result('warning', 'Route inspection works but no routes are registered.', 'Check that your route files are loaded.');
            });

            $checks[] = $this->check('Evidence paths', function () use ($paths, &$validation): array {
                if ($validation === null || ! $validation->isValid()) {
                    return $this->result('warning', 'Skipped because the configuration is invalid.', 'Fix the configuration first.');
                }

                $covered = $validation->settings->coveredRoutes;

                if ($covered === []) {
                    return $this->result('warning', 'No coverage.covered_routes mappings are declared yet.', 'Map route IDs from `php artisan fyno:scan` to test files.');
                }

                $problems = [];
                foreach ($covered as $id => $path) {
                    $problem = $paths->validateSyntax($path);
                    if ($problem !== null) {
                        $problems[] = sprintf('"%s": %s.', $id, $problem);
                    }
                }

                return $problems === []
                    ? $this->result('pass', count($covered).' evidence path(s) are syntactically valid and project-relative.')
                    : $this->result('fail', implode(' ', $problems), 'Use project-relative paths without ".." segments.');
            });

            $checks[] = $this->check('Test runner files', function (): array {
                $found = [];
                foreach (['phpunit.xml', 'phpunit.xml.dist', 'tests/Pest.php'] as $file) {
                    if (is_file(base_path($file))) {
                        $found[] = $file;
                    }
                }
                if (is_dir(base_path('tests'))) {
                    $found[] = 'tests/';
                }

                return $found === []
                    ? $this->result('warning', 'No phpunit.xml, Pest.php or tests/ directory was found (detection is heuristic).', 'fynoVerify does not run tests; make sure your project has a runnable test suite.')
                    : $this->result('pass', 'Found: '.implode(', ', $found).' (heuristic detection).');
            });

            $failed = array_filter($checks, static fn (array $c): bool => $c['status'] === 'fail') !== [];

            if ($format === 'json') {
                $this->output->write(ReportRenderer::json([
                    'tool' => FynoVerify::NAME,
                    'schema_version' => FynoVerify::SCHEMA_VERSION,
                    'report_type' => 'doctor',
                    'passed' => ! $failed,
                    'checks' => $checks,
                ]), false, OutputInterface::OUTPUT_RAW);
            } else {
                $rows = array_map(static fn (array $c): array => [
                    $c['name'],
                    $c['status'],
                    Text::terminal($c['message'].($c['recommendation'] !== null ? ' Next: '.$c['recommendation'] : '')),
                ], $checks);
                $this->table(['Check', 'Status', 'Details'], $rows);
                $this->line($failed ? 'Doctor found at least one failing check.' : 'No failing checks. Warnings, if any, are advisory.');
            }

            return $failed ? FynoVerify::EXIT_CHECK_FAILED : FynoVerify::EXIT_OK;
        });
    }

    /**
     * @param  callable(): array{status: string, message: string, recommendation: ?string}  $callback
     * @return array{name: string, status: string, message: string, recommendation: ?string}
     */
    private function check(string $name, callable $callback): array
    {
        try {
            return ['name' => $name] + $callback();
        } catch (Throwable $e) {
            return ['name' => $name] + $this->result('fail', 'Check could not complete: '.$e::class.'.', 'Run with -v for details or report a bug.');
        }
    }

    /**
     * @return array{status: string, message: string, recommendation: ?string}
     */
    private function result(string $status, string $message, ?string $recommendation = null): array
    {
        return ['status' => $status, 'message' => $message, 'recommendation' => $recommendation];
    }
}
