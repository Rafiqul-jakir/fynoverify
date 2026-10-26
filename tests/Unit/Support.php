<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Tests\Unit;

use Rafiquljakir\FynoVerify\Config\Settings;
use Rafiquljakir\FynoVerify\DTOs\RouteRecord;
use Rafiquljakir\FynoVerify\Planning\TestPlanBuilder;
use Rafiquljakir\FynoVerify\Support\RouteIdentifier;

/**
 * Small builders shared by the unit tests.
 */
final class Support
{
    /**
     * @param  list<string>  $methods
     * @param  list<string>  $middleware
     * @param  list<string>  $parameters
     */
    public static function route(string $id, string $uri = 'api/x', array $methods = ['GET', 'HEAD'], array $middleware = [], array $parameters = [], ?string $name = null): RouteRecord
    {
        return new RouteRecord($id, $name, $methods, null, $uri, 'Closure', $middleware, $parameters, []);
    }

    /**
     * @param  array<string, string>  $covered
     * @param  array<string, string>  $ignored
     * @param  list<string>  $include
     * @param  list<string>  $exclude
     */
    public static function settings(array $covered = [], array $ignored = [], array $include = ['api/*'], array $exclude = [], bool $failStale = true, bool $failEmpty = true): Settings
    {
        return new Settings($include, $exclude, $covered, $ignored, $failStale, $failEmpty, TestPlanBuilder::CATEGORIES, [], 8, null);
    }

    public static function fallbackId(string $uri): string
    {
        return RouteIdentifier::for(null, ['GET', 'HEAD'], null, $uri);
    }

    public static function tempDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fynoverify-unit-'.bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);

        return $dir;
    }

    public static function deleteTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::deleteTree($path.DIRECTORY_SEPARATOR.$entry);
            }
        }

        @rmdir($path);
    }
}
