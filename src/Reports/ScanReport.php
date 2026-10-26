<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Reports;

use Rafiquljakir\FynoVerify\DTOs\RouteRecord;
use Rafiquljakir\FynoVerify\FynoVerify;

final class ScanReport
{
    /**
     * @param  list<RouteRecord>  $routes
     * @return array<string, mixed>
     */
    public static function build(array $routes, ?string $filter): array
    {
        $counts = [];
        foreach ($routes as $route) {
            $counts[$route->id] = ($counts[$route->id] ?? 0) + 1;
        }

        $warnings = [];
        foreach ($counts as $id => $count) {
            if ($count > 1) {
                $warnings[] = sprintf('Route ID "%s" is shared by %d routes; coverage declarations for it would be ambiguous.', (string) $id, $count);
            }
        }

        return [
            'tool' => FynoVerify::NAME,
            'schema_version' => FynoVerify::SCHEMA_VERSION,
            'report_type' => 'scan',
            'disclaimer' => FynoVerify::SCAN_DISCLAIMER,
            'filter' => $filter,
            'route_count' => count($routes),
            'routes' => array_map(static fn (RouteRecord $r): array => $r->toArray(), $routes),
            'warnings' => $warnings,
        ];
    }
}
