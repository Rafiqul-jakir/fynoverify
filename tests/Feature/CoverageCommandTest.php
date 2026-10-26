<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Feature;

use Illuminate\Support\Facades\Route;

final class CoverageCommandTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('fvt/a', fn () => 'ok')->name('fvt.a');
        Route::get('fvt/b', fn () => 'ok')->name('fvt.b');
        Route::get('fvt/c', fn () => 'ok')->name('fvt.c');
        Route::get('fvt/d', fn () => 'ok')->name('fvt.d');
        Route::get('fvt/e', fn () => 'ok')->name('fvt.e');
    }

    /**
     * @param  array<string, string>  $covered
     * @param  array<string, string>  $ignored
     */
    private function declareEvidence(array $covered, array $ignored = []): void
    {
        config()->set('fynoVerify.coverage.covered_routes', $covered);
        config()->set('fynoVerify.coverage.ignored_routes', $ignored);
    }

    private function coverEverything(): void
    {
        $this->touchFile('tests/Feature/AnyTest.php');
        $evidence = 'tests/Feature/AnyTest.php';
        $this->declareEvidence(
            ['fvt.a' => $evidence, 'fvt.b' => $evidence, 'fvt.c' => $evidence],
            ['fvt.d' => 'Intentionally exempt in this fixture.', 'fvt.e' => 'Intentionally exempt in this fixture.'],
        );
    }

    public function test_passing_audit_exits_zero_and_emits_valid_json(): void
    {
        $this->coverEverything();

        [$code, $report] = $this->runJson('fyno:coverage');

        $this->assertSame(0, $code);
        $this->assertTrue($report['passed']);
        $this->assertSame(0, $report['exit_code']);
        $this->assertSame(5, $report['scope']['route_count_in_scope']);
        $this->assertSame(3, $report['summary']['covered']);
        $this->assertSame(2, $report['summary']['ignored']);
        $this->assertSame('declared test-evidence coverage', $report['metric']);
    }

    public function test_every_status_is_reported_and_the_audit_fails_with_valid_json(): void
    {
        $this->touchFile('tests/Feature/AnyTest.php');
        $this->declareEvidence(
            ['fvt.a' => 'tests/Feature/AnyTest.php', 'fvt.b' => 'tests/Feature/Missing.php', 'fvt.gone' => 'tests/Feature/AnyTest.php'],
            ['fvt.c' => 'Exempt for this fixture.', 'fvt.e' => '   '],
        );

        [$code, $report] = $this->runJson('fyno:coverage');
        $routes = $this->routesById($report);

        $this->assertSame(1, $code);
        $this->assertFalse($report['passed']);
        $this->assertSame('covered', $routes['fvt.a']['status']);
        $this->assertSame('missing-evidence', $routes['fvt.b']['status']);
        $this->assertSame('ignored', $routes['fvt.c']['status']);
        $this->assertSame('uncovered', $routes['fvt.d']['status']);
        $this->assertSame('configuration-error', $routes['fvt.e']['status']);
        $this->assertSame([['id' => 'fvt.gone', 'declared_as' => 'covered']], $report['stale_declarations']);
        $this->assertNotEmpty($report['failure_reasons']);
    }

    public function test_out_of_root_and_traversal_evidence_is_rejected(): void
    {
        $outside = dirname($this->projectRoot).'/fyno-outside-'.bin2hex(random_bytes(4)).'.php';
        file_put_contents($outside, '<?php');

        try {
            $this->touchFile('tests/Feature/AnyTest.php');
            $this->declareEvidence([
                'fvt.a' => '../'.basename($outside),
                'fvt.b' => $outside,
                'fvt.c' => 'tests/../../'.basename($outside),
                'fvt.d' => 'tests/Feature/AnyTest.php',
                'fvt.e' => 'tests/Feature/AnyTest.php',
            ]);

            [$code, $report] = $this->runJson('fyno:coverage');
            $routes = $this->routesById($report);

            $this->assertSame(1, $code);
            foreach (['fvt.a', 'fvt.b', 'fvt.c'] as $id) {
                $this->assertSame('missing-evidence', $routes[$id]['status'], $id);
            }
            $this->assertSame('covered', $routes['fvt.d']['status']);
        } finally {
            @unlink($outside);
        }
    }

    public function test_stale_declarations_follow_the_config_flag(): void
    {
        $this->coverEverything();
        // Dotted route IDs must not go through dot-notation, so rebuild the whole map.
        $covered = config('fynoVerify.coverage.covered_routes');
        $covered['fvt.removed'] = 'tests/Feature/AnyTest.php';
        config()->set('fynoVerify.coverage.covered_routes', $covered);

        [$strict] = $this->runJson('fyno:coverage');
        $this->assertSame(1, $strict);

        config()->set('fynoVerify.coverage.fail_on_stale_declarations', false);
        [$lenient, $report] = $this->runJson('fyno:coverage');

        $this->assertSame(0, $lenient);
        $this->assertCount(1, $report['stale_declarations']);
    }

    public function test_empty_scope_follows_the_config_flag(): void
    {
        config()->set('fynoVerify.coverage.include_uri_patterns', ['no-such-prefix/*']);

        [$strict, $report] = $this->runJson('fyno:coverage');
        $this->assertSame(1, $strict);
        $this->assertSame(0, $report['scope']['route_count_in_scope']);

        config()->set('fynoVerify.coverage.fail_on_empty_scope', false);
        [$lenient] = $this->runJson('fyno:coverage');
        $this->assertSame(0, $lenient);
    }

    public function test_exclude_patterns_apply_after_include_patterns(): void
    {
        $this->coverEverything();
        config()->set('fynoVerify.coverage.exclude_uri_patterns', ['fvt/e']);
        config()->set('fynoVerify.coverage.ignored_routes', ['fvt.d' => 'Exempt for this fixture.']);

        [$code, $report] = $this->runJson('fyno:coverage');

        $this->assertSame(0, $code);
        $this->assertSame(4, $report['scope']['route_count_in_scope']);
        $this->assertContains('fvt.e', $report['scope']['not_in_scope_route_ids']);
    }

    public function test_invalid_configuration_exits_two_but_still_emits_a_report(): void
    {
        config()->set('fynoVerify.coverage.include_uri_patterns', 'fvt/*');
        config()->set('fynoVerify.coverage.covered_routes', ['tests/Feature/AnyTest.php']);

        [$code, $report] = $this->runJson('fyno:coverage');

        $this->assertSame(2, $code);
        $this->assertFalse($report['passed']);
        $this->assertSame(2, $report['exit_code']);
        $this->assertNotEmpty($report['configuration_errors']);
        $this->assertStringContainsString('coverage.include_uri_patterns', implode("\n", $report['configuration_errors']));
    }

    public function test_invalid_format_exits_two(): void
    {
        [$code, $output] = $this->run_('fyno:coverage', ['--format' => 'yaml']);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('Unsupported --format', $output);
    }

    public function test_failed_audit_still_writes_the_report_file(): void
    {
        [$code, $output] = $this->run_('fyno:coverage', ['--format' => 'json', '--output' => 'storage/app/fynoverify/coverage.json']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Report written to', $output);

        $decoded = json_decode((string) file_get_contents($this->projectRoot.'/storage/app/fynoverify/coverage.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($decoded['passed']);
    }

    public function test_unsafe_output_path_exits_two(): void
    {
        [$code] = $this->run_('fyno:coverage', ['--format' => 'json', '--output' => '../coverage.json']);

        $this->assertSame(2, $code);
    }

    public function test_table_output_uses_the_right_wording_and_never_claims_tests_ran(): void
    {
        $this->coverEverything();

        [$code, $output] = $this->run_('fyno:coverage');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('declared test-evidence coverage', $output);
        $this->assertStringContainsString('does not execute tests', $output);
        $this->assertStringContainsString('php artisan test', $output);
        $this->assertStringContainsString('Result: PASS (exit code 0)', $output);
        $this->assertStringNotContainsStringIgnoringCase('tests passed', $output);
        $this->assertStringNotContainsStringIgnoringCase('line coverage', $output);
    }

    public function test_markdown_output_is_rendered(): void
    {
        [$code, $markdown] = $this->run_('fyno:coverage', ['--format' => 'markdown']);

        $this->assertSame(1, $code);
        $this->assertStringStartsWith('# fynoVerify declared test-evidence coverage', $markdown);
        $this->assertStringContainsString('## Scope', $markdown);
        $this->assertStringContainsString('uncovered', $markdown);
    }

    public function test_coverage_is_read_only_and_does_not_open_evidence_files_for_execution(): void
    {
        $this->coverEverything();
        // If this fixture were ever included or executed, the sentinel file would appear.
        file_put_contents($this->projectRoot.'/tests/Feature/AnyTest.php', "<?php file_put_contents(__DIR__.'/EXECUTED', 'x');");
        $before = $this->snapshot();

        $this->run_('fyno:coverage');
        $this->run_('fyno:coverage', ['--format' => 'json']);

        $this->assertFileDoesNotExist($this->projectRoot.'/tests/Feature/EXECUTED');
        $this->assertSame($before, $this->snapshot());
    }
}
