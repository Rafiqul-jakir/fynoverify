# Configuration

File: `config/fynoVerify.php` (config key `fynoVerify`, publish tag `fynoVerify-config`). All keys are optional and validated at runtime; errors name the offending key.

## Route IDs

- Named routes use their exact name, e.g. `api.documents.show`.
- Unnamed routes use `"<METHODS joined by |> <uri>"`, e.g. `GET|HEAD api/ping`, or `POST api/x @ tenant.example.com` for domain routes.
- If two routes share an ID, every affected in-scope route is reported as `configuration-error`; give them unique names.

The same IDs are used by `scan`, `coverage` and `plan`.

## URI patterns

Patterns are globs applied to the normalized URI (no leading/trailing slash; the root route is `/`). `*` matches any characters including `/`. Everything else is literal and matching is case-sensitive. Use `php artisan fyno:scan` to see exact URIs.

**Order:** include patterns apply first, then exclude patterns remove routes from that set. Routes not in scope are still listed (by ID) in coverage reports so the effective scope is visible.

## `coverage`

| Key | Type | Default | Behavior |
| --- | --- | --- | --- |
| `include_uri_patterns` | list of strings | `['api/*']` | Routes matching any pattern are in scope. Must contain at least one pattern (an empty list is an error). |
| `exclude_uri_patterns` | list of strings | `[]` | Removes routes from scope after inclusion. |
| `covered_routes` | map `route ID => path` | `[]` | Test file offered as evidence. Path must be project-relative, no `..`, an existing readable regular file inside the project root. Contents are never read or executed. |
| `ignored_routes` | map `route ID => reason` | `[]` | Documented exemption. The reason must be non-blank. |
| `fail_on_stale_declarations` | bool | `true` | Exit `1` when a declared ID matches no registered route. |
| `fail_on_empty_scope` | bool | `true` | Exit `1` when the scope matches no routes. |

Per-route problems (blank path/reason, a route declared as both covered and ignored, duplicate IDs) produce the route status `configuration-error` and exit code `1`. Structural problems (wrong types, bad patterns) produce exit code `2`.

Symlinks: accepted only if the fully resolved target stays inside the project root.

## `plan`

| Key | Type | Default | Behavior |
| --- | --- | --- | --- |
| `enabled_suggestions` | list of category names | all | Categories: `guest-access`, `access-intent`, `authorization`, `ownership`, `validation`, `not-found`, `state-change`, `http-method`, `rate-limit`, `mass-assignment`, `api-contract`. |
| `protected_uri_patterns` | list of strings | `[]` | URIs you consider protected; treated like an authentication signal. |
| `max_scenarios_per_route` | int >= 1 | `8` | Keeps the highest-priority suggestions. |
| `max_routes` | int >= 1 or `null` | `null` | Caps routes in a plan; omitted routes are reported. |

Unknown keys produce warnings, not errors.
