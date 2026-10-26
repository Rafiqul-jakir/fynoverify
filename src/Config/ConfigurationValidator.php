<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Config;

use Rafiquljakir\FynoVerify\FynoVerify;
use Rafiquljakir\FynoVerify\Planning\TestPlanBuilder;
use Rafiquljakir\FynoVerify\Support\PatternMatcher;

/**
 * Validates raw configuration without assuming any value has the right type.
 *
 * Structural problems (wrong types, bad patterns, empty include list) are errors and make
 * `fyno:coverage` exit with code 2. Per-route declaration problems (blank reason, blank path,
 * route declared twice) are NOT detected here: the audit reports them as route-level
 * `configuration-error` statuses so they appear next to the route they affect.
 */
final class ConfigurationValidator
{
    private const COVERAGE_KEYS = [
        'include_uri_patterns', 'exclude_uri_patterns', 'covered_routes', 'ignored_routes',
        'fail_on_stale_declarations', 'fail_on_empty_scope',
    ];

    private const PLAN_KEYS = [
        'enabled_suggestions', 'protected_uri_patterns', 'max_scenarios_per_route', 'max_routes',
    ];

    public function __construct(private readonly PatternMatcher $matcher) {}

    public function validate(mixed $config): ValidationResult
    {
        $errors = [];
        $warnings = [];
        $root = FynoVerify::CONFIG_KEY;

        if (! is_array($config)) {
            $errors[] = sprintf('%s: configuration must be an array, got %s.', $root, get_debug_type($config));
            $config = [];
        }

        foreach (array_keys($config) as $key) {
            if (! in_array($key, ['coverage', 'plan'], true)) {
                $warnings[] = sprintf('%s.%s: unknown key (ignored).', $root, (string) $key);
            }
        }

        $coverage = $this->section($config, 'coverage', $errors);
        $plan = $this->section($config, 'plan', $errors);

        $this->warnUnknown($coverage, self::COVERAGE_KEYS, 'coverage', $warnings);
        $this->warnUnknown($plan, self::PLAN_KEYS, 'plan', $warnings);

        $include = $this->patternList($coverage, 'include_uri_patterns', 'coverage', ['api/*'], $errors);
        if ($include === []) {
            $errors[] = 'coverage.include_uri_patterns: must contain at least one pattern (an empty list would silently audit nothing).';
            $include = ['api/*'];
        }

        $exclude = $this->patternList($coverage, 'exclude_uri_patterns', 'coverage', [], $errors);
        $covered = $this->stringMap($coverage, 'covered_routes', 'coverage', 'route ID => test file path', $errors);
        $ignored = $this->stringMap($coverage, 'ignored_routes', 'coverage', 'route ID => reason', $errors);
        $failStale = $this->bool($coverage, 'fail_on_stale_declarations', 'coverage', true, $errors);
        $failEmpty = $this->bool($coverage, 'fail_on_empty_scope', 'coverage', true, $errors);

        $enabled = $this->enabledSuggestions($plan, $errors);
        $protected = $this->patternList($plan, 'protected_uri_patterns', 'plan', [], $errors);
        $maxScenarios = $this->positiveInt($plan, 'max_scenarios_per_route', 'plan', 8, false, $errors);
        $maxRoutes = $this->positiveInt($plan, 'max_routes', 'plan', null, true, $errors);

        return new ValidationResult(
            new Settings(
                $include,
                $exclude,
                $covered,
                $ignored,
                $failStale,
                $failEmpty,
                $enabled,
                $protected,
                (int) $maxScenarios,
                $maxRoutes,
            ),
            $errors,
            $warnings,
        );
    }

    /**
     * @param  array<mixed>  $config
     * @param  list<string>  $errors
     * @return array<mixed>
     */
    private function section(array $config, string $key, array &$errors): array
    {
        if (! array_key_exists($key, $config)) {
            return [];
        }

        if (! is_array($config[$key])) {
            $errors[] = sprintf('%s.%s: expected an array, got %s.', FynoVerify::CONFIG_KEY, $key, get_debug_type($config[$key]));

            return [];
        }

        return $config[$key];
    }

    /**
     * @param  array<mixed>  $section
     * @param  list<string>  $known
     * @param  list<string>  $warnings
     */
    private function warnUnknown(array $section, array $known, string $name, array &$warnings): void
    {
        foreach (array_keys($section) as $key) {
            if (! in_array($key, $known, true)) {
                $warnings[] = sprintf('%s.%s: unknown key (ignored).', $name, (string) $key);
            }
        }
    }

    /**
     * @param  array<mixed>  $section
     * @param  list<string>  $default
     * @param  list<string>  $errors
     * @return list<string>
     */
    private function patternList(array $section, string $key, string $name, array $default, array &$errors): array
    {
        if (! array_key_exists($key, $section)) {
            return $default;
        }

        $path = $name.'.'.$key;
        $value = $section[$key];

        if (! is_array($value)) {
            $errors[] = sprintf('%s: expected a list of strings, got %s.', $path, get_debug_type($value));

            return $default;
        }

        $patterns = [];
        foreach ($value as $index => $pattern) {
            $problem = $this->matcher->validatePattern($pattern);
            if ($problem !== null) {
                $errors[] = sprintf('%s[%s]: %s.', $path, (string) $index, $problem);

                continue;
            }
            $patterns[] = (string) $pattern;
        }

        return $patterns;
    }

    /**
     * @param  array<mixed>  $section
     * @param  list<string>  $errors
     * @return array<string, string>
     */
    private function stringMap(array $section, string $key, string $name, string $shape, array &$errors): array
    {
        if (! array_key_exists($key, $section)) {
            return [];
        }

        $path = $name.'.'.$key;
        $value = $section[$key];

        if (! is_array($value)) {
            $errors[] = sprintf('%s: expected a map of %s, got %s.', $path, $shape, get_debug_type($value));

            return [];
        }

        $map = [];
        foreach ($value as $id => $item) {
            if (is_int($id)) {
                $errors[] = sprintf('%s: expected a map of %s but found list entry #%d; use string keys (route IDs).', $path, $shape, $id);

                continue;
            }

            if (! is_string($item)) {
                $errors[] = sprintf('%s.%s: expected a string, got %s.', $path, $id, get_debug_type($item));

                continue;
            }

            $map[$id] = $item;
        }

        return $map;
    }

    /**
     * @param  array<mixed>  $section
     * @param  list<string>  $errors
     */
    private function bool(array $section, string $key, string $name, bool $default, array &$errors): bool
    {
        if (! array_key_exists($key, $section)) {
            return $default;
        }

        if (! is_bool($section[$key])) {
            $errors[] = sprintf('%s.%s: expected a boolean, got %s.', $name, $key, get_debug_type($section[$key]));

            return $default;
        }

        return $section[$key];
    }

    /**
     * @param  array<mixed>  $section
     * @param  list<string>  $errors
     */
    private function positiveInt(array $section, string $key, string $name, ?int $default, bool $nullable, array &$errors): ?int
    {
        if (! array_key_exists($key, $section)) {
            return $default;
        }

        $value = $section[$key];

        if ($value === null && $nullable) {
            return null;
        }

        if (! is_int($value) || $value < 1) {
            $errors[] = sprintf(
                '%s.%s: expected a positive integer%s, got %s.',
                $name,
                $key,
                $nullable ? ' or null' : '',
                is_int($value) ? (string) $value : get_debug_type($value),
            );

            return $default;
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $plan
     * @param  list<string>  $errors
     * @return list<string>
     */
    private function enabledSuggestions(array $plan, array &$errors): array
    {
        $default = TestPlanBuilder::CATEGORIES;

        if (! array_key_exists('enabled_suggestions', $plan)) {
            return $default;
        }

        $value = $plan['enabled_suggestions'];

        if (! is_array($value)) {
            $errors[] = sprintf('plan.enabled_suggestions: expected a list of strings, got %s.', get_debug_type($value));

            return $default;
        }

        $enabled = [];
        foreach ($value as $index => $category) {
            if (! is_string($category) || ! in_array($category, TestPlanBuilder::CATEGORIES, true)) {
                $errors[] = sprintf(
                    'plan.enabled_suggestions[%s]: unknown category. Allowed: %s.',
                    (string) $index,
                    implode(', ', TestPlanBuilder::CATEGORIES),
                );

                continue;
            }
            $enabled[] = $category;
        }

        return array_values(array_unique($enabled));
    }
}
