<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Unit;

use Rafiquljakir\FynoVerify\Config\Settings;
use Rafiquljakir\FynoVerify\Planning\TestPlanBuilder;
use Rafiquljakir\FynoVerify\Support\PatternMatcher;
use Rafiquljakir\FynoVerify\Support\RouteScope;
use PHPUnit\Framework\TestCase;

final class TestPlanBuilderTest extends TestCase
{
    private function builder(): TestPlanBuilder
    {
        $matcher = new PatternMatcher;

        return new TestPlanBuilder($matcher, new RouteScope($matcher));
    }

    /**
     * @param  list<array<string, mixed>>  $scenarios
     * @return list<string>
     */
    private function categories(array $scenarios): array
    {
        return array_map(static fn (array $s): string => $s['category'], $scenarios);
    }

    public function test_authenticated_show_route_gets_access_ownership_and_lookup_suggestions(): void
    {
        $route = Support::route('api.documents.show', 'api/documents/{document}', ['GET', 'HEAD'], ['api', 'auth:sanctum'], ['document']);

        $categories = $this->categories($this->builder()->scenariosFor($route, Support::settings()));

        $this->assertContains('guest-access', $categories);
        $this->assertContains('authorization', $categories);
        $this->assertContains('ownership', $categories);
        $this->assertContains('not-found', $categories);
        $this->assertContains('api-contract', $categories);
        $this->assertNotContains('validation', $categories);
        $this->assertNotContains('state-change', $categories);
        $this->assertNotContains('access-intent', $categories);
    }

    public function test_unprotected_write_route_gets_validation_and_state_suggestions_without_alarm(): void
    {
        $route = Support::route('api.documents.store', 'api/documents', ['POST'], ['api']);

        $scenarios = $this->builder()->scenariosFor($route, Support::settings());
        $categories = $this->categories($scenarios);

        $this->assertContains('access-intent', $categories);
        $this->assertContains('validation', $categories);
        $this->assertContains('state-change', $categories);
        $this->assertContains('http-method', $categories);
        $this->assertNotContains('guest-access', $categories);
        $this->assertNotContains('ownership', $categories, 'No parameters, so no ownership suggestion.');
        $this->assertNotContains('authorization', $categories);

        $intent = array_values(array_filter($scenarios, static fn (array $s): bool => $s['category'] === 'access-intent'))[0];
        $this->assertStringContainsString('not evidence that the route is public or unprotected', $intent['rationale']);
    }

    public function test_update_routes_get_a_mass_assignment_hypothesis(): void
    {
        $route = Support::route('api.documents.update', 'api/documents/{document}', ['PUT'], ['auth'], ['document']);

        $scenarios = $this->builder()->scenariosFor($route, Support::settings());
        $mass = array_values(array_filter($scenarios, static fn (array $s): bool => $s['category'] === 'mass-assignment'));

        $this->assertCount(1, $mass);
        $this->assertStringContainsString('hypothesis', strtolower($mass[0]['title']));
    }

    public function test_rate_limit_is_only_suggested_with_a_throttle_signal(): void
    {
        $plain = Support::route('a', 'api/a', ['GET'], ['auth']);
        $throttled = Support::route('b', 'api/b', ['GET'], ['throttle:60,1']);

        $this->assertNotContains('rate-limit', $this->categories($this->builder()->scenariosFor($plain, Support::settings())));
        $this->assertContains('rate-limit', $this->categories($this->builder()->scenariosFor($throttled, Support::settings())));
    }

    public function test_priority_rules_are_deterministic(): void
    {
        $privileged = Support::route('a', 'api/admin/users', ['DELETE'], ['auth', 'can:delete,user'], ['user']);
        $scenarios = $this->builder()->scenariosFor($privileged, Support::settings());
        $byCategory = array_column($scenarios, 'priority', 'category');

        $this->assertSame('high', $byCategory['authorization']);
        $this->assertSame('high', $byCategory['state-change']);
        $this->assertSame('high', $byCategory['ownership']);
        $this->assertSame('low', $byCategory['http-method']);

        $general = Support::route('b', 'api/b', ['GET'], ['auth']);
        $this->assertSame('medium', array_column($this->builder()->scenariosFor($general, Support::settings()), 'priority', 'category')['authorization']);
    }

    public function test_scenarios_are_ordered_by_priority_then_catalog_order(): void
    {
        $route = Support::route('a', 'api/admin/x/{x}', ['PATCH'], ['auth', 'can:update,x', 'throttle:10,1'], ['x']);
        $rank = ['high' => 0, 'medium' => 1, 'low' => 2];

        $scenarios = $this->builder()->scenariosFor($route, Support::settings());
        $ranks = array_map(static fn (array $s): int => $rank[$s['priority']], $scenarios);
        $sorted = $ranks;
        sort($sorted);

        $this->assertSame($sorted, $ranks);
    }

    public function test_every_scenario_is_labelled_a_suggestion_and_never_a_verdict(): void
    {
        $route = Support::route('a', 'api/a/{a}', ['POST', 'PUT'], ['auth', 'throttle:5,1'], ['a']);
        $scenarios = $this->builder()->scenariosFor($route, Support::settings());

        $this->assertNotEmpty($scenarios);
        foreach ($scenarios as $scenario) {
            $this->assertSame('suggested', $scenario['status']);
            $this->assertTrue($scenario['heuristic']);
            $this->assertTrue($scenario['requires_human_review']);
            $this->assertNotEmpty($scenario['requires_from_team']);
        }

        $encoded = strtolower((string) json_encode($scenarios));
        foreach (['vulnerab', '"passed"', '"failed"'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $encoded);
        }
    }

    public function test_enabled_suggestions_and_cap_are_respected(): void
    {
        $route = Support::route('a', 'api/a/{a}', ['PUT'], ['auth', 'can:x'], ['a']);

        $filtered = new Settings(['api/*'], [], [], [], true, true, ['validation', 'not-found'], [], 8, null);
        $this->assertEqualsCanonicalizing(['validation', 'not-found'], $this->categories($this->builder()->scenariosFor($route, $filtered)));

        $capped = new Settings(['api/*'], [], [], [], true, true, TestPlanBuilder::CATEGORIES, [], 2, null);
        $this->assertCount(2, $this->builder()->scenariosFor($route, $capped));
    }

    public function test_configured_protected_patterns_count_as_an_auth_signal(): void
    {
        $route = Support::route('a', 'api/private/a', ['GET'], []);
        $settings = new Settings(['api/*'], [], [], [], true, true, TestPlanBuilder::CATEGORIES, ['api/private/*'], 8, null);

        $categories = $this->categories($this->builder()->scenariosFor($route, $settings));

        $this->assertContains('guest-access', $categories);
        $this->assertNotContains('access-intent', $categories);
    }

    public function test_plan_covers_each_in_scope_route_and_bounds_large_sets(): void
    {
        $routes = [];
        for ($i = 1; $i <= 30; $i++) {
            $routes[] = Support::route('r'.$i, sprintf('api/r%02d', $i), ['GET'], ['auth']);
        }
        $routes[] = Support::route('web', 'home');

        $full = $this->builder()->build($routes, Support::settings());

        $this->assertSame(30, $full['summary']['routes_in_plan']);
        $this->assertSame(31, $full['scope']['route_count_total']);
        $this->assertSame(array_map(static fn (int $i): string => 'r'.$i, range(1, 30)), array_column($full['routes'], 'id'));

        $limited = $this->builder()->build($routes, new Settings(['api/*'], [], [], [], true, true, TestPlanBuilder::CATEGORIES, [], 8, 5));

        $this->assertSame(5, $limited['summary']['routes_in_plan']);
        $this->assertSame(25, $limited['summary']['routes_omitted']);
        $this->assertNotEmpty($limited['warnings']);
    }

    public function test_plan_carries_the_required_disclaimer(): void
    {
        $plan = $this->builder()->build([Support::route('a', 'api/a')], Support::settings());

        $this->assertSame('These are metadata-based test suggestions, not executed tests or a security verdict.', $plan['disclaimer']);
        $this->assertSame('plan', $plan['report_type']);
    }
}
