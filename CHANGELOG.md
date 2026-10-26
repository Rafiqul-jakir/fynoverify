# Changelog

## Unreleased (0.1.0 prototype)

Implemented (written but **not yet executed**: no PHP/Composer was available when this was authored):

- Composer package `rafiquljakir/fynoverify` with Laravel package discovery and optional config publishing (`fynoVerify-config`).
- `fyno:scan`: route inventory (table, JSON, Markdown), glob filter, safe `--output`/`--force`.
- `fyno:coverage`: declared test-evidence audit with statuses `covered`, `ignored`, `uncovered`, `missing-evidence`, `configuration-error`, stale declaration detection and exit codes 0/1/2/3.
- `fyno:plan`: metadata-based, deterministic test suggestions (table, JSON, Markdown).
- `fyno:doctor`: read-only diagnostics (table, JSON).
- PHPUnit/Testbench test suite, GitHub Actions workflow, Pint and Larastan configuration, documentation.
