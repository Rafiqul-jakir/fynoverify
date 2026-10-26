<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| fynoVerify configuration
|--------------------------------------------------------------------------
| Every key is optional; omitted keys fall back to the defaults shown here.
| See docs/CONFIGURATION.md for the accepted types and behavior of each key.
*/

return [
    'coverage' => [
        // Routes whose normalized URI matches ANY of these glob patterns are in scope.
        // Must contain at least one pattern. "*" matches any characters, including "/".
        'include_uri_patterns' => ['api/*'],

        // Applied AFTER the include patterns: matching routes are removed from scope.
        // Excluded routes are still listed in the report so the effective scope is visible.
        'exclude_uri_patterns' => [],

        // Route ID => project-relative path of the test file that is your evidence.
        // Replace with real IDs from `php artisan fyno:scan`. Example:
        //   'api.documents.index' => 'tests/Feature/Api/DocumentIndexTest.php',
        'covered_routes' => [],

        // Route ID => non-empty reason this route is intentionally exempt. Example:
        //   'api.health' => 'Public liveness endpoint with no sensitive application data.',
        'ignored_routes' => [],

        // Fail (exit code 1) when a declared route ID no longer exists.
        'fail_on_stale_declarations' => true,

        // Fail (exit code 1) when the include/exclude patterns match no routes.
        'fail_on_empty_scope' => true,
    ],

    'plan' => [
        // Scenario categories that may appear in `fyno:plan`. Omit categories to hide them.
        'enabled_suggestions' => [
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
        ],

        // Extra URI glob patterns you consider protected (treated like an authentication signal).
        'protected_uri_patterns' => [],

        // Upper bound on suggestions kept per route (highest priority first).
        'max_scenarios_per_route' => 8,

        // Upper bound on routes included in a plan; null means no limit.
        'max_routes' => null,
    ],
];
