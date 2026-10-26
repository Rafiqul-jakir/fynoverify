<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Feature;

use Illuminate\Support\Facades\Route;

final class ScanCommandTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->registerRoutes();
    }

    public function test_json_output_is_pure_valid_json_with_required_keys(): void
    {
        [$code, $report] = $this->runJson('fyno:scan', ['--filter' => 'fvt/*']);

        $this->assertSame(0, $code);
        $this->assertSame('fynoVerify', $report['tool']);
        $this->assertSame('1.0', $report['schema_version']);
        $this->assertSame('fvt/*', $report['filter']);
        $this->assertSame($report['route_count'], count($report['routes']));
        $this->assertGreaterThanOrEqual(8, $report['route_count']);

        foreach (['id', 'name', 'methods', 'domain', 'uri', 'action', 'middleware', 'parameters', 'where_constraints'] as $key) {
            $this->assertArrayHasKey($key, $report['routes'][0]);
        }
    }

    public function test_output_is_deterministic_and_sorted(): void
    {
        [, $first] = $this->run_('fyno:scan', ['--format' => 'json', '--filter' => 'fvt/*']);
        [, $second] = $this->run_('fyno:scan', ['--format' => 'json', '--filter' => 'fvt/*']);

        $this->assertSame($first, $second);

        $report = json_decode($first, true, 512, JSON_THROW_ON_ERROR);
        $uris = array_column($report['routes'], 'uri');
        $sorted = $uris;
        usort($sorted, 'strcmp');

        $this->assertSame($sorted, $uris);
    }

    public function test_filter_includes_matching_and_excludes_non_matching_routes(): void
    {
        [, $report] = $this->runJson('fyno:scan', ['--filter' => 'fvt/documents*']);
        $ids = array_keys($this->routesById($report));

        $this->assertContains('fvt.documents.index', $ids);
        $this->assertContains('fvt.documents.show', $ids);
        $this->assertNotContains('fvt.dom', $ids);
        $this->assertNotContains('fvt.root', $ids);
    }

    public function test_empty_filter_result_is_handled_in_every_format(): void
    {
        [$code, $report] = $this->runJson('fyno:scan', ['--filter' => 'nothing/here/*']);

        $this->assertSame(0, $code);
        $this->assertSame(0, $report['route_count']);
        $this->assertSame([], $report['routes']);

        [$tableCode, $table] = $this->run_('fyno:scan', ['--filter' => 'nothing/here/*']);
        $this->assertSame(0, $tableCode);
        $this->assertStringContainsString('No routes matched', $table);
    }

    public function test_edge_case_routes_are_normalized(): void
    {
        [, $report] = $this->runJson('fyno:scan');
        $routes = $this->routesById($report);

        $this->assertSame('/', $routes['fvt.root']['uri']);

        $this->assertSame(['id'], $routes['fvt.opt']['parameters']);
        $this->assertSame('fvt/opt/{id?}', $routes['fvt.opt']['uri']);
        $this->assertSame(['id' => '[0-9]+'], $routes['fvt.opt']['where_constraints']);

        $this->assertSame('tenant.example.com', $routes['fvt.dom']['domain']);

        $this->assertSame(['GET', 'HEAD', 'POST'], $routes['fvt.multi']['methods']);

        $unnamed = $routes['GET|HEAD fvt/unnamed'];
        $this->assertNull($unnamed['name']);
        $this->assertSame([], $unnamed['middleware']);

        $this->assertSame(['auth:sanctum'], $routes['fvt.documents.show']['middleware']);
        $this->assertSame(['document'], $routes['fvt.documents.show']['parameters']);
    }

    public function test_markdown_output_escapes_route_metadata(): void
    {
        Route::get('fvt/evil', fn () => 'ok')->name('fvt.evil|<b>bold</b>');

        [$code, $markdown] = $this->run_('fyno:scan', ['--format' => 'markdown', '--filter' => 'fvt/evil']);

        $this->assertSame(0, $code);
        $this->assertStringNotContainsString('<b>', $markdown);
        $this->assertStringContainsString('fvt.evil\\|&lt;b&gt;', $markdown);
        $this->assertStringStartsWith('# fynoVerify route inventory', $markdown);
    }

    public function test_default_table_output_lists_routes_and_next_steps(): void
    {
        [$code, $output] = $this->run_('fyno:scan', ['--filter' => 'fvt/documents*']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('fvt.documents.index', $output);
        $this->assertStringContainsString('route(s)', $output);
        $this->assertStringContainsString('fyno:coverage', $output);
    }

    public function test_invalid_format_fails_with_a_helpful_message(): void
    {
        [$code, $output] = $this->run_('fyno:scan', ['--format' => 'xml']);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('Unsupported --format', $output);
    }

    public function test_invalid_filter_fails(): void
    {
        [$code, $output] = $this->run_('fyno:scan', ['--filter' => "bad\npattern"]);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('Invalid --filter', $output);
    }

    public function test_output_option_requires_json_or_markdown(): void
    {
        [$code, $output] = $this->run_('fyno:scan', ['--output' => 'storage/app/x.json']);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('--output requires', $output);
        $this->assertFileDoesNotExist($this->projectRoot.'/storage/app/x.json');
    }

    public function test_report_file_is_written_to_a_safe_path_and_not_silently_replaced(): void
    {
        $options = ['--format' => 'json', '--filter' => 'fvt/*', '--output' => 'storage/app/fynoverify/routes.json'];

        [$code, $output] = $this->run_('fyno:scan', $options);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Report written to storage/app/fynoverify/routes.json', $output);

        $file = $this->projectRoot.'/storage/app/fynoverify/routes.json';
        $this->assertFileExists($file);
        $decoded = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('fynoVerify', $decoded['tool']);

        [$secondCode, $secondOutput] = $this->run_('fyno:scan', $options);
        $this->assertSame(2, $secondCode);
        $this->assertStringContainsString('--force', $secondOutput);

        [$forcedCode] = $this->run_('fyno:scan', $options + ['--force' => true]);
        $this->assertSame(0, $forcedCode);
    }

    public function test_unsafe_output_paths_fail_and_leave_no_files_behind(): void
    {
        file_put_contents($this->projectRoot.'/composer.json', '{"name":"app/app"}');
        $before = $this->snapshot();

        foreach (['../escape.json', '/tmp/fyno-escape.json', 'composer.json', 'config/routes.json', 'storage/report.txt'] as $path) {
            [$code] = $this->run_('fyno:scan', ['--format' => 'json', '--output' => $path, '--force' => true]);

            $this->assertSame(2, $code, "Expected exit code 2 for {$path}");
        }

        $this->assertSame($before, $this->snapshot());
        $this->assertSame('{"name":"app/app"}', file_get_contents($this->projectRoot.'/composer.json'));
        $this->assertFileDoesNotExist(dirname($this->projectRoot).'/escape.json');
        $this->assertFileDoesNotExist('/tmp/fyno-escape.json');
    }

    public function test_scan_is_read_only(): void
    {
        $before = $this->snapshot();

        $this->run_('fyno:scan');
        $this->run_('fyno:scan', ['--format' => 'json']);
        $this->run_('fyno:scan', ['--format' => 'markdown']);

        $this->assertSame($before, $this->snapshot());
    }
}
