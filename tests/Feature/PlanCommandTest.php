<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Feature;

final class PlanCommandTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->registerRoutes();
    }

    public function test_json_plan_contains_each_in_scope_route_and_only_suggestions(): void
    {
        [$code, $plan] = $this->runJson('fyno:plan');

        $this->assertSame(0, $code);
        $this->assertSame('plan', $plan['report_type']);
        $this->assertSame('These are metadata-based test suggestions, not executed tests or a security verdict.', $plan['disclaimer']);

        $ids = array_column($plan['routes'], 'id');
        foreach (['fvt.documents.index', 'fvt.documents.show', 'fvt.documents.update', 'fvt.dom', 'fvt.opt'] as $id) {
            $this->assertContains($id, $ids);
        }
        $this->assertNotContains('fvt.root', $ids, 'The root route is not matched by fvt/*.');

        foreach ($plan['routes'] as $route) {
            foreach ($route['scenarios'] as $scenario) {
                $this->assertSame('suggested', $scenario['status']);
                $this->assertTrue($scenario['requires_human_review']);
            }
        }

        $this->assertStringNotContainsStringIgnoringCase('vulnerab', (string) json_encode($plan));
    }

    public function test_suggestions_respond_to_method_parameters_and_middleware(): void
    {
        [, $plan] = $this->runJson('fyno:plan');
        $byId = $this->routesById($plan);
        $categories = static fn (string $id): array => array_column($byId[$id]['scenarios'], 'category');

        $this->assertContains('guest-access', $categories('fvt.documents.show'));
        $this->assertContains('ownership', $categories('fvt.documents.show'));
        $this->assertContains('mass-assignment', $categories('fvt.documents.update'));
        $this->assertContains('validation', $categories('fvt.documents.store'));
        $this->assertContains('access-intent', $categories('fvt.documents.store'));
        $this->assertNotContains('validation', $categories('fvt.documents.index'));
        $this->assertNotContains('not-found', $categories('fvt.documents.index'));
    }

    public function test_markdown_and_table_formats_work(): void
    {
        [$markdownCode, $markdown] = $this->run_('fyno:plan', ['--format' => 'markdown']);
        $this->assertSame(0, $markdownCode);
        $this->assertStringStartsWith('# fynoVerify test plan', $markdown);
        $this->assertStringContainsString('(suggested)', $markdown);

        [$tableCode, $table] = $this->run_('fyno:plan');
        $this->assertSame(0, $tableCode);
        $this->assertStringContainsString('suggested test plan', $table);
        $this->assertStringContainsString('not executed tests or a security verdict', $table);
    }

    public function test_max_routes_limits_a_large_plan(): void
    {
        config()->set('fynoVerify.plan.max_routes', 2);

        [, $plan] = $this->runJson('fyno:plan');

        $this->assertSame(2, $plan['summary']['routes_in_plan']);
        $this->assertGreaterThan(0, $plan['summary']['routes_omitted']);
        $this->assertNotEmpty($plan['warnings']);
    }

    public function test_invalid_configuration_exits_two(): void
    {
        config()->set('fynoVerify.plan.enabled_suggestions', ['not-a-category']);

        [$code, $output] = $this->run_('fyno:plan', ['--format' => 'json']);

        $this->assertSame(2, $code);
        $this->assertStringContainsString('plan.enabled_suggestions', $output);
    }

    public function test_output_file_and_unsafe_paths(): void
    {
        [$code] = $this->run_('fyno:plan', ['--format' => 'markdown', '--output' => 'storage/app/fynoverify/test-plan.md']);
        $this->assertSame(0, $code);
        $this->assertFileExists($this->projectRoot.'/storage/app/fynoverify/test-plan.md');

        [$unsafe] = $this->run_('fyno:plan', ['--format' => 'markdown', '--output' => 'app/test-plan.md']);
        $this->assertSame(2, $unsafe);
        $this->assertFileDoesNotExist($this->projectRoot.'/app/test-plan.md');
    }

    public function test_plan_is_read_only(): void
    {
        $before = $this->snapshot();

        $this->run_('fyno:plan');
        $this->run_('fyno:plan', ['--format' => 'json']);

        $this->assertSame($before, $this->snapshot());
    }
}
