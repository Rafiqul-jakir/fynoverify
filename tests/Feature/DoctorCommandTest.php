<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Feature;

final class DoctorCommandTest extends FeatureTestCase
{
    /**
     * @param  array<string, mixed>  $report
     * @return array<string, array<string, mixed>>
     */
    private function checksByName(array $report): array
    {
        $byName = [];
        foreach ($report['checks'] as $check) {
            $byName[$check['name']] = $check;
        }

        return $byName;
    }

    public function test_a_healthy_environment_reports_useful_statuses(): void
    {
        $this->registerRoutes();
        file_put_contents($this->projectRoot.'/phpunit.xml', '<phpunit/>');

        [$code, $report] = $this->runJson('fyno:doctor');
        $checks = $this->checksByName($report);

        $this->assertSame(0, $code);
        $this->assertTrue($report['passed']);
        $this->assertSame('pass', $checks['PHP version']['status']);
        $this->assertSame('pass', $checks['Laravel version']['status']);
        $this->assertSame('pass', $checks['Project base path']['status']);
        $this->assertSame('pass', $checks['Configuration']['status']);
        $this->assertSame('pass', $checks['Route registration']['status']);
        $this->assertSame('warning', $checks['Evidence paths']['status'], 'No mappings declared yet is advisory.');
        $this->assertSame('pass', $checks['Test runner files']['status']);
    }

    public function test_table_output_works(): void
    {
        [$code, $output] = $this->run_('fyno:doctor');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('PHP version', $output);
        $this->assertStringContainsString('Configuration', $output);
    }

    public function test_invalid_configuration_is_a_failing_check_with_an_actionable_message(): void
    {
        config()->set('fynoVerify.coverage.fail_on_empty_scope', 'yes');

        [$code, $report] = $this->runJson('fyno:doctor');
        $checks = $this->checksByName($report);

        $this->assertSame(1, $code);
        $this->assertFalse($report['passed']);
        $this->assertSame('fail', $checks['Configuration']['status']);
        $this->assertStringContainsString('coverage.fail_on_empty_scope', $checks['Configuration']['message']);
        $this->assertNotEmpty($checks['Configuration']['recommendation']);
    }

    public function test_unsafe_evidence_paths_fail_the_syntax_check(): void
    {
        config()->set('fynoVerify.coverage.covered_routes', ['fvt.a' => '../escape.php', 'fvt.b' => '/etc/passwd']);

        [$code, $report] = $this->runJson('fyno:doctor');

        $this->assertSame(1, $code);
        $this->assertSame('fail', $this->checksByName($report)['Evidence paths']['status']);
    }

    public function test_missing_test_runner_files_are_only_a_warning(): void
    {
        rmdir($this->projectRoot.'/tests/Feature');
        rmdir($this->projectRoot.'/tests');

        [$code, $report] = $this->runJson('fyno:doctor');

        $this->assertSame(0, $code);
        $this->assertSame('warning', $this->checksByName($report)['Test runner files']['status']);
    }

    public function test_output_never_contains_environment_secret_values(): void
    {
        $secret = 'fyno-super-secret-value-'.bin2hex(random_bytes(6));
        putenv('FYNO_TEST_SECRET='.$secret);
        $_ENV['FYNO_TEST_SECRET'] = $secret;
        $_SERVER['FYNO_TEST_SECRET'] = $secret;
        config()->set('database.connections.testing.password', $secret);

        try {
            [, $json] = $this->run_('fyno:doctor', ['--format' => 'json']);
            [, $table] = $this->run_('fyno:doctor');
        } finally {
            putenv('FYNO_TEST_SECRET');
            unset($_ENV['FYNO_TEST_SECRET'], $_SERVER['FYNO_TEST_SECRET']);
        }

        $this->assertStringNotContainsString($secret, $json);
        $this->assertStringNotContainsString($secret, $table);

        $appKey = (string) config('app.key');
        if ($appKey !== '') {
            $this->assertStringNotContainsString($appKey, $json.$table);
        }
    }

    public function test_doctor_does_not_modify_the_project(): void
    {
        $this->registerRoutes();
        $before = $this->snapshot();

        $this->run_('fyno:doctor');
        $this->run_('fyno:doctor', ['--format' => 'json']);

        $this->assertSame($before, $this->snapshot());
    }

    public function test_invalid_format_exits_two(): void
    {
        [$code] = $this->run_('fyno:doctor', ['--format' => 'markdown']);

        $this->assertSame(2, $code);
    }
}
