<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Unit;

use Rafiquljakir\FynoVerify\Coverage\CoverageAuditor;
use Rafiquljakir\FynoVerify\DTOs\RouteRecord;
use Rafiquljakir\FynoVerify\Planning\TestPlanBuilder;
use Rafiquljakir\FynoVerify\Reports\ReportRenderer;
use Rafiquljakir\FynoVerify\Reports\ScanReport;
use Rafiquljakir\FynoVerify\Support\EvidencePathResolver;
use Rafiquljakir\FynoVerify\Support\PatternMatcher;
use Rafiquljakir\FynoVerify\Support\RouteScope;
use PHPUnit\Framework\TestCase;

final class ReportRendererTest extends TestCase
{
    private function hostileRoute(): RouteRecord
    {
        return new RouteRecord(
            "api.evil|name\nnext-line",
            "api.evil|name\nnext-line",
            ['GET'],
            null,
            'api/<img src=x onerror=alert(1)>/{id}',
            'App\\Http\\Controllers\\X@<b>run</b>',
            ['auth', "throttle|1\n2"],
            ['id'],
            ['id' => '[0-9]+'],
        );
    }

    public function test_scan_json_is_valid_and_has_the_required_keys(): void
    {
        $json = ReportRenderer::json(ScanReport::build([$this->hostileRoute()], 'api/*'));
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('fynoVerify', $decoded['tool']);
        $this->assertSame('1.0', $decoded['schema_version']);
        $this->assertSame(1, $decoded['route_count']);
        $this->assertSame('api/*', $decoded['filter']);
        $this->assertArrayNotHasKey('generated_at', $decoded, 'Canonical output is deterministic and has no timestamp.');
        $this->assertSame("api.evil|name\nnext-line", $decoded['routes'][0]['id']);
        $this->assertStringEndsWith("\n", $json);
    }

    public function test_empty_where_constraints_serialize_as_an_object(): void
    {
        $record = new RouteRecord('a', 'a', ['GET'], null, 'api/a', 'Closure', [], [], []);

        $this->assertStringContainsString('"where_constraints": {}', ReportRenderer::json(ScanReport::build([$record], null)));
    }

    public function test_slashes_and_unicode_are_not_escaped_in_json(): void
    {
        $record = new RouteRecord('a', 'a', ['GET'], null, 'api/café/{id}', 'Closure', [], [], []);

        $this->assertStringContainsString('api/café/{id}', ReportRenderer::json(ScanReport::build([$record], null)));
    }

    public function test_scan_markdown_cannot_be_corrupted_by_route_metadata(): void
    {
        $markdown = ReportRenderer::scanMarkdown(ScanReport::build([$this->hostileRoute()], null));

        $this->assertStringNotContainsString('<img', $markdown);
        $this->assertStringNotContainsString('<b>', $markdown);
        $this->assertStringContainsString('&lt;img', $markdown);

        $tableRows = array_values(array_filter(explode("\n", $markdown), static fn (string $l): bool => str_starts_with($l, '| ')));
        $this->assertCount(3, $tableRows, 'Header, separator and exactly one data row: newlines in metadata must not add rows.');

        foreach ($tableRows as $row) {
            $this->assertSame(7, preg_match_all('/(?<!\\\\)\|/', $row), 'Six columns means seven unescaped pipes per row.');
        }
    }

    public function test_coverage_markdown_states_the_metric_and_limits(): void
    {
        $route = $this->hostileRoute();
        $auditor = new CoverageAuditor(new RouteScope(new PatternMatcher), new EvidencePathResolver);
        $report = $auditor->audit([$route], Support::settings(), sys_get_temp_dir());
        $markdown = ReportRenderer::coverageMarkdown($report);

        $this->assertStringContainsString('declared test-evidence coverage', $markdown);
        $this->assertStringContainsString('does not execute tests', $markdown);
        $this->assertStringNotContainsString('<img', $markdown);
        $this->assertStringContainsString('Audit result:** fail', $markdown);
    }

    public function test_coverage_markdown_renders_for_configuration_failures(): void
    {
        $markdown = ReportRenderer::coverageMarkdown(CoverageAuditor::configurationFailure(['coverage.x: bad'], []));

        $this->assertStringContainsString('Configuration errors', $markdown);
        $this->assertStringContainsString('exit code 2', $markdown);
    }

    public function test_plan_markdown_escapes_and_labels_suggestions(): void
    {
        $matcher = new PatternMatcher;
        $builder = new TestPlanBuilder($matcher, new RouteScope($matcher));
        $markdown = ReportRenderer::planMarkdown($builder->build([$this->hostileRoute()], Support::settings()));

        $this->assertStringNotContainsString('<img', $markdown);
        $this->assertStringContainsString('(suggested)', $markdown);
        $this->assertStringContainsString('metadata-based test suggestions, not executed tests or a security verdict', $markdown);
    }
}
