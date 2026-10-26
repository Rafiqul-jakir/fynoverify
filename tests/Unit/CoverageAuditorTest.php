<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Unit;

use Rafiquljakir\FynoVerify\Coverage\CoverageAuditor;
use Rafiquljakir\FynoVerify\Support\EvidencePathResolver;
use Rafiquljakir\FynoVerify\Support\PatternMatcher;
use Rafiquljakir\FynoVerify\Support\RouteScope;
use PHPUnit\Framework\TestCase;

final class CoverageAuditorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = Support::tempDir();
        mkdir($this->root.'/tests', 0777, true);
        file_put_contents($this->root.'/tests/ShowTest.php', '<?php');
    }

    protected function tearDown(): void
    {
        Support::deleteTree($this->root);
    }

    private function auditor(): CoverageAuditor
    {
        return new CoverageAuditor(new RouteScope(new PatternMatcher), new EvidencePathResolver);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, string>
     */
    private function statuses(array $report): array
    {
        $statuses = [];
        foreach ($report['routes'] as $route) {
            $statuses[$route['id']] = $route['status'];
        }

        return $statuses;
    }

    public function test_every_status_is_produced(): void
    {
        $routes = [
            Support::route('covered', 'api/a', name: 'covered'),
            Support::route('ignored', 'api/b', name: 'ignored'),
            Support::route('uncovered', 'api/c', name: 'uncovered'),
            Support::route('missing', 'api/d', name: 'missing'),
            Support::route('blank-reason', 'api/e', name: 'blank-reason'),
        ];

        $settings = Support::settings(
            covered: ['covered' => 'tests/ShowTest.php', 'missing' => 'tests/Gone.php'],
            ignored: ['ignored' => 'Public liveness endpoint.', 'blank-reason' => "  \n"],
            failStale: false,
        );

        $report = $this->auditor()->audit($routes, $settings, $this->root);

        $this->assertSame([
            'covered' => 'covered',
            'ignored' => 'ignored',
            'uncovered' => 'uncovered',
            'missing' => 'missing-evidence',
            'blank-reason' => 'configuration-error',
        ], $this->statuses($report));
        $this->assertFalse($report['passed']);
        $this->assertSame(1, $report['exit_code']);
        $this->assertSame(1, $report['summary']['covered']);
        $this->assertSame(1, $report['summary']['configuration-error']);
    }

    public function test_passes_when_everything_has_evidence_or_a_reason(): void
    {
        $routes = [Support::route('a', 'api/a'), Support::route('b', 'api/b')];
        $settings = Support::settings(['a' => 'tests/ShowTest.php'], ['b' => 'Intentionally exempt.']);

        $report = $this->auditor()->audit($routes, $settings, $this->root);

        $this->assertTrue($report['passed']);
        $this->assertSame(0, $report['exit_code']);
        $this->assertSame([], $report['failure_reasons']);
    }

    public function test_route_declared_as_both_covered_and_ignored_is_a_configuration_error(): void
    {
        $report = $this->auditor()->audit(
            [Support::route('a', 'api/a')],
            Support::settings(['a' => 'tests/ShowTest.php'], ['a' => 'Because.']),
            $this->root,
        );

        $this->assertSame(['a' => 'configuration-error'], $this->statuses($report));
        $this->assertStringContainsString('both covered and ignored', $report['routes'][0]['message']);
    }

    public function test_blank_covered_path_is_a_configuration_error(): void
    {
        $report = $this->auditor()->audit([Support::route('a', 'api/a')], Support::settings(['a' => '  ']), $this->root);

        $this->assertSame(['a' => 'configuration-error'], $this->statuses($report));
    }

    public function test_path_traversal_in_evidence_is_missing_evidence(): void
    {
        $report = $this->auditor()->audit([Support::route('a', 'api/a')], Support::settings(['a' => '../outside.php']), $this->root);

        $this->assertSame(['a' => 'missing-evidence'], $this->statuses($report));
        $this->assertStringContainsString('..', $report['routes'][0]['message']);
    }

    public function test_stale_declarations_are_reported_separately_and_fail_by_default(): void
    {
        $routes = [Support::route('a', 'api/a')];
        $settings = Support::settings(['a' => 'tests/ShowTest.php', 'old.route' => 'tests/ShowTest.php'], ['gone.route' => 'Removed.']);

        $report = $this->auditor()->audit($routes, $settings, $this->root);

        $this->assertSame(['a' => 'covered'], $this->statuses($report));
        $this->assertEqualsCanonicalizing(
            [['id' => 'old.route', 'declared_as' => 'covered'], ['id' => 'gone.route', 'declared_as' => 'ignored']],
            $report['stale_declarations'],
        );
        $this->assertSame(1, $report['exit_code']);

        $lenient = $this->auditor()->audit($routes, Support::settings(['a' => 'tests/ShowTest.php', 'old.route' => 'x'], [], failStale: false), $this->root);

        $this->assertSame(0, $lenient['exit_code']);
        $this->assertCount(1, $lenient['stale_declarations']);
        $this->assertNotEmpty($lenient['warnings']);
    }

    public function test_empty_scope_fails_only_when_configured(): void
    {
        $routes = [Support::route('web.home', 'home')];

        $strict = $this->auditor()->audit($routes, Support::settings(), $this->root);
        $lenient = $this->auditor()->audit($routes, Support::settings(failEmpty: false), $this->root);

        $this->assertSame(1, $strict['exit_code']);
        $this->assertSame(0, $lenient['exit_code']);
        $this->assertNotEmpty($lenient['warnings']);
    }

    public function test_exclude_patterns_apply_after_include_patterns_and_stay_visible(): void
    {
        $routes = [
            Support::route('a', 'api/a'),
            Support::route('internal', 'api/internal/x'),
            Support::route('home', 'home'),
        ];

        $report = $this->auditor()->audit($routes, Support::settings(['a' => 'tests/ShowTest.php'], [], ['api/*'], ['api/internal/*']), $this->root);

        $this->assertSame(['a' => 'covered'], $this->statuses($report));
        $this->assertSame(1, $report['scope']['route_count_in_scope']);
        $this->assertSame(2, $report['scope']['route_count_not_in_scope']);
        $this->assertEqualsCanonicalizing(['internal', 'home'], $report['scope']['not_in_scope_route_ids']);
        $this->assertSame(0, $report['exit_code']);
    }

    public function test_duplicate_route_ids_never_pass_silently(): void
    {
        $routes = [Support::route('dup', 'api/a'), Support::route('dup', 'api/b')];

        $report = $this->auditor()->audit($routes, Support::settings(['dup' => 'tests/ShowTest.php']), $this->root);

        $this->assertSame(['dup' => 'configuration-error'], $this->statuses($report));
        $this->assertSame(2, $report['summary']['configuration-error']);
        $this->assertSame(1, $report['exit_code']);
    }

    public function test_declaration_for_an_out_of_scope_route_is_a_warning_not_stale(): void
    {
        $report = $this->auditor()->audit(
            [Support::route('a', 'api/a'), Support::route('home', 'home')],
            Support::settings(['a' => 'tests/ShowTest.php', 'home' => 'tests/ShowTest.php']),
            $this->root,
        );

        $this->assertSame([], $report['stale_declarations']);
        $this->assertSame(0, $report['exit_code']);
        $this->assertStringContainsString('outside the configured scope', $report['warnings'][0]);
    }

    public function test_report_never_claims_tests_ran_or_passed(): void
    {
        $report = $this->auditor()->audit([Support::route('a', 'api/a')], Support::settings(['a' => 'tests/ShowTest.php']), $this->root);

        $this->assertSame('declared test-evidence coverage', $report['metric']);
        $this->assertStringContainsString('does not execute tests', $report['disclaimer']);
        $this->assertStringContainsString('declared test-evidence coverage', $report['disclaimer']);
    }

    public function test_configuration_failure_report_has_exit_code_two(): void
    {
        $report = CoverageAuditor::configurationFailure(['coverage.x: bad'], ['warn']);

        $this->assertSame(2, $report['exit_code']);
        $this->assertFalse($report['passed']);
        $this->assertSame(['coverage.x: bad'], $report['configuration_errors']);
    }
}
