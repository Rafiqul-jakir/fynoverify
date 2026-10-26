<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\DTOs;

use stdClass;

/**
 * Normalized, read-only description of one registered route.
 */
final class RouteRecord
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * @param  list<string>  $methods  Sorted, unique, upper-case HTTP verbs.
     * @param  list<string>  $middleware  Middleware labels as declared on the route (groups are not expanded).
     * @param  list<string>  $parameters  Parameter names without optional markers.
     * @param  array<string, string>  $whereConstraints  Parameter name => constraint pattern, sorted by name.
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $name,
        public readonly array $methods,
        public readonly ?string $domain,
        public readonly string $uri,
        public readonly string $action,
        public readonly array $middleware,
        public readonly array $parameters,
        public readonly array $whereConstraints,
    ) {}

    public function isWrite(): bool
    {
        return array_intersect(self::WRITE_METHODS, $this->methods) !== [];
    }

    public function hasMethod(string $method): bool
    {
        return in_array(strtoupper($method), $this->methods, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'methods' => $this->methods,
            'domain' => $this->domain,
            'uri' => $this->uri,
            'action' => $this->action,
            'middleware' => $this->middleware,
            'parameters' => $this->parameters,
            // An empty PHP array would serialize as [] rather than {}.
            'where_constraints' => $this->whereConstraints === [] ? new stdClass : $this->whereConstraints,
        ];
    }
}
