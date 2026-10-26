<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Commands;

use Rafiquljakir\FynoVerify\Config\ConfigurationValidator;
use Rafiquljakir\FynoVerify\FynoVerify;
use Rafiquljakir\FynoVerify\Planning\TestPlanBuilder;
use Rafiquljakir\FynoVerify\Reports\ReportRenderer;
use Rafiquljakir\FynoVerify\Support\ReportWriter;
use Rafiquljakir\FynoVerify\Support\RouteInspector;
use Rafiquljakir\FynoVerify\Support\Text;

final class PlanCommand extends BaseCommand
{
    protected $signature = 'fyno:plan
        {--format=table : Output format: table, json or markdown}
        {--output= : Write a json/markdown report to this project-relative path}
        {--force : Replace an existing --output file}';

    protected $description = 'Generate metadata-based test suggestions for in-scope routes (nothing is executed).';

    public function handle(
        RouteInspector $inspector,
        ConfigurationValidator $validator,
        TestPlanBuilder $builder,
        ReportWriter $writer,
    ): int {
        return $this->guard(function () use ($inspector, $validator, $builder, $writer): int {
            $format = $this->formatOption(['table', 'json', 'markdown']);

            if ($format === null) {
                return $this->failWith('Unsupported --format. Use one of: table, json, markdown.');
            }

            if ($this->stringOption('output') !== null && $format === 'table') {
                return $this->failWith('--output requires --format=json or --format=markdown.');
            }

            $validation = $validator->validate(config(FynoVerify::CONFIG_KEY));

            if (! $validation->isValid()) {
                foreach ($validation->errors as $error) {
                    $this->errorLine('Configuration error: '.Text::plain($error));
                }

                return $this->failWith('Invalid configuration; fix the errors above and re-run.');
            }

            $report = $builder->build($inspector->all(), $validation->settings);
            $report['warnings'] = array_merge($validation->warnings, $report['warnings']);

            if ($format === 'table') {
                $this->renderTable($report);

                return FynoVerify::EXIT_OK;
            }

            $content = $format === 'json' ? ReportRenderer::json($report) : ReportRenderer::planMarkdown($report);

            return $this->emitDocument($format, $content, $writer) ?? FynoVerify::EXIT_OK;
        });
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderTable(array $report): void
    {
        $this->line('fynoVerify: suggested test plan');
        $this->line(Text::terminal(FynoVerify::PLAN_DISCLAIMER));

        $rows = [];
        foreach ($report['routes'] as $route) {
            foreach ($route['scenarios'] as $scenario) {
                $rows[] = [
                    Text::terminal(Text::truncate((string) $route['id'], 36)),
                    $scenario['priority'],
                    $scenario['category'],
                    Text::terminal(Text::truncate($scenario['title'], 64)).' (suggested)',
                ];
            }
        }

        if ($rows !== []) {
            $this->table(['Route', 'Priority', 'Category', 'Suggested scenario'], $rows);
        }

        $summary = $report['summary'];
        $this->line(sprintf(
            '%d route(s) in plan, %d suggested scenario(s) (high %d, medium %d, low %d). Use --format=markdown for rationale and required inputs.',
            $summary['routes_in_plan'],
            $summary['scenario_count'],
            $summary['by_priority']['high'],
            $summary['by_priority']['medium'],
            $summary['by_priority']['low'],
        ));

        foreach ($report['warnings'] as $warning) {
            $this->line('Warning: '.Text::terminal((string) $warning));
        }
    }
}
