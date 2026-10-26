<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Commands;

use Rafiquljakir\FynoVerify\DTOs\RouteRecord;
use Rafiquljakir\FynoVerify\FynoVerify;
use Rafiquljakir\FynoVerify\Reports\ReportRenderer;
use Rafiquljakir\FynoVerify\Reports\ScanReport;
use Rafiquljakir\FynoVerify\Support\PatternMatcher;
use Rafiquljakir\FynoVerify\Support\ReportWriter;
use Rafiquljakir\FynoVerify\Support\RouteInspector;
use Rafiquljakir\FynoVerify\Support\Text;

final class ScanCommand extends BaseCommand
{
    protected $signature = 'fyno:scan
        {--format=table : Output format: table, json or markdown}
        {--filter= : Only include routes whose URI matches this glob (for example "api/*")}
        {--output= : Write a json/markdown report to this project-relative path}
        {--force : Replace an existing --output file}';

    protected $description = 'Inventory registered routes (read-only) as a table, JSON or Markdown.';

    public function handle(RouteInspector $inspector, PatternMatcher $matcher, ReportWriter $writer): int
    {
        return $this->guard(function () use ($inspector, $matcher, $writer): int {
            $format = $this->formatOption(['table', 'json', 'markdown']);

            if ($format === null) {
                return $this->failWith('Unsupported --format. Use one of: table, json, markdown.');
            }

            if ($this->stringOption('output') !== null && $format === 'table') {
                return $this->failWith('--output requires --format=json or --format=markdown.');
            }

            $filter = $this->stringOption('filter');

            if ($filter !== null) {
                $problem = $matcher->validatePattern($filter);

                if ($problem !== null) {
                    return $this->failWith('Invalid --filter: '.$problem.'.');
                }
            }

            $routes = $inspector->all();

            if ($filter !== null) {
                $routes = array_values(array_filter(
                    $routes,
                    static fn (RouteRecord $route): bool => $matcher->matches($filter, $route->uri),
                ));
            }

            $report = ScanReport::build($routes, $filter);

            if ($format === 'table') {
                $this->renderTable($report);

                return FynoVerify::EXIT_OK;
            }

            $content = $format === 'json' ? ReportRenderer::json($report) : ReportRenderer::scanMarkdown($report);

            return $this->emitDocument($format, $content, $writer) ?? FynoVerify::EXIT_OK;
        });
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderTable(array $report): void
    {
        if ($report['route_count'] === 0) {
            $this->line('No routes matched'.($report['filter'] !== null ? ' the filter "'.Text::terminal((string) $report['filter']).'"' : '').'.');

            return;
        }

        $rows = [];
        foreach ($report['routes'] as $route) {
            $rows[] = [
                Text::terminal(Text::truncate((string) $route['id'], 40)),
                Text::terminal(implode('|', $route['methods'])),
                Text::terminal(Text::truncate((string) $route['uri'], 40)),
                Text::terminal(Text::truncate((string) $route['action'], 48)),
                Text::terminal(Text::truncate(implode(', ', $route['middleware']), 40)),
            ];
        }

        $this->table(['ID', 'Methods', 'URI', 'Action', 'Middleware'], $rows);
        $this->line(sprintf('%d route(s). Use --format=json or --format=markdown for full, untruncated data.', $report['route_count']));

        foreach ($report['warnings'] as $warning) {
            $this->line('Warning: '.Text::terminal((string) $warning));
        }

        $this->line('Next: map routes to test files in config/fynoVerify.php, then run `php artisan fyno:coverage`.');
    }
}
