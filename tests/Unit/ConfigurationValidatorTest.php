<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Unit;

use Rafiquljakir\FynoVerify\Config\ConfigurationValidator;
use Rafiquljakir\FynoVerify\Planning\TestPlanBuilder;
use Rafiquljakir\FynoVerify\Support\PatternMatcher;
use PHPUnit\Framework\TestCase;

final class ConfigurationValidatorTest extends TestCase
{
    private function validator(): ConfigurationValidator
    {
        return new ConfigurationValidator(new PatternMatcher);
    }

    public function test_missing_config_uses_documented_defaults(): void
    {
        $result = $this->validator()->validate(null);

        $this->assertFalse($result->isValid(), 'null is not an array, which is reported.');

        $result = $this->validator()->validate([]);

        $this->assertTrue($result->isValid());
        $this->assertSame(['api/*'], $result->settings->includeUriPatterns);
        $this->assertSame([], $result->settings->excludeUriPatterns);
        $this->assertSame([], $result->settings->coveredRoutes);
        $this->assertSame([], $result->settings->ignoredRoutes);
        $this->assertTrue($result->settings->failOnStaleDeclarations);
        $this->assertTrue($result->settings->failOnEmptyScope);
        $this->assertSame(TestPlanBuilder::CATEGORIES, $result->settings->planEnabledSuggestions);
        $this->assertSame(8, $result->settings->planMaxScenariosPerRoute);
        $this->assertNull($result->settings->planMaxRoutes);
    }

    public function test_partial_config_keeps_defaults_for_omitted_keys(): void
    {
        $result = $this->validator()->validate(['coverage' => ['fail_on_empty_scope' => false]]);

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->settings->failOnEmptyScope);
        $this->assertTrue($result->settings->failOnStaleDeclarations);
        $this->assertSame(['api/*'], $result->settings->includeUriPatterns);
    }

    public function test_wrong_types_name_the_offending_key(): void
    {
        $result = $this->validator()->validate(['coverage' => [
            'include_uri_patterns' => 'api/*',
            'covered_routes' => 'tests/x.php',
            'fail_on_stale_declarations' => 'yes',
        ]]);

        $errors = implode("\n", $result->errors);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('coverage.include_uri_patterns', $errors);
        $this->assertStringContainsString('coverage.covered_routes', $errors);
        $this->assertStringContainsString('coverage.fail_on_stale_declarations', $errors);
    }

    public function test_empty_include_list_is_an_error(): void
    {
        $result = $this->validator()->validate(['coverage' => ['include_uri_patterns' => []]]);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('at least one pattern', $result->errors[0]);
    }

    public function test_invalid_patterns_are_reported_with_their_index(): void
    {
        $result = $this->validator()->validate(['coverage' => ['exclude_uri_patterns' => ['ok/*', '', 7]]]);

        $this->assertCount(2, $result->errors);
        $this->assertStringContainsString('exclude_uri_patterns[1]', $result->errors[0]);
        $this->assertStringContainsString('exclude_uri_patterns[2]', $result->errors[1]);
    }

    public function test_list_instead_of_map_is_rejected(): void
    {
        $result = $this->validator()->validate(['coverage' => ['covered_routes' => ['tests/FooTest.php']]]);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('route ID => test file path', $result->errors[0]);
    }

    public function test_non_string_map_values_are_rejected(): void
    {
        $result = $this->validator()->validate(['coverage' => ['ignored_routes' => ['api.health' => ['nope']]]]);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('coverage.ignored_routes.api.health', $result->errors[0]);
    }

    public function test_blank_declaration_values_are_left_for_the_audit_to_flag_per_route(): void
    {
        $result = $this->validator()->validate(['coverage' => [
            'covered_routes' => ['a' => '  '],
            'ignored_routes' => ['b' => ''],
        ]]);

        $this->assertTrue($result->isValid());
        $this->assertSame(['a' => '  '], $result->settings->coveredRoutes);
        $this->assertSame(['b' => ''], $result->settings->ignoredRoutes);
    }

    public function test_unknown_keys_are_warnings_not_errors(): void
    {
        $result = $this->validator()->validate(['extra' => 1, 'coverage' => ['typo_key' => true], 'plan' => ['nope' => 1]]);

        $this->assertTrue($result->isValid());
        $this->assertCount(3, $result->warnings);
    }

    public function test_plan_settings_are_validated(): void
    {
        $result = $this->validator()->validate(['plan' => [
            'enabled_suggestions' => ['validation', 'made-up'],
            'max_scenarios_per_route' => 0,
            'max_routes' => 'ten',
        ]]);

        $errors = implode("\n", $result->errors);

        $this->assertStringContainsString('plan.enabled_suggestions[1]', $errors);
        $this->assertStringContainsString('plan.max_scenarios_per_route', $errors);
        $this->assertStringContainsString('plan.max_routes', $errors);

        $ok = $this->validator()->validate(['plan' => ['enabled_suggestions' => ['validation'], 'max_routes' => 5]]);

        $this->assertTrue($ok->isValid());
        $this->assertSame(['validation'], $ok->settings->planEnabledSuggestions);
        $this->assertSame(5, $ok->settings->planMaxRoutes);
    }
}
