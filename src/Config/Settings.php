<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Config;

/**
 * Validated, fully defaulted configuration.
 */
final class Settings
{
    /**
     * @param  list<string>  $includeUriPatterns
     * @param  list<string>  $excludeUriPatterns
     * @param  array<string, string>  $coveredRoutes  Route ID => test file path (may be blank; the audit flags that per route).
     * @param  array<string, string>  $ignoredRoutes  Route ID => reason (may be blank; the audit flags that per route).
     * @param  list<string>  $planEnabledSuggestions
     * @param  list<string>  $planProtectedUriPatterns
     */
    public function __construct(
        public readonly array $includeUriPatterns,
        public readonly array $excludeUriPatterns,
        public readonly array $coveredRoutes,
        public readonly array $ignoredRoutes,
        public readonly bool $failOnStaleDeclarations,
        public readonly bool $failOnEmptyScope,
        public readonly array $planEnabledSuggestions,
        public readonly array $planProtectedUriPatterns,
        public readonly int $planMaxScenariosPerRoute,
        public readonly ?int $planMaxRoutes,
    ) {}
}
