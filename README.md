# fynoVerify: Developer Documentation

Package: `rafiquljakir/fynoverify` · Namespace: `Rafiquljakir\FynoVerify` · Repository: <https://github.com/Rafiqul-jakir/fynoverify> · License: MIT

> **Status: prototype (0.1.x).** The code was written without PHP, Composer or a Laravel install available, so it has not yet been executed. The supported versions (PHP `^8.2`, Laravel 11 and 12) are *declared*, not verified. Run `composer install && composer test` before relying on anything here, and narrow the version constraints to what actually passes.

---

## Contents

1. [What fynoVerify is](#1-what-fynoverify-is)
2. [Installation](#2-installation)
3. [Quick start](#3-quick-start)
4. [Core concepts](#4-core-concepts)
5. [Command reference](#5-command-reference)
6. [Configuration reference](#6-configuration-reference)
7. [Report schemas](#7-report-schemas)
8. [Exit codes and CI](#8-exit-codes-and-ci)
9. [Output path policy](#9-output-path-policy)
10. [Security and privacy](#10-security-and-privacy)
11. [Architecture](#11-architecture)
12. [Extending the package](#12-extending-the-package)
13. [Testing the package](#13-testing-the-package)
14. [Releasing](#14-releasing)
15. [Troubleshooting and FAQ](#15-troubleshooting-and-faq)
16. [Limitations and roadmap](#16-limitations-and-roadmap)

---

## 1. What fynoVerify is

fynoVerify is a Laravel development and CI tool for API and authorization regression testing. It does three jobs:

1. **Inventory** the routes your application registers (`fyno:scan`).
2. **Audit declared test evidence**: check that each in-scope route is mapped to an existing test file or has a written exemption (`fyno:coverage`).
3. **Suggest tests** for each in-scope route, based only on route metadata and your configuration (`fyno:plan`).

A fourth command, `fyno:doctor`, diagnoses installation and configuration problems.

### What it does not do

| It does not | Why it matters |
| --- | --- |
| Run your tests | A mapped test file may be weak, skipped or failing. Always run your real suite separately. |
| Read or judge test file contents | Evidence files are only checked for existence and readability. |
| Measure line or branch coverage | The metric is **declared test-evidence coverage**, not code coverage. |
| Declare a route secure or vulnerable | It issues no security verdict. Plan items are *suggested scenarios*. |
| Make network calls, send telemetry, or call AI services | None exist in the MVP. |
| Execute controllers, run migrations, or touch the database | It is read-only. |

---

## 2. Installation

Requirements: PHP 8.2+, Composer 2, Laravel 11 or 12 (declared).

Until the package is listed on Packagist, install it from GitHub. Add a repository entry to your application's `composer.json`:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/Rafiqul-jakir/fynoverify.git" }
    ]
}
```

```bash
composer require --dev rafiquljakir/fynoverify:dev-main
```

Once it is on Packagist, remove the `repositories` entry and run:

```bash
composer require --dev rafiquljakir/fynoverify
```

For local development of the package itself, use a path repository:

```json
{
    "repositories": [
        { "type": "path", "url": "../fynoverify", "options": { "symlink": true } }
    ]
}
```

### Verify discovery

The service provider is auto-discovered through `extra.laravel.providers`. Confirm:

```bash
php artisan list fyno
```

You should see `fyno:scan`, `fyno:coverage`, `fyno:plan` and `fyno:doctor`. Then run `php artisan fyno:doctor`.

### Publish the config (optional)

```bash
php artisan vendor:publish --tag=fynoVerify-config
```

This copies `config/fynoVerify.php` into your app. Without it the package works on defaults, and a partially published config is fine because omitted keys fall back to defaults.

> Because the package is a `require-dev` dependency, the commands are absent after `composer install --no-dev`.

---

## 3. Quick start

```bash
# 1. See your API routes and copy their IDs.
php artisan fyno:scan --filter='api/*'

# 2. Edit config/fynoVerify.php: map IDs to test files or exemptions.

# 3. Audit declared evidence.
php artisan fyno:coverage

# 4. Get a reviewable list of tests to write.
php artisan fyno:plan --format=markdown --output=storage/app/fynoverify/test-plan.md

# 5. Run the real tests (separate step; fynoVerify never does this).
php artisan test
```

Example mapping:

```php
// config/fynoVerify.php
return [
    'coverage' => [
        'covered_routes' => [
            'api.documents.index' => 'tests/Feature/Api/DocumentIndexTest.php',
        ],
        'ignored_routes' => [
            'api.health' => 'Public liveness endpoint with no sensitive application data.',
        ],
    ],
];
```

The IDs shown are examples. Use the IDs your own application reports. The packaged default config contains no fictional mappings.

---

## 4. Core concepts

### 4.1 Route IDs

Every command identifies a route the same way, and your config keys must match.

| Route | ID | Example |
| --- | --- | --- |
| Named | Exact route name | `api.documents.show` |
| Unnamed | `METHODS uri` (methods joined by `\|`) | `GET\|HEAD api/ping` |
| Unnamed, with a domain constraint | `METHODS uri @ domain` | `POST api/x @ tenant.example.com` |

Notes:

- `METHODS` is the full sorted method list Laravel registers, so a `GET` route is `GET|HEAD`.
- Unnamed IDs change when the method or URI changes. Naming routes keeps your mappings stable.
- If two routes share an ID, every affected in-scope route becomes `configuration-error`, and `fyno:scan` prints a warning. Give each route a unique `->name()`.

### 4.2 URI normalization and patterns

URIs are normalized before matching: no leading or trailing slash, and the root route is `/`.

Patterns are globs. `*` matches any characters, including `/`. Everything else is literal and matching is case-sensitive.

| Pattern | Matches | Does not match |
| --- | --- | --- |
| `api/*` | `api/users`, `api/users/{user}` | `web/users`, `API/users` |
| `api/v1.0/*` | `api/v1.0/x` | `api/v1x0/x` (dots are literal) |
| `/` | the root route | everything else |
| `*` | every route, including `/` | none |

A leading slash in a pattern is normalized away (`/api/*` equals `api/*`). Patterns must be non-empty strings with no control characters.

### 4.3 Scope

For `fyno:coverage` and `fyno:plan`, a route is **in scope** when it matches at least one `include_uri_patterns` entry **and** no `exclude_uri_patterns` entry. Include runs first, then exclude removes routes from that set.

Routes outside the scope are not audited, but coverage reports list their IDs under `scope.not_in_scope_route_ids`, so the effective scope is always visible.

### 4.4 Coverage statuses

| Status | Meaning |
| --- | --- |
| `covered` | Mapped to a file that exists, is readable, is a regular file, and resolves inside the project root. |
| `ignored` | Has an entry in `ignored_routes` with a non-blank reason. |
| `uncovered` | Neither a mapping nor an exemption exists. |
| `missing-evidence` | A mapping exists but the file is missing, unreadable, not a regular file, absolute, contains `..`, or resolves outside the project. |
| `configuration-error` | Blank path, blank reason, declared as both covered and ignored, or the route ID is ambiguous (shared by several routes). |

Evaluation order for each in-scope route: ambiguous ID, then declaration problem, then covered, then ignored, then uncovered.

**Stale declarations** are IDs in `covered_routes` or `ignored_routes` that match no registered route. They are reported separately from route statuses (`stale_declarations`). A declaration for a route that exists but is outside the scope is not stale; it produces a warning and has no effect.

### 4.5 Declared test-evidence coverage

`covered` means "you pointed this route at a file that exists". It does not mean the file contains relevant assertions, that tests pass, that the route is secure, or that branches are covered. Every coverage report carries this disclaimer.

---

## 5. Command reference

All commands return a Laravel exit code (see [section 8](#8-exit-codes-and-ci)). Machine-readable output (`json`, `markdown`) written to stdout is never mixed with decorations. Error and diagnostic messages go to stderr.

### 5.1 `fyno:scan`

Read-only route inventory.

```bash
php artisan fyno:scan [--format=table|json|markdown] [--filter=GLOB] [--output=PATH] [--force]
```

| Option | Default | Description |
| --- | --- | --- |
| `--format` | `table` | Output format. Anything else exits `2`. |
| `--filter` | none | Glob on the normalized URI. An invalid pattern exits `2`. |
| `--output` | none | Write a report file instead of printing. Requires `json` or `markdown`; combining it with `table` exits `2`. |
| `--force` | off | Allow replacing an existing `--output` file. |

Examples:

```bash
php artisan fyno:scan
php artisan fyno:scan --filter='api/*' --format=json
php artisan fyno:scan --format=markdown --output=storage/app/fynoverify/routes.md
```

**Route record fields**

| Field | Type | Description |
| --- | --- | --- |
| `id` | string | Route ID (see 4.1). |
| `name` | string or null | Route name. |
| `methods` | string[] | Sorted, unique, upper-case verbs. |
| `domain` | string or null | Domain constraint. |
| `uri` | string | Normalized URI. Optional parameters keep their `?` (`fvt/opt/{id?}`). |
| `action` | string | Laravel's action label, such as `App\Http\Controllers\X@show` or `Closure`. |
| `middleware` | string[] | Labels declared on the route, de-duplicated, in Laravel's order. Middleware group names such as `api` are not expanded. Closures appear as `Closure`. |
| `parameters` | string[] | Parameter names without `?`. |
| `where_constraints` | object | Parameter name to constraint pattern, sorted by name. Serialized as `{}` when empty. |

Behavior:

- Sorted by URI, then methods, then domain, then ID, using byte-wise comparison, so output is identical between runs.
- Handles the root route, unnamed routes, optional parameters, constraints, `HEAD`/`OPTIONS`, domain routes and routes without middleware.
- An empty result prints "No routes matched" (table) or an empty `routes` list, and exits `0`.
- Table output truncates long values. JSON and Markdown never truncate.

### 5.2 `fyno:coverage`

Audits declared test evidence for in-scope routes.

```bash
php artisan fyno:coverage [--format=table|json|markdown] [--output=PATH] [--force]
```

Flow:

1. Validate configuration. Structural errors produce a report with `configuration_errors` and exit `2`.
2. Inspect routes and partition them into in-scope and not-in-scope.
3. Classify each in-scope route, check stale declarations, and compute failure reasons.
4. Render. A report is always produced, even for a failed audit, and `--output` still writes it.

The audit fails with exit `1` when any of these hold:

- an in-scope route is `uncovered`, `missing-evidence` or `configuration-error`;
- stale declarations exist and `fail_on_stale_declarations` is `true`;
- the scope matched no routes and `fail_on_empty_scope` is `true`.

Evidence files are only checked for existence, readability and being a regular file inside the project root. They are never opened for their contents and never executed.

### 5.3 `fyno:plan`

Generates reviewable test suggestions for in-scope routes.

```bash
php artisan fyno:plan [--format=table|json|markdown] [--output=PATH] [--force]
```

Invalid configuration prints each error to stderr and exits `2`.

Each scenario is always `status: "suggested"`, `heuristic: true`, `requires_human_review: true`, and lists what your team must supply (`requires_from_team`). Nothing is ever labelled passed, failed or vulnerable. Every plan carries: *"These are metadata-based test suggestions, not executed tests or a security verdict."*

**Signals** derived from a route:

| Signal | Definition |
| --- | --- |
| Authentication signal | A middleware label matching `auth`, `auth.basic`, `can`, `role`, `permission`, `abilities` or `ability` (optionally followed by `:...`), or a label containing `Authenticate` or `Authorize`, or the URI matching `plan.protected_uri_patterns`. |
| Privilege signal | A label matching `can`, `role`, `permission`, `abilities`, `ability`, or containing `Authorize`; or an `admin` URI segment. |
| Throttle signal | A label starting with `throttle` or containing `ThrottleRequests`. |
| Write route | Methods include `POST`, `PUT`, `PATCH` or `DELETE`. |
| Has parameters | The route has at least one parameter. |

**Scenario catalog** (output order is by priority, then this order):

| Category | Suggested when | Priority |
| --- | --- | --- |
| `guest-access` | Authentication signal present | high |
| `access-intent` | No authentication signal visible. This is not evidence the route is public. | medium |
| `authorization` | Authentication signal present | high with a privilege signal, otherwise medium |
| `ownership` | Route has parameters | high for write routes, otherwise medium |
| `validation` | Write route | medium |
| `not-found` | Route has parameters | medium |
| `state-change` | Write route | high for `PUT`/`PATCH`/`DELETE`, otherwise medium |
| `http-method` | Write route | low |
| `rate-limit` | Throttle signal present | medium |
| `mass-assignment` | `PUT` or `PATCH` (a hypothesis) | medium |
| `api-contract` | URI is `api` or starts with `api/` | low |

Suggestions are de-duplicated per `category|title`, filtered by `plan.enabled_suggestions`, ordered, and capped at `plan.max_scenarios_per_route`. `plan.max_routes` limits the number of routes; omitted routes are reported in `summary.routes_omitted` and `warnings`.

### 5.4 `fyno:doctor`

Read-only diagnostics.

```bash
php artisan fyno:doctor [--format=table|json]
```

| Check | Passes when | Otherwise |
| --- | --- | --- |
| PHP version | PHP is 8.2 or newer | fail |
| Laravel version | Major version is 11 or 12 | warning |
| Project base path | `base_path()` is a directory | fail |
| Configuration | Config loads and validates | fail (errors) or warning (unknown keys) |
| Route registration | At least one route can be inspected | warning if none |
| Evidence paths | Declared paths are project-relative with no `..` | fail; warning if none declared yet or the config is invalid |
| Test runner files | `phpunit.xml`, `phpunit.xml.dist`, `tests/Pest.php` or `tests/` found (heuristic) | warning |

Each check has a `status` (`pass`, `warning`, `fail`), a `message`, and an optional `recommendation`. The command exits `1` only if a check fails. An exception inside a check becomes a failing check rather than a crash. It never prints environment values, credentials or connection strings, and it never modifies files. Evidence *existence* is checked by `fyno:coverage`, not by doctor.

---

## 6. Configuration reference

File: `config/fynoVerify.php`. Config key: `fynoVerify`. Publish tag: `fynoVerify-config`. Every key is optional and validated at runtime; messages name the exact key.

### `coverage`

| Key | Type | Default | Behavior |
| --- | --- | --- | --- |
| `include_uri_patterns` | list of strings | `['api/*']` | Routes matching any pattern are in scope. An empty list is an error, so scope never silently widens or empties. |
| `exclude_uri_patterns` | list of strings | `[]` | Removes routes from scope after inclusion. |
| `covered_routes` | map of route ID to path | `[]` | Project-relative path to an existing test file. |
| `ignored_routes` | map of route ID to reason | `[]` | Reason must be non-blank. |
| `fail_on_stale_declarations` | bool | `true` | Exit `1` when a declared ID matches no route. |
| `fail_on_empty_scope` | bool | `true` | Exit `1` when no routes are in scope. |

### `plan`

| Key | Type | Default | Behavior |
| --- | --- | --- | --- |
| `enabled_suggestions` | list of category names | all eleven | Unknown names are an error. |
| `protected_uri_patterns` | list of strings | `[]` | Treated like an authentication signal. |
| `max_scenarios_per_route` | integer, 1 or more | `8` | Keeps the highest-priority suggestions. |
| `max_routes` | integer 1 or more, or `null` | `null` | Caps routes in a plan. |

### Validation levels

| Problem | Result |
| --- | --- |
| Wrong type, invalid pattern, list where a map is expected, empty include list, unknown plan category, bad integer | **Error.** `fyno:coverage` exits `2` and still emits a report with `configuration_errors`. `fyno:plan` exits `2`. `fyno:doctor` reports a failing check. |
| Blank evidence path, blank exemption reason, route declared as both covered and ignored | **Route-level `configuration-error`** with exit `1`. |
| Unknown key | **Warning** only. |

### Pitfalls

- **Dotted route IDs and `config()->set()`.** Setting `config()->set('fynoVerify.coverage.covered_routes.api.users.index', ...)` creates nested arrays because dots are path separators. Assign the whole map instead, as a PHP array literal in the config file.
- **Numeric-looking keys.** PHP turns the key `'123'` into an integer. Integer keys in the maps are reported as errors ("found list entry").
- **Merging.** The provider uses Laravel's shallow `mergeConfigFrom`. The validator applies defaults per key, so a partial `coverage` array does not lose other defaults.

### Evidence path rules

- Must be project-relative: no leading `/` or `\`, no drive letter such as `C:`.
- No `..` segments. Both `/` and `\` separators are accepted.
- Must resolve to an existing, readable, regular file.
- The real path (after resolving symlinks) must stay inside the project root. A symlink pointing outside is rejected; a symlink pointing inside is accepted.

---

## 7. Report schemas

JSON reports use UTF-8, unescaped slashes and a trailing newline. Canonical output has **no timestamps**, so identical inputs give identical output and diffs are meaningful.

`schema_version` is `"1.0"`. Adding optional fields keeps the version. Renaming, removing or retyping fields bumps it.

Common fields: `tool` (`fynoVerify`), `schema_version`, `report_type` (`scan`, `coverage`, `plan`, `doctor`), `disclaimer` (all except doctor), `warnings` (scan, coverage, plan).

### 7.1 Scan

```json
{
  "tool": "fynoVerify",
  "schema_version": "1.0",
  "report_type": "scan",
  "disclaimer": "This inventory lists route metadata as registered in the application. It does not show how routes behave or whether they are protected.",
  "filter": "api/*",
  "route_count": 1,
  "routes": [
    {
      "id": "api.documents.show",
      "name": "api.documents.show",
      "methods": ["GET", "HEAD"],
      "domain": null,
      "uri": "api/documents/{document}",
      "action": "App\\Http\\Controllers\\DocumentController@show",
      "middleware": ["api", "auth:sanctum"],
      "parameters": ["document"],
      "where_constraints": {}
    }
  ],
  "warnings": []
}
```

`filter` is `null` when no filter was given.

### 7.2 Coverage

```json
{
  "tool": "fynoVerify",
  "schema_version": "1.0",
  "report_type": "coverage",
  "metric": "declared test-evidence coverage",
  "disclaimer": "This report shows declared test-evidence coverage: ...",
  "passed": false,
  "exit_code": 1,
  "scope": {
    "include_uri_patterns": ["api/*"],
    "exclude_uri_patterns": [],
    "route_count_total": 12,
    "route_count_in_scope": 5,
    "route_count_not_in_scope": 7,
    "not_in_scope_route_ids": ["web.home"]
  },
  "summary": {
    "covered": 3,
    "ignored": 1,
    "uncovered": 1,
    "missing-evidence": 0,
    "configuration-error": 0,
    "stale_declarations": 0
  },
  "routes": [
    {
      "id": "api.a",
      "name": "api.a",
      "methods": ["GET", "HEAD"],
      "uri": "api/a",
      "status": "covered",
      "evidence": "tests/Feature/ATest.php",
      "reason": null,
      "message": null
    }
  ],
  "stale_declarations": [{ "id": "api.removed", "declared_as": "covered" }],
  "configuration_errors": [],
  "failure_reasons": ["1 in-scope route(s) have status \"uncovered\"."],
  "warnings": []
}
```

- `declared_as` is `covered`, `ignored` or `covered+ignored`.
- `evidence` is set for `covered` and `missing-evidence`; `reason` for `ignored`; `message` explains non-passing statuses.
- With invalid configuration: `scope` and `summary` are `null`, `routes` is empty, `exit_code` is `2`, `passed` is `false`, and `configuration_errors` lists each problem.

### 7.3 Plan

```json
{
  "tool": "fynoVerify",
  "schema_version": "1.0",
  "report_type": "plan",
  "disclaimer": "These are metadata-based test suggestions, not executed tests or a security verdict.",
  "scope": {
    "include_uri_patterns": ["api/*"],
    "exclude_uri_patterns": [],
    "route_count_total": 12,
    "route_count_in_scope": 5
  },
  "summary": {
    "routes_in_plan": 5,
    "routes_omitted": 0,
    "scenario_count": 21,
    "by_priority": { "high": 7, "medium": 11, "low": 3 }
  },
  "routes": [
    {
      "id": "api.documents.show",
      "name": "api.documents.show",
      "methods": ["GET", "HEAD"],
      "uri": "api/documents/{document}",
      "middleware": ["api", "auth:sanctum"],
      "action": "App\\Http\\Controllers\\DocumentController@show",
      "scenarios": [
        {
          "category": "guest-access",
          "title": "Unauthenticated request is handled as intended",
          "status": "suggested",
          "priority": "high",
          "priority_rule": "High because an authentication-related signal is present.",
          "rationale": "...",
          "basis": "route-metadata",
          "heuristic": true,
          "requires_human_review": true,
          "requires_from_team": ["authentication guard in use", "expected unauthenticated status code or contract"]
        }
      ]
    }
  ],
  "warnings": []
}
```

### 7.4 Doctor

```json
{
  "tool": "fynoVerify",
  "schema_version": "1.0",
  "report_type": "doctor",
  "passed": true,
  "checks": [
    { "name": "PHP version", "status": "pass", "message": "PHP 8.3.0.", "recommendation": null }
  ]
}
```

### 7.5 Markdown output

Scan, coverage and plan can be rendered as Markdown. All route metadata is escaped: `\ | ` * _ [ ]` are backslash-escaped, `< > &` become HTML entities, and control characters (including newlines and ANSI escapes) collapse to spaces. A hostile route name cannot add table rows, inject links or emit raw HTML.

### 7.6 Parsing example

```bash
php artisan fyno:coverage --format=json \
  | jq -r '.routes[] | select(.status != "covered" and .status != "ignored") | "\(.status)\t\(.id)"'
```

---

## 8. Exit codes and CI

| Code | Constant | Meaning |
| --- | --- | --- |
| `0` | `EXIT_OK` | Success, or the audit passed. |
| `1` | `EXIT_CHECK_FAILED` | `fyno:coverage` policy failure, or `fyno:doctor` has a failing check. |
| `2` | `EXIT_INVALID` | Invalid arguments or configuration, or an unsafe or unwritable `--output`. |
| `3` | `EXIT_INTERNAL` | Unexpected internal error. Never reported as success. |

With `--format=json` and no `--output`, stdout contains only the JSON document, so pipelines can parse it safely. Errors go to stderr. Passing `-v` adds a stack trace for internal errors.

### GitHub Actions

Run the audit and your tests as separate steps. A passing audit never implies tests ran.

```yaml
- run: php artisan fyno:coverage --format=json --output=storage/app/fynoverify/coverage.json --force
- run: php artisan test
- uses: actions/upload-artifact@v4
  if: always()
  with:
    name: fynoverify-coverage
    path: storage/app/fynoverify/coverage.json
```

`if: always()` uploads the report even when the audit fails. Gate merges on the exit code: `1` means fix declarations, `2` means fix configuration.

---

## 9. Output path policy

`--output` is the only way fynoVerify writes a file. Rules enforced by `ReportWriter`:

| Rule | Detail |
| --- | --- |
| Project-relative only | Absolute paths (`/x`, `\x`, `C:\x`) and `..` segments are rejected. |
| Extension matches format | `.json` for JSON; `.md` or `.markdown` for Markdown. |
| Protected top-level directories | `vendor`, `node_modules`, `.git`, `.github`, `app`, `config`, `routes`, `database`, `src`, `tests`, `bootstrap`. |
| Protected file names (anywhere) | `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `readme.md`, `changelog.md`, `license.md`. |
| No symlinks | The target is never a symlink, and the nearest existing parent must resolve inside the project root. |
| No silent overwrite | An existing file is replaced only with `--force`. |
| Directories | Missing parent directories are created (mode `0755`). A file in the way is an error. |

Every violation exits `2` with a message naming the problem. A good default location is `storage/app/fynoverify/<name>.json`. The protected-directory list is a safeguard, not a security boundary.

---

## 10. Security and privacy

- No network requests, telemetry, source upload or AI calls. A unit test (`NoNetworkOrExecutionTest`) scans `src/` for network and process-execution primitives, `eval`, and similar calls.
- No controller execution, HTTP probes, migrations or database access.
- Test files are only checked for existence and readability, never read or executed (a feature test plants a sentinel file to prove this).
- Route names, URIs, actions, middleware labels, config strings and paths are treated as untrusted and escaped for the terminal, Markdown and JSON.
- `fyno:doctor` prints only versions, counts and project-relative paths. A feature test confirms environment secrets and `app.key` never appear.
- Generated reports describe your API surface. Keep them internal and out of public artifacts.
- fynoVerify offers no security guarantee. Use wording such as "potential risk signal", "suggested scenario" and "requires application-specific review".

Report suspected vulnerabilities privately through GitHub's **Security** tab ("Report a vulnerability") rather than a public issue.

---

## 11. Architecture

### 11.1 Layout

```text
fynoverify/
├── composer.json
├── config/fynoVerify.php
├── src/
│   ├── FynoVerify.php                  constants: name, schema version, exit codes, disclaimers
│   ├── FynoVerifyServiceProvider.php   merges config, publishes tag, registers commands
│   ├── Commands/
│   │   ├── BaseCommand.php             guard, stderr output, option helpers, document emission
│   │   ├── ScanCommand.php
│   │   ├── CoverageCommand.php
│   │   ├── PlanCommand.php
│   │   └── DoctorCommand.php
│   ├── Config/
│   │   ├── ConfigurationValidator.php  raw config -> Settings + errors + warnings
│   │   ├── Settings.php                validated, fully defaulted value object
│   │   └── ValidationResult.php
│   ├── Coverage/CoverageAuditor.php    statuses, stale detection, failure reasons
│   ├── DTOs/RouteRecord.php            normalized route
│   ├── Planning/TestPlanBuilder.php    signals, scenario catalog, ordering
│   ├── Reports/
│   │   ├── ScanReport.php              builds the scan report array
│   │   └── ReportRenderer.php          JSON and Markdown rendering
│   └── Support/
│       ├── RouteInspector.php          Laravel Router -> RouteRecord[]
│       ├── RouteIdentifier.php         ID scheme
│       ├── RouteScope.php              include/exclude partition
│       ├── PatternMatcher.php          URI normalization and glob matching
│       ├── EvidencePathResolver.php    evidence file validation
│       ├── PathSafety.php              path helpers
│       ├── ReportWriter.php            safe report file writing
│       ├── ReportWriteException.php
│       └── Text.php                    terminal and Markdown sanitizers
└── tests/{Unit,Feature}
```

### 11.2 Data flow

```text
Router --> RouteInspector --> list<RouteRecord> (sorted)
                                   |
config('fynoVerify') --> ConfigurationValidator --> Settings
                                   |
              RouteScope: in_scope / not_in_scope
               |                          |
       CoverageAuditor             TestPlanBuilder
               |                          |
        report array               report array
               \                         /
                ReportRenderer (json | markdown)  or  console table
                              |
                  stdout (raw)  or  ReportWriter (--output)
```

Reports are plain arrays built in services. Commands only parse options, call services, render and map results to exit codes.

### 11.3 Design principles

- **Thin commands, testable services.** Commands receive services through method injection on `handle()`. Core logic runs without an HTTP request.
- **Public APIs only.** `RouteInspector` uses `Router::getRoutes()` and the public `Route` methods. No reflection on framework internals.
- **Deterministic output.** Byte-wise sorting, no timestamps, `JSON_THROW_ON_ERROR`, and `JSON_INVALID_UTF8_SUBSTITUTE` so bad bytes do not abort a report.
- **Fail loudly.** `BaseCommand::guard()` converts any `Throwable` into a stderr message and exit `3`. The audit is never reported as passed after an exception.
- **Untrusted input.** All strings from routes and config pass through `Text` before reaching a terminal or Markdown.
- **Raw writes.** Documents are written with `OutputInterface::OUTPUT_RAW` so Symfony's formatter cannot interpret `<tag>`-like text inside JSON or Markdown.

### 11.4 Key classes

| Class | Responsibility |
| --- | --- |
| `RouteInspector::all()` | Builds sorted `RouteRecord`s. Uses `gatherMiddleware()` (declared labels), `parameterNames()`, `$route->wheres`, `getActionName()`. |
| `ConfigurationValidator::validate(mixed)` | Returns a `ValidationResult` with `settings` (always usable, defaults for invalid keys), `errors` and `warnings`. |
| `CoverageAuditor::audit(routes, settings, basePath)` | Returns the coverage report array. `CoverageAuditor::configurationFailure()` builds the exit-2 report. |
| `TestPlanBuilder::build()` / `scenariosFor()` | Builds the plan, or the scenario list for one route. |
| `EvidencePathResolver::check()` / `validateSyntax()` | Full filesystem check, or static syntax check only. Returns an error string or `null`. |
| `ReportWriter::write()` | Applies the output path policy and writes the file, or throws `ReportWriteException`. |
| `BaseCommand::emitDocument()` | Prints raw content, or delegates to `ReportWriter`. Returns an exit code on failure or `null` on success. |

---

## 12. Extending the package

### 12.1 Add a plan scenario category

1. Add the category name to `TestPlanBuilder::CATEGORIES`. The validator reads this constant, so `plan.enabled_suggestions` accepts it automatically.
2. In `TestPlanBuilder::scenariosFor()`, add a candidate via `$this->scenario(category, title, rationale, priority, priorityRule, basis, requires)`. Base it only on route metadata or explicit config.
3. Add the category to the default `enabled_suggestions` list in `config/fynoVerify.php`.
4. Add unit tests in `TestPlanBuilderTest` (when it appears, when it does not, its priority) and update the tables in this file.

Rules to keep: never use verdict language ("vulnerable", "passed", "failed"); always set `heuristic` and `requires_human_review`; say what the team must supply.

### 12.2 Add a config key

1. Add a default in `config/fynoVerify.php` with a comment.
2. Add the key to `COVERAGE_KEYS` or `PLAN_KEYS` in `ConfigurationValidator` (so it is not flagged unknown), validate it with the existing helpers, and add a property to `Settings`.
3. Pass it where needed and test both valid and invalid values.

### 12.3 Add an output format

Add a renderer method to `ReportRenderer`, allow the format in the commands' `formatOption([...])` list, and extend the extension rules in `ReportWriter::write()`. Escape every route-derived string through `Text`.

### 12.4 Add a command

Create a class extending `BaseCommand` with a `$signature` (`fyno:...`), a `handle()` that wraps its body in `$this->guard(...)`, use `failWith()` for user-correctable errors, and register it in `FynoVerifyServiceProvider::boot()`. Keep logic in services.

### 12.5 Future test helpers

The source is organized so a `Testing` namespace can be added without touching the CLI. Any helper must take the principal, factory, request and expected status explicitly, must not assume an `App\Models\User` or a role system, and must never produce tests that pass trivially.

---

## 13. Testing the package

```bash
composer install
composer test        # phpunit
composer lint        # pint --test (composer format to fix)
composer analyse     # larastan, level 6
```

### Layout

- `tests/Unit`: pure logic (pattern matching, IDs, path safety, report writer, text escaping, validator, auditor, plan builder, renderers, and the no-network guard).
- `tests/Feature`: real Laravel apps via Orchestra Testbench, running the commands end to end.

### Test infrastructure

`tests/TestCase.php` creates a throwaway project root under the system temp directory for each test and calls `$this->app->setBasePath()`, so evidence checks and report writes never touch real files. It also sets `include_uri_patterns` to `['fvt/*']`, and the feature tests register their routes under that prefix to stay clear of routes the Testbench skeleton registers.

| Helper | Purpose |
| --- | --- |
| `run_($command, $options)` | Runs `Artisan::call`; returns `[exitCode, output]`. |
| `runJson($command, $options)` | As above with `--format=json`; returns the decoded report. |
| `touchFile($relative)` | Creates an evidence file in the temp project. |
| `snapshot()` | Lists files with mtime and size, to prove commands are read-only. |
| `registerRoutes()` (feature base) | Registers varied routes: named, unnamed, optional parameter with constraint, multi-method, domain and root. |

Under Testbench, stderr and stdout share one buffer, so error text appears in `Artisan::output()`. In real use they are separate streams.

### What the suite covers

- Provider discovery, command registration, config defaults without publishing, and the publish tag.
- Scan: valid JSON, determinism, ordering, filtering, edge-case routes, Markdown escaping, invalid options, safe and unsafe output paths, and read-only behavior.
- Coverage: every status, stale declarations, empty scope, include/exclude order, traversal and out-of-root evidence, invalid config (exit 2 with a report), duplicate IDs, report files on failed audits, wording, and no execution of evidence files.
- Plan: coverage of in-scope routes, category rules and priorities, suggestion-only labels, no verdict words, caps and limits.
- Doctor: healthy and failing environments, secret redaction, and no mutation.

### Known gaps

The suite has not been run yet. There is no Windows path test, no real multi-version matrix run, and no end-to-end test that installs the package into a fresh Laravel application. The CI workflow (`.github/workflows/tests.yml`) defines a PHP 8.2 to 8.4 and Laravel 11 to 12 matrix, but it has not run.

---

## 14. Releasing

1. Run all checks locally (section 13) and fix failures. Narrow the `require` constraints to what passes.
2. Do **not** add a `version` field to `composer.json`; Packagist reads Git tags.
3. Update `CHANGELOG.md` (only features that exist and are tested).
4. Tag: `git tag -a v0.1.0-alpha.1 -m "First alpha" && git push origin v0.1.0-alpha.1`. Use `v0.1.0` once CI is green.
5. Submit `https://github.com/Rafiqul-jakir/fynoverify` at <https://packagist.org/packages/submit> and enable the GitHub webhook so new tags are picked up.
6. After listing, remove the GitHub `repositories` block from the README and installation docs.

Follow semantic versioning. Changing the report `schema_version`, command signatures, exit codes, config keys, route ID scheme, vendor name or namespace is a breaking change.

---

## 15. Troubleshooting and FAQ

**The `fyno:*` commands are not listed.**
Run `composer dump-autoload`, confirm the package is in `require-dev`, and that you did not install with `--no-dev`.

**`fyno:coverage` exits `2`.**
The configuration is invalid. The message names the key, for example `coverage.include_uri_patterns: expected a list of strings, got string`. `fyno:doctor` shows the same errors.

**A route shows `missing-evidence`.**
Read the route's `message`: the file does not exist, the path is absolute or contains `..`, or it resolves outside the project. Use a path relative to the project root.

**Everything is reported as a stale declaration.**
Route IDs changed. Re-run `fyno:scan` and copy the current IDs. Unnamed route IDs change with their method or URI.

**Empty scope failure.**
Your include patterns match nothing. Compare them with the `uri` column of `fyno:scan`. URIs have no leading slash, and matching is case-sensitive.

**Two routes share an ID.**
Add a unique `->name()` to each. Until then both are `configuration-error`.

**Output file refused.**
Choose a path such as `storage/app/fynoverify/report.json` with the right extension, and add `--force` to replace an existing file.

**Does a `covered` route mean it is tested?**
No. It means a file you named exists. Run your test suite to learn whether tests pass.

**Why does `fyno:plan` suggest tests for routes that have `auth` middleware?**
A middleware label is not proof of correct behavior. The suggestions ask you to verify guest, authorization and ownership behavior explicitly.

**Why is a route with no visible middleware not flagged as public?**
Global and custom middleware are invisible to route metadata, so it gets an `access-intent` suggestion ("confirm the intended policy") instead of a claim.

**Can I commit the JSON reports?**
You can, but treat them as internal documentation because they describe your API surface.

**Which Laravel versions work?**
Laravel 11 and 12 are declared. Nothing has been verified yet, so let CI show what passes.

---

## 16. Limitations and roadmap

### Current limitations

- Prototype: not yet executed, versions unverified.
- Middleware is shown as declared on the route. Global middleware and unexpanded groups (`api`, `web`) are invisible, so authentication signals are heuristics.
- Plan suggestions cannot know your roles, tenants, ownership rules or response contracts, and they list what you need to supply.
- Declared evidence is not proof of meaningful tests.
- Absolute `--output` paths are not supported.
- No timestamps in canonical output, by design.

### Not implemented (ideas only)

- A PHPUnit/Pest helper API for access-control regression tests.
- Human-reviewed test scaffolding with prominent TODOs that never overwrites existing tests.
- Richer static signals for authentication and authorization (still not a verdict).
- Optional, opt-in, clearly labelled LLM-assisted suggestions, disabled by default, with data minimization.
- Other frameworks, and hosted team reporting, if there is demand.

None of these exist in the current code.
