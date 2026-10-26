<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Coverage;

use Rafiquljakir\FynoVerify\Config\Settings;
use Rafiquljakir\FynoVerify\DTOs\RouteRecord;
use Rafiquljakir\FynoVerify\FynoVerify;
use Rafiquljakir\FynoVerify\Support\EvidencePathResolver;
use Rafiquljakir\FynoVerify\Support\RouteScope;

/**
 * Audits declared test evidence. This never reads test file contents, runs tests, or
 * decides that a route is secure.
 */
final class CoverageAuditor
{
    public const COVERED = 'covered';

    public const IGNORED = 'ignored';

    public const UNCOVERED = 'uncovered';

    public const MISSING_EVIDENCE = 'missing-evidence';

    public const CONFIGURATION_ERROR = 'configuration-error';

    public function __construct(
        private readonly RouteScope $scope,
        private readonly EvidencePathResolver $paths,
    ) {}

    /**
     * @param  list<RouteRecord>  $routes  The full route inventory.
     * @return array<string, mixed>
     */
    public function audit(array $routes, Settings $settings, string $basePath): array
    {
        $partition = $this->scope->partition($routes, $settings);
        $inScope = $partition['in_scope'];
        $excluded = $partition['excluded'];

        $idCounts = [];
        foreach ($routes as $route) {
            $idCounts[$route->id] = ($idCounts[$route->id] ?? 0) + 1;
        }

        $declarationIssues = $this->declarationIssues($settings);

        $counts = [
            self::COVERED => 0,
            self::IGNORED => 0,
            self::UNCOVERED => 0,
            self::MISSING_EVIDENCE => 0,
            self::CONFIGURATION_ERROR => 0,
        ];
        $items = [];

        foreach ($inScope as $route) {
            [$status, $evidence, $reason, $message] = $this->classify($route, $settings, $idCounts, $declarationIssues, $basePath);
            $counts[$status]++;

            $items[] = [
                'id' => $route->id,
                'name' => $route->name,
                'methods' => $route->methods,
                'uri' => $route->uri,
                'status' => $status,
                'evidence' => $evidence,
                'reason' => $reason,
                'message' => $message,
            ];
        }

        $stale = [];
        $warnings = [];
        $declared = [];
        foreach (array_keys($settings->coveredRoutes) as $id) {
            $declared[(string) $id][] = 'covered';
        }
        foreach (array_keys($settings->ignoredRoutes) as $id) {
            $declared[(string) $id][] = 'ignored';
        }

        $inScopeIds = array_flip(array_map(static fn (RouteRecord $r): string => $r->id, $inScope));
        foreach ($declared as $id => $kinds) {
            $id = (string) $id;
            if (! isset($idCounts[$id])) {
                $stale[] = ['id' => $id, 'declared_as' => count($kinds) > 1 ? 'covered+ignored' : $kinds[0]];
            } elseif (! isset($inScopeIds[$id])) {
                $warnings[] = sprintf('Declaration for "%s" refers to a route outside the configured scope; it has no effect on the audit.', $id);
            }
        }

        $failures = [];
        foreach ([self::UNCOVERED, self::MISSING_EVIDENCE, self::CONFIGURATION_ERROR] as $status) {
            if ($counts[$status] > 0) {
                $failures[] = sprintf('%d in-scope route(s) have status "%s".', $counts[$status], $status);
            }
        }

        if ($stale !== [] && $settings->failOnStaleDeclarations) {
            $failures[] = sprintf('%d declared route ID(s) no longer exist (stale declarations).', count($stale));
        } elseif ($stale !== []) {
            $warnings[] = sprintf('%d stale declaration(s) found; coverage.fail_on_stale_declarations is false so this does not fail the audit.', count($stale));
        }

        if ($inScope === []) {
            if ($settings->failOnEmptyScope) {
                $failures[] = 'The configured scope matched no routes (coverage.fail_on_empty_scope is true).';
            } else {
                $warnings[] = 'The configured scope matched no routes.';
            }
        }

        return [
            'tool' => FynoVerify::NAME,
            'schema_version' => FynoVerify::SCHEMA_VERSION,
            'report_type' => 'coverage',
            'metric' => 'declared test-evidence coverage',
            'disclaimer' => FynoVerify::COVERAGE_DISCLAIMER,
            'passed' => $failures === [],
            'exit_code' => $failures === [] ? FynoVerify::EXIT_OK : FynoVerify::EXIT_CHECK_FAILED,
            'scope' => [
                'include_uri_patterns' => $settings->includeUriPatterns,
                'exclude_uri_patterns' => $settings->excludeUriPatterns,
                'route_count_total' => count($routes),
                'route_count_in_scope' => count($inScope),
                'route_count_not_in_scope' => count($excluded),
                'not_in_scope_route_ids' => array_map(static fn (RouteRecord $r): string => $r->id, $excluded),
            ],
            'summary' => $counts + ['stale_declarations' => count($stale)],
            'routes' => $items,
            'stale_declarations' => $stale,
            'configuration_errors' => [],
            'failure_reasons' => $failures,
            'warnings' => $warnings,
        ];
    }

    /**
     * Report for configuration that is too broken to audit (exit code 2).
     *
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    public static function configurationFailure(array $errors, array $warnings): array
    {
        return [
            'tool' => FynoVerify::NAME,
            'schema_version' => FynoVerify::SCHEMA_VERSION,
            'report_type' => 'coverage',
            'metric' => 'declared test-evidence coverage',
            'disclaimer' => FynoVerify::COVERAGE_DISCLAIMER,
            'passed' => false,
            'exit_code' => FynoVerify::EXIT_INVALID,
            'scope' => null,
            'summary' => null,
            'routes' => [],
            'stale_declarations' => [],
            'configuration_errors' => $errors,
            'failure_reasons' => ['The configuration is invalid, so no meaningful audit could run.'],
            'warnings' => $warnings,
        ];
    }

    /**
     * Problems with individual declarations, keyed by route ID.
     *
     * @return array<string, string>
     */
    private function declarationIssues(Settings $settings): array
    {
        $issues = [];

        foreach ($settings->coveredRoutes as $id => $path) {
            $id = (string) $id;
            if (array_key_exists($id, $settings->ignoredRoutes)) {
                $issues[$id] = 'Route is declared as both covered and ignored; keep only one.';
            } elseif (trim($path) === '') {
                $issues[$id] = 'coverage.covered_routes entry has an empty path.';
            }
        }

        foreach ($settings->ignoredRoutes as $id => $reason) {
            $id = (string) $id;
            if (! isset($issues[$id]) && trim($reason) === '') {
                $issues[$id] = 'coverage.ignored_routes entry needs a non-empty reason.';
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, int>  $idCounts
     * @param  array<string, string>  $declarationIssues
     * @return array{0: string, 1: ?string, 2: ?string, 3: ?string} status, evidence, reason, message
     */
    private function classify(RouteRecord $route, Settings $settings, array $idCounts, array $declarationIssues, string $basePath): array
    {
        $id = $route->id;

        if (($idCounts[$id] ?? 0) > 1) {
            return [self::CONFIGURATION_ERROR, null, null, sprintf('Route ID "%s" is used by %d routes; declarations cannot be mapped unambiguously. Give each route a unique name.', $id, $idCounts[$id])];
        }

        if (isset($declarationIssues[$id])) {
            return [self::CONFIGURATION_ERROR, null, null, $declarationIssues[$id]];
        }

        if (array_key_exists($id, $settings->coveredRoutes)) {
            $path = trim($settings->coveredRoutes[$id]);
            $problem = $this->paths->check($path, $basePath);

            if ($problem !== null) {
                return [self::MISSING_EVIDENCE, $path, null, sprintf('Evidence "%s": %s.', $path, $problem)];
            }

            return [self::COVERED, $path, null, null];
        }

        if (array_key_exists($id, $settings->ignoredRoutes)) {
            return [self::IGNORED, null, trim($settings->ignoredRoutes[$id]), null];
        }

        return [self::UNCOVERED, null, null, 'No test-evidence mapping or exemption is declared for this route.'];
    }
}
