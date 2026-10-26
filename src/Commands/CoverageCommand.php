<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Commands;

use Rafiquljakir\FynoVerify\Config\ConfigurationValidator;
use Rafiquljakir\FynoVerify\Coverage\CoverageAuditor;
use Rafiquljakir\FynoVerify\FynoVerify;
use Rafiquljakir\FynoVerify\Reports\ReportRenderer;
use Rafiquljakir\FynoVerify\Support\ReportWriter;
use Rafiquljakir\FynoVerify\Support\RouteInspector;
use Rafiquljakir\FynoVerify\Support\Text;

final class CoverageCommand extends BaseCommand
{
    protected $signature = 'fyno:coverage
        {--format=table : Output format: table, json or markdown}
        {--output= : Write a json/markdown report to this project-relative path}
        {--force : Replace an existing --output file}';

    protected $description = 'Audit declared test-evidence mappings for in-scope routes (does not run tests).';

    public function handle(
        RouteInspector $inspector,
        ConfigurationValidator $validator,
        CoverageAuditor $auditor,
        ReportWriter $writer,
    ): int {
        return $this->guard(function () use ($inspector, $validator, $auditor, $writer): int {
            $format = $this->formatOption(['table', 'json', 'markdown']);

            if ($format === null) {
                return $this->failWith('Unsupported --format. Use one of: table, json, markdown.');
            }

            if ($this->stringOption('output') !== null && $format === 'table') {
                return $this->failWith('--output requires --format=json or --format=markdown.');
            }

            $validation = $validator->validate(config(FynoVerify::CONFIG_KEY));

            if ($validation->isValid()) {
                $report = $auditor->audit($inspector->all(), $validation->settings, base_path());
                $report['warnings'] = array_merge($validation->warnings, $report['warnings']);
            } else {
                $report = CoverageAuditor::configurationFailure($validation->errors, $validation->warnings);
            }

            // A report is always produced, even for a failed audit, so CI can archive it.
            if ($format === 'table') {
                $this->renderTable($report);
            } else {
                $content = $format === 'json' ? ReportRenderer::json($report) : ReportRenderer::coverageMarkdown($report);
                $failure = $this->emitDocument($format, $content, $writer);

                if ($failure !== null) {
                    return $failure;
                }
            }

            return (int) $report['exit_code'];
        });
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderTable(array $report): void
    {
        $this->line('fynoVerify: declared test-evidence coverage');

        foreach ($report['configuration_errors'] as $error) {
            $this->errorLine('Configuration error: '.Text::plain((string) $error));
        }

        if ($report['scope'] !== null) {
            $scope = $report['scope'];
            $this->line(sprintf(
                'Scope: include [%s], then exclude [%s]. %d of %d route(s) in scope.',
                Text::terminal(implode(', ', $scope['include_uri_patterns'])),
                Text::terminal(implode(', ', $scope['exclude_uri_patterns'])),
                $scope['route_count_in_scope'],
                $scope['route_count_total'],
            ));
        }

        if ($report['routes'] !== []) {
            $rows = [];
            foreach ($report['routes'] as $route) {
                $rows[] = [
                    Text::terminal(Text::truncate((string) $route['id'], 40)),
                    $route['status'],
                    Text::terminal(Text::truncate((string) $route['uri'], 36)),
                    Text::terminal(Text::truncate((string) ($route['evidence'] ?? $route['reason'] ?? $route['message'] ?? ''), 60)),
                ];
            }
            $this->table(['ID', 'Status', 'URI', 'Evidence / reason / note'], $rows);
        }

        if ($report['summary'] !== null) {
            $parts = [];
            foreach ($report['summary'] as $name => $count) {
                $parts[] = $name.' '.$count;
            }
            $this->line('Summary: '.implode(', ', $parts));
        }

        foreach ($report['stale_declarations'] as $stale) {
            $this->line(sprintf('Stale declaration (%s): %s', $stale['declared_as'], Text::terminal((string) $stale['id'])));
        }

        foreach ($report['failure_reasons'] as $reason) {
            $this->line('Failure: '.Text::terminal((string) $reason));
        }

        foreach ($report['warnings'] as $warning) {
            $this->line('Warning: '.Text::terminal((string) $warning));
        }

        $this->line(Text::terminal(FynoVerify::COVERAGE_DISCLAIMER));
        $this->line(sprintf('Result: %s (exit code %d)', $report['passed'] ? 'PASS' : 'FAIL', $report['exit_code']));
    }
}
