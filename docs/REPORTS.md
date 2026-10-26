# Reports

Reports are UTF-8 JSON with unescaped slashes and a trailing newline. Canonical output is deterministic and contains no timestamps.

## Schema versioning

`schema_version` is `"1.0"`. Adding optional fields is a minor change; renaming/removing fields or changing types bumps the major version.

Common fields: `tool` (`fynoVerify`), `schema_version`, `report_type` (`scan`, `coverage`, `plan`, `doctor`), `disclaimer` (except doctor), `warnings`.

## Scan

```json
{
  "tool": "fynoVerify",
  "schema_version": "1.0",
  "report_type": "scan",
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

## Coverage

Keys: `metric` (`declared test-evidence coverage`), `passed`, `exit_code`, `scope` (patterns, counts, `not_in_scope_route_ids`), `summary` (status counts + `stale_declarations`), `routes[]` (`id`, `name`, `methods`, `uri`, `status`, `evidence`, `reason`, `message`), `stale_declarations[]`, `configuration_errors[]`, `failure_reasons[]`, `warnings[]`. For invalid configuration, `scope` and `summary` are `null` and `exit_code` is `2`.

## Plan

Keys: `scope`, `summary` (`routes_in_plan`, `routes_omitted`, `scenario_count`, `by_priority`), `routes[]` each with `scenarios[]` (`category`, `title`, `status: "suggested"`, `priority`, `priority_rule`, `rationale`, `basis`, `heuristic`, `requires_human_review`, `requires_from_team`).

## What reports do not prove

They do not show that tests exist, ran, or passed; that mapped files contain relevant assertions; that routes are protected or unprotected; or that the application is secure.
