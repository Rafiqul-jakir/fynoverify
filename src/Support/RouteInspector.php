<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Support;

use Rafiquljakir\FynoVerify\DTOs\RouteRecord;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Read-only route inventory built from Laravel's public routing API.
 * It never invokes controllers, middleware or requests.
 */
final class RouteInspector
{
    public function __construct(private readonly Router $router) {}

    /**
     * @return list<RouteRecord> Sorted by URI, methods, domain, then ID (byte-wise, locale independent).
     */
    public function all(): array
    {
        $records = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $records[] = $this->toRecord($route);
        }

        usort($records, static fn (RouteRecord $a, RouteRecord $b): int => strcmp($a->uri, $b->uri)
            ?: strcmp(implode('|', $a->methods), implode('|', $b->methods))
            ?: strcmp($a->domain ?? '', $b->domain ?? '')
            ?: strcmp($a->id, $b->id));

        return $records;
    }

    private function toRecord(Route $route): RouteRecord
    {
        $methods = array_values(array_unique(array_map('strtoupper', $route->methods())));
        sort($methods, SORT_STRING);

        $uri = PatternMatcher::normalizeUri($route->uri());

        $name = $route->getName();
        $name = is_string($name) && trim($name) !== '' ? $name : null;

        $domain = $route->getDomain();
        $domain = is_string($domain) && $domain !== '' ? $domain : null;

        $middleware = [];
        foreach ($route->gatherMiddleware() as $label) {
            $middleware[] = is_string($label) ? $label : 'Closure';
        }

        $wheres = [];
        foreach ($route->wheres as $parameter => $constraint) {
            $wheres[(string) $parameter] = (string) $constraint;
        }
        ksort($wheres, SORT_STRING);

        return new RouteRecord(
            RouteIdentifier::for($name, $methods, $domain, $uri),
            $name,
            $methods,
            $domain,
            $uri,
            $route->getActionName(),
            array_values(array_unique($middleware)),
            array_values($route->parameterNames()),
            $wheres,
        );
    }
}
