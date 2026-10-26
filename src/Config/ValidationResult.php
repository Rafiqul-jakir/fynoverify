<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify\Config;

final class ValidationResult
{
    /**
     * @param  list<string>  $errors  Problems that prevent a meaningful audit.
     * @param  list<string>  $warnings  Non-fatal observations (for example unknown keys).
     */
    public function __construct(
        public readonly Settings $settings,
        public readonly array $errors,
        public readonly array $warnings,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
