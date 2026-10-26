<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Support;

use Rafiquljakir\FynoVerify\Config\Settings;
use Rafiquljakir\FynoVerify\DTOs\RouteRecord;

/**
 * Applies the audit scope: include patterns first, then exclude patterns.
 * Shared by `fyno:coverage` and `fyno:plan` so both always agree on scope.
 */
final class RouteScope
{
    public function __construct(private readonly PatternMatcher $matcher) {}

    /**
     * @param  list<RouteRecord>  $routes
     * @return array{in_scope: list<RouteRecord>, excluded: list<RouteRecord>} `excluded` holds every route that is NOT in scope.
     */
    public function partition(array $routes, Settings $settings): array
    {
        $inScope = [];
        $excluded = [];

        foreach ($routes as $route) {
            $included = $this->matcher->matchesAny($settings->includeUriPatterns, $route->uri)
                && ! $this->matcher->matchesAny($settings->excludeUriPatterns, $route->uri);

            if ($included) {
                $inScope[] = $route;
            } else {
                $excluded[] = $route;
            }
        }

        return ['in_scope' => $inScope, 'excluded' => $excluded];
    }
}
