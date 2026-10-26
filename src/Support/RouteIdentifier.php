<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Support;

/**
 * Builds the identifier used by scan, coverage, plan, reports and configuration.
 *
 * Named routes use their exact name. Unnamed routes use a deterministic fallback:
 *   "<METHODS joined by |> <uri>"  or  "<METHODS> <uri> @ <domain>"
 * e.g. "GET|HEAD api/ping" or "POST api/x @ tenant.example.com".
 */
final class RouteIdentifier
{
    /**
     * @param  list<string>  $methods
     */
    public static function for(?string $name, array $methods, ?string $domain, string $uri): string
    {
        if ($name !== null && trim($name) !== '') {
            return $name;
        }

        return sprintf(
            '%s %s%s',
            implode('|', $methods),
            $uri,
            $domain !== null && $domain !== '' ? ' @ '.$domain : '',
        );
    }
}
