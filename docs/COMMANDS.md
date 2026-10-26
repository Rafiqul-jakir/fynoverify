# Commands

All commands accept `--format`. Machine-readable output (`json`, `markdown`) on stdout is never mixed with decorations; diagnostics go to stderr.

## Exit codes

| Code | Meaning |
| --- | --- |
| 0 | Success / audit passed |
| 1 | `fyno:coverage` policy failure; `fyno:doctor` failing check |
| 2 | Invalid arguments, invalid configuration, unsafe or unwritable `--output` |
| 3 | Unexpected internal error (never reported as success) |

## `fyno:scan`

```
php artisan fyno:scan [--format=table|json|markdown] [--filter=PATTERN] [--output=PATH] [--force]
```

Read-only inventory. Fields per route: `id`, `name`, `methods`, `domain`, `uri`, `action`, `middleware`, `parameters`, `where_constraints`. Middleware labels are those declared on the route (middleware *group names* such as `api` are not expanded). Sorted by URI, methods, domain, ID. An empty result exits `0`.

## `fyno:coverage`

```
php artisan fyno:coverage [--format=table|json|markdown] [--output=PATH] [--force]
```

Statuses: `covered`, `ignored`, `uncovered`, `missing-evidence`, `configuration-error`. Stale declarations are reported separately. The summary says **declared test-evidence coverage**: it does not run tests, and a mapped file may contain weak, skipped or failing tests. Run `php artisan test` separately.

## `fyno:plan`

```
php artisan fyno:plan [--format=table|json|markdown] [--output=PATH] [--force]
```

Every item is a `suggested` scenario with rationale, priority, `heuristic: true` and `requires_human_review: true`, plus what your team must supply. Disclaimer: *These are metadata-based test suggestions, not executed tests or a security verdict.*

Priority rules: `guest-access` high; `access-intent` medium; `authorization` high with a `can`/`role`/`permission` middleware or an `admin` URI segment, otherwise medium; `ownership` high for write routes with parameters, otherwise medium; `validation`, `not-found`, `rate-limit`, `mass-assignment` medium; `state-change` high for PUT/PATCH/DELETE, otherwise medium; `http-method`, `api-contract` low.

## `fyno:doctor`

```
php artisan fyno:doctor [--format=table|json]
```

Checks PHP version, Laravel version, base path, configuration, route registration, evidence-path syntax and test-runner files (heuristic). Statuses: `pass`, `warning`, `fail`. Never prints environment values; never modifies files.

## `--output` policy

Project-relative only; no `..`, no absolute paths; `.json` for JSON, `.md`/`.markdown` for Markdown; refuses `app/`, `config/`, `routes/`, `database/`, `src/`, `tests/`, `bootstrap/`, `vendor/`, `.git/`, `node_modules/` and files such as `composer.json` or `README.md`; never writes through symlinks; existing files need `--force`.

## CI

```yaml
- run: php artisan fyno:coverage --format=json --output=storage/app/fynoverify/coverage.json --force
- run: php artisan test
```
