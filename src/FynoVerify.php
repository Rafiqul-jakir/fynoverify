<?php

declare(strict_types=1);

namespace Rafiquljakir\FynoVerify;

/**
 * Central, shared constants: product identity, report schema version, exit codes and
 * the wording that every report must carry.
 */
final class FynoVerify
{
    public const NAME = 'fynoVerify';

    public const CONFIG_KEY = 'fynoVerify';

    public const SCHEMA_VERSION = '1.0';

    /** Audit passed / command succeeded. */
    public const EXIT_OK = 0;

    /** `fyno:coverage` policy failure, or `fyno:doctor` found a failing check. */
    public const EXIT_CHECK_FAILED = 1;

    /** Invalid CLI arguments, invalid configuration, or an unsafe/unwritable output path. */
    public const EXIT_INVALID = 2;

    /** Unexpected internal error. The command never reports success after one. */
    public const EXIT_INTERNAL = 3;

    public const COVERAGE_DISCLAIMER = 'This report shows declared test-evidence coverage: whether each in-scope route has a configured test-file mapping or a documented exemption. '
        .'It does not execute tests. It does not show that the mapped files contain relevant assertions, that tests pass, that routes are secure, or that all branches are covered. '
        .'Run your real test suite separately (for example: php artisan test).';

    public const PLAN_DISCLAIMER = 'These are metadata-based test suggestions, not executed tests or a security verdict.';

    public const SCAN_DISCLAIMER = 'This inventory lists route metadata as registered in the application. It does not show how routes behave or whether they are protected.';
}
