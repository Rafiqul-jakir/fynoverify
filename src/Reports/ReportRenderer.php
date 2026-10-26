<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Reports;

use Rafiquljakir\FynoVerify\Support\Text;

/**
 * Pure rendering of report arrays to JSON and Markdown. All route metadata is treated as
 * untrusted: Markdown output is escaped so it cannot break tables or carry raw HTML.
 */
final class ReportRenderer
{
    /**
     * @param  array<string, mixed>  $report
     */
    public static function json(array $report): string
    {
        return json_encode(
            $report,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE,
        )."\n";
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function scanMarkdown(array $report): string
    {
        $out = [
            '# fynoVerify route inventory',
            '',
            '- Schema version: '.Text::markdown((string) $report['schema_version']),
            '- Filter: '.($report['filter'] === null ? 'none' : Text::markdown((string) $report['filter'])),
            '- Routes: '.(int) $report['route_count'],
            '',
        ];

        $rows = [];
        foreach ($report['routes'] as $route) {
            $rows[] = [
                $route['id'],
                implode(', ', $route['methods']),
                $route['uri'],
                $route['domain'] ?? '',
                $route['action'],
                implode(', ', $route['middleware']),
            ];
        }

        $out[] = $rows === []
            ? 'No routes matched.'
            : self::table(['ID', 'Methods', 'URI', 'Domain', 'Action', 'Middleware'], $rows);

        $out = array_merge($out, self::bulletSection('Warnings', $report['warnings']));
        $out[] = '';
        $out[] = '> '.Text::markdown((string) $report['disclaimer']);

        return implode("\n", $out)."\n";
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function coverageMarkdown(array $report): string
    {
        $out = [
            '# fynoVerify declared test-evidence coverage',
            '',
            '> '.Text::markdown((string) $report['disclaimer']),
            '',
            sprintf(
                '**Audit result:** %s (exit code %d). This covers declared test evidence only.',
                $report['passed'] ? 'pass' : 'fail',
                (int) $report['exit_code'],
            ),
            '',
        ];

        if ($report['scope'] !== null) {
            $scope = $report['scope'];
            $out[] = '## Scope';
            $out[] = '';
            $out[] = '- Include patterns (applied first): '.self::inlineList($scope['include_uri_patterns']);
            $out[] = '- Exclude patterns (applied second): '.self::inlineList($scope['exclude_uri_patterns']);
            $out[] = sprintf(
                '- Routes: %d total, %d in scope, %d not in scope',
                $scope['route_count_total'],
                $scope['route_count_in_scope'],
                $scope['route_count_not_in_scope'],
            );
            $out[] = '';
        }

        if ($report['summary'] !== null) {
            $out[] = '## Summary';
            $out[] = '';
            $summaryRows = [];
            foreach ($report['summary'] as $status => $count) {
                $summaryRows[] = [(string) $status, (string) $count];
            }
            $out[] = self::table(['Status', 'Count'], $summaryRows);
            $out[] = '';
        }

        if ($report['routes'] !== []) {
            $out[] = '## In-scope routes';
            $out[] = '';
            $rows = [];
            foreach ($report['routes'] as $route) {
                $rows[] = [
                    $route['id'],
                    $route['status'],
                    $route['uri'],
                    $route['evidence'] ?? $route['reason'] ?? '',
                    $route['message'] ?? '',
                ];
            }
            $out[] = self::table(['ID', 'Status', 'URI', 'Evidence / exemption reason', 'Note'], $rows);
            $out[] = '';
        }

        if ($report['stale_declarations'] !== []) {
            $out[] = '## Stale declarations';
            $out[] = '';
            $rows = [];
            foreach ($report['stale_declarations'] as $stale) {
                $rows[] = [$stale['id'], $stale['declared_as']];
            }
            $out[] = self::table(['Declared route ID', 'Declared as'], $rows);
            $out[] = '';
        }

        $out = array_merge(
            $out,
            self::bulletSection('Configuration errors', $report['configuration_errors']),
            self::bulletSection('Failure reasons', $report['failure_reasons']),
            self::bulletSection('Warnings', $report['warnings']),
        );

        $notInScope = $report['scope']['not_in_scope_route_ids'] ?? [];
        if ($notInScope !== []) {
            $out[] = '';
            $out[] = '## Routes not in scope';
            $out[] = '';
            $out[] = 'Listed so the effective scope is visible. They are not audited.';
            $out[] = '';
            foreach ($notInScope as $id) {
                $out[] = '- '.Text::markdown((string) $id);
            }
        }

        return implode("\n", $out)."\n";
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public static function planMarkdown(array $report): string
    {
        $summary = $report['summary'];
        $out = [
            '# fynoVerify test plan',
            '',
            '> '.Text::markdown((string) $report['disclaimer']),
            '',
            '- Include patterns: '.self::inlineList($report['scope']['include_uri_patterns']),
            '- Exclude patterns: '.self::inlineList($report['scope']['exclude_uri_patterns']),
            sprintf(
                '- Routes in plan: %d (omitted: %d); suggested scenarios: %d (high %d, medium %d, low %d)',
                $summary['routes_in_plan'],
                $summary['routes_omitted'],
                $summary['scenario_count'],
                $summary['by_priority']['high'],
                $summary['by_priority']['medium'],
                $summary['by_priority']['low'],
            ),
            '',
        ];

        foreach ($report['routes'] as $route) {
            $out[] = '## '.Text::markdown((string) $route['id']);
            $out[] = '';
            $out[] = sprintf(
                '%s `%s` — action: %s — middleware: %s',
                Text::markdown(implode(', ', $route['methods'])),
                str_replace('`', '', Text::plain((string) $route['uri'])),
                Text::markdown((string) $route['action']),
                $route['middleware'] === [] ? 'none declared' : Text::markdown(implode(', ', $route['middleware'])),
            );
            $out[] = '';

            if ($route['scenarios'] === []) {
                $out[] = 'No suggestions for this route with the current configuration.';
                $out[] = '';

                continue;
            }

            $rows = [];
            foreach ($route['scenarios'] as $scenario) {
                $rows[] = [
                    $scenario['priority'],
                    $scenario['category'],
                    $scenario['title'].' (suggested)',
                    $scenario['rationale'],
                    implode('; ', $scenario['requires_from_team']),
                ];
            }
            $out[] = self::table(['Priority', 'Category', 'Suggested scenario', 'Rationale', 'Needed from your team'], $rows);
            $out[] = '';
        }

        $out = array_merge($out, self::bulletSection('Warnings', $report['warnings']));
        $out[] = '';
        $out[] = '> '.Text::markdown((string) $report['disclaimer']);

        return implode("\n", $out)."\n";
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private static function table(array $headers, array $rows): string
    {
        $lines = [
            '| '.implode(' | ', $headers).' |',
            '| '.implode(' | ', array_fill(0, count($headers), '---')).' |',
        ];

        foreach ($rows as $row) {
            $lines[] = '| '.implode(' | ', array_map(static fn ($cell): string => Text::markdown((string) $cell), $row)).' |';
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private static function bulletSection(string $title, array $items): array
    {
        if ($items === []) {
            return [];
        }

        $lines = ['', '## '.$title, ''];
        foreach ($items as $item) {
            $lines[] = '- '.Text::markdown((string) $item);
        }

        return $lines;
    }

    /**
     * @param  list<string>  $items
     */
    private static function inlineList(array $items): string
    {
        return $items === [] ? 'none' : Text::markdown(implode(', ', $items));
    }
}
