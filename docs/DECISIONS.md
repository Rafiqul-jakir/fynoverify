# Decisions

| Decision | Choice and reason |
| --- | --- |
| Identity | Display `fynoVerify`; Composer `rafiquljakir/fynoverify`; namespace `Rafiquljakir\FynoVerify`; config key/file `fynoVerify`; publish tag `fynoVerify-config`. The source prompt had stray spaces in every identifier and contradicted itself; these are the cleaned values. |
| Supported matrix | PHP `^8.2`, Laravel (illuminate/*) `^11.0\|^12.0`, Testbench `^9\|^10`. **Declared but unverified**: no PHP/Composer was available when written. CI defines the matrix. Trim the constraints to what CI proves. |
| Report schema | `1.0`, no timestamps in canonical output (deterministic). |
| Route ID | Route name, else `METHODS uri[ @ domain]`. Duplicate IDs are `configuration-error`. |
| Middleware labels | As declared on the route; groups not expanded. Plan signals are heuristics on these labels. |
| Empty `include_uri_patterns` | Treated as an error: neither silently widening nor emptying scope. |
| Error classes | Structural config errors -> exit 2. Per-route declaration problems -> `configuration-error` status, exit 1. |
| Exit code 3 | Added for unexpected internal errors so they are never confused with an audit failure. |
| `--output` | Project-relative only; extension must match format; denylist; `--force` to replace. Absolute paths unsupported (simplest secure choice). |
| Testing approach | Orchestra Testbench with a per-test temporary project root (via `setBasePath`) instead of a bundled demo app. No separate workbench application exists yet. |
| Plan categories | Kept to 11 with at most one suggestion per category per route; capped at 8 per route. |
| Not implemented | `fyno:*` helpers, scaffolding, AI, dashboards (see ROADMAP). |
