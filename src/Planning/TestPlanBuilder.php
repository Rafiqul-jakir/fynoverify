<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Planning;

use Rafiquljakir\FynoVerify\Config\Settings;
use Rafiquljakir\FynoVerify\DTOs\RouteRecord;
use Rafiquljakir\FynoVerify\FynoVerify;
use Rafiquljakir\FynoVerify\Support\PatternMatcher;
use Rafiquljakir\FynoVerify\Support\RouteScope;

/**
 * Builds metadata-based test suggestions. Nothing here runs a test or probes the app, and
 * nothing is ever labelled passed, failed or vulnerable: every item is a "suggested" scenario
 * that needs human review.
 *
 * Priority rules (deterministic, documented in docs/COMMANDS.md):
 *  - guest-access: high (an authentication-related signal exists)
 *  - access-intent: medium (no visible authentication signal; intent is unknown)
 *  - authorization: high with a permission/role/admin signal, otherwise medium
 *  - ownership: high for write routes with parameters, otherwise medium
 *  - validation: medium
 *  - not-found: medium
 *  - state-change: high for PUT/PATCH/DELETE, otherwise medium
 *  - mass-assignment: medium
 *  - rate-limit: medium
 *  - http-method: low
 *  - api-contract: low
 */
final class TestPlanBuilder
{
    /** Catalog order; also the allowed values of plan.enabled_suggestions. */
    public const CATEGORIES = [
        'guest-access',
        'access-intent',
        'authorization',
        'ownership',
        'validation',
        'not-found',
        'state-change',
        'http-method',
        'rate-limit',
        'mass-assignment',
        'api-contract',
    ];

    private const PRIORITY_RANK = ['high' => 0, 'medium' => 1, 'low' => 2];

    private const AUTH_MIDDLEWARE = '/^(auth|auth\.basic|can|role|permission|abilities|ability)(:|$)/i';

    private const PRIVILEGE_MIDDLEWARE = '/^(can|role|permission|abilities|ability)(:|$)/i';

    public function __construct(
        private readonly PatternMatcher $matcher,
        private readonly RouteScope $scope,
    ) {}

    /**
     * @param  list<RouteRecord>  $routes  The full route inventory.
     * @return array<string, mixed>
     */
    public function build(array $routes, Settings $settings): array
    {
        $partition = $this->scope->partition($routes, $settings);
        $inScope = $partition['in_scope'];
        $warnings = [];

        $omitted = 0;
        if ($settings->planMaxRoutes !== null && count($inScope) > $settings->planMaxRoutes) {
            $omitted = count($inScope) - $settings->planMaxRoutes;
            $inScope = array_slice($inScope, 0, $settings->planMaxRoutes);
            $warnings[] = sprintf('plan.max_routes limited the plan to %d route(s); %d in-scope route(s) were omitted.', $settings->planMaxRoutes, $omitted);
        }

        $items = [];
        $byPriority = ['high' => 0, 'medium' => 0, 'low' => 0];
        $scenarioCount = 0;

        foreach ($inScope as $route) {
            $scenarios = $this->scenariosFor($route, $settings);
            foreach ($scenarios as $scenario) {
                $byPriority[$scenario['priority']]++;
                $scenarioCount++;
            }

            $items[] = [
                'id' => $route->id,
                'name' => $route->name,
                'methods' => $route->methods,
                'uri' => $route->uri,
                'middleware' => $route->middleware,
                'action' => $route->action,
                'scenarios' => $scenarios,
            ];
        }

        if ($inScope === [] && $omitted === 0) {
            $warnings[] = 'No routes matched the configured scope, so the plan is empty.';
        }

        return [
            'tool' => FynoVerify::NAME,
            'schema_version' => FynoVerify::SCHEMA_VERSION,
            'report_type' => 'plan',
            'disclaimer' => FynoVerify::PLAN_DISCLAIMER,
            'scope' => [
                'include_uri_patterns' => $settings->includeUriPatterns,
                'exclude_uri_patterns' => $settings->excludeUriPatterns,
                'route_count_total' => count($routes),
                'route_count_in_scope' => count($partition['in_scope']),
            ],
            'summary' => [
                'routes_in_plan' => count($items),
                'routes_omitted' => $omitted,
                'scenario_count' => $scenarioCount,
                'by_priority' => $byPriority,
            ],
            'routes' => $items,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scenariosFor(RouteRecord $route, Settings $settings): array
    {
        $hasAuth = $this->hasAuthSignal($route, $settings);
        $hasPrivilege = $this->hasPrivilegeSignal($route);
        $isWrite = $route->isWrite();
        $hasParameters = $route->parameters !== [];
        $updatesResource = $route->hasMethod('PUT') || $route->hasMethod('PATCH');
        $changesState = $updatesResource || $route->hasMethod('DELETE');

        $candidates = [];

        if ($hasAuth) {
            $candidates[] = $this->scenario(
                'guest-access',
                'Unauthenticated request is handled as intended',
                'This route shows an authentication-related signal (middleware label or configured protected pattern). A middleware label is not proof of correct behavior, so verify the real response (commonly 401, but your contract decides).',
                'high',
                'High because an authentication-related signal is present.',
                'route-metadata',
                ['authentication guard in use', 'expected unauthenticated status code or contract'],
            );
        } else {
            $candidates[] = $this->scenario(
                'access-intent',
                'Confirm and test the intended access policy',
                'No authentication-related middleware label is visible on this route. Global or custom mechanisms may still apply, so this is not evidence that the route is public or unprotected.',
                'medium',
                'Medium because the intended access policy cannot be inferred from route metadata.',
                'route-metadata',
                ['intended access policy (public or authenticated)'],
            );
        }

        if ($hasAuth) {
            $candidates[] = $this->scenario(
                'authorization',
                'Authenticated user without the required permission is handled as intended',
                'Authentication does not imply authorization. Verify that an authenticated principal lacking access gets the contract-defined response (403, 404 or another status).',
                $hasPrivilege ? 'high' : 'medium',
                $hasPrivilege
                    ? 'High because a permission/role/admin signal is present.'
                    : 'Medium because only a general authentication signal is present.',
                'route-metadata',
                ['test principals with and without access', 'authorization policy or role model', 'expected status code'],
            );
        }

        if ($hasParameters) {
            $candidates[] = $this->scenario(
                'ownership',
                'Access to another user\'s or tenant\'s resource is handled as intended',
                sprintf('Route parameter(s) %s may identify a resource. If your application has ownership or tenancy rules, verify cross-owner access. This tool cannot tell whether such rules exist.', implode(', ', $route->parameters)),
                $isWrite ? 'high' : 'medium',
                $isWrite ? 'High because the route has parameters and a write method.' : 'Medium because the route has parameters.',
                'route-metadata',
                ['whether ownership or tenancy rules exist, and the model that defines them', 'two test principals or tenants'],
            );
        }

        if ($isWrite) {
            $candidates[] = $this->scenario(
                'validation',
                'Invalid and boundary input is rejected without side effects',
                'Write methods usually accept input. Verify missing, malformed and boundary values are rejected as your validation rules define.',
                'medium',
                'Medium because the route accepts a write method.',
                'route-metadata',
                ['a valid payload', 'validation rules and expected error responses'],
            );
        }

        if ($hasParameters) {
            $candidates[] = $this->scenario(
                'not-found',
                'Unknown or deleted resource identifier is handled as intended',
                'Routes with parameters receive identifiers that may not exist. Verify the contract-defined response (commonly 404).',
                'medium',
                'Medium because the route has parameters.',
                'route-metadata',
                ['an identifier that does not exist', 'expected status code'],
            );
        }

        if ($isWrite) {
            $candidates[] = $this->scenario(
                'state-change',
                'Expected state change occurs and failed requests leave no partial side effects',
                'Write and delete routes change state. Verify the intended database/state change and that rejected requests change nothing.',
                $changesState ? 'high' : 'medium',
                $changesState ? 'High because the route uses PUT, PATCH or DELETE.' : 'Medium because the route uses POST.',
                'route-metadata',
                ['data factories or fixtures', 'expected database or state changes'],
            );

            $candidates[] = $this->scenario(
                'http-method',
                'Unsupported HTTP methods are rejected',
                'Verify methods this route does not declare are rejected (commonly 405).',
                'low',
                'Low because it is a hygiene check on a write route.',
                'route-metadata',
                ['expected status code for unsupported methods'],
            );
        }

        if ($this->hasThrottleSignal($route)) {
            $candidates[] = $this->scenario(
                'rate-limit',
                'Throttling behaves as configured',
                'A throttle middleware label is present on this route. Verify the limit and the response once it is exceeded.',
                'medium',
                'Medium because a throttle middleware label is present.',
                'route-metadata',
                ['the configured limit and window', 'expected status code once limited'],
            );
        }

        if ($updatesResource) {
            $candidates[] = $this->scenario(
                'mass-assignment',
                'Privileged fields cannot be changed through this endpoint (hypothesis)',
                'Update routes may accept more fields than intended. This is a hypothesis that requires application-specific review.',
                'medium',
                'Medium because the route uses PUT or PATCH.',
                'route-metadata',
                ['the list of privileged fields', 'model fillable/guarded rules or request validation'],
            );
        }

        if ($route->uri === 'api' || str_starts_with($route->uri, 'api/')) {
            $candidates[] = $this->scenario(
                'api-contract',
                'Response shape matches the team-defined API contract',
                'The URI follows an API convention, but the expected response shape cannot be inferred. A team-defined contract is required.',
                'low',
                'Low because it depends entirely on a contract the team must supply.',
                'route-metadata',
                ['team-defined response contract (schema or resource)'],
            );
        }

        return $this->finalize($candidates, $settings);
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function finalize(array $candidates, Settings $settings): array
    {
        $seen = [];
        $unique = [];
        foreach ($candidates as $candidate) {
            if (! in_array($candidate['category'], $settings->planEnabledSuggestions, true)) {
                continue;
            }

            $key = $candidate['category'].'|'.$candidate['title'];
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $candidate;
        }

        // Stable order: priority, then catalog position.
        usort($unique, static fn (array $a, array $b): int => (self::PRIORITY_RANK[$a['priority']] <=> self::PRIORITY_RANK[$b['priority']])
            ?: (array_search($a['category'], self::CATEGORIES, true) <=> array_search($b['category'], self::CATEGORIES, true)));

        return array_slice($unique, 0, $settings->planMaxScenariosPerRoute);
    }

    /**
     * @param  list<string>  $requires
     * @return array<string, mixed>
     */
    private function scenario(string $category, string $title, string $rationale, string $priority, string $priorityRule, string $basis, array $requires): array
    {
        return [
            'category' => $category,
            'title' => $title,
            'status' => 'suggested',
            'priority' => $priority,
            'priority_rule' => $priorityRule,
            'rationale' => $rationale,
            'basis' => $basis,
            'heuristic' => true,
            'requires_human_review' => true,
            'requires_from_team' => $requires,
        ];
    }

    private function hasAuthSignal(RouteRecord $route, Settings $settings): bool
    {
        foreach ($route->middleware as $label) {
            if (preg_match(self::AUTH_MIDDLEWARE, $label) === 1
                || stripos($label, 'Authenticate') !== false
                || stripos($label, 'Authorize') !== false) {
                return true;
            }
        }

        return $this->matcher->matchesAny($settings->planProtectedUriPatterns, $route->uri);
    }

    private function hasPrivilegeSignal(RouteRecord $route): bool
    {
        foreach ($route->middleware as $label) {
            if (preg_match(self::PRIVILEGE_MIDDLEWARE, $label) === 1 || stripos($label, 'Authorize') !== false) {
                return true;
            }
        }

        return in_array('admin', explode('/', strtolower($route->uri)), true);
    }

    private function hasThrottleSignal(RouteRecord $route): bool
    {
        foreach ($route->middleware as $label) {
            if (preg_match('/^throttle(:|$)/i', $label) === 1 || stripos($label, 'ThrottleRequests') !== false) {
                return true;
            }
        }

        return false;
    }
}
