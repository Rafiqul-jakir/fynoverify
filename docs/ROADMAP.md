# Roadmap

## Exists today (prototype)

`fyno:scan`, `fyno:coverage`, `fyno:plan`, `fyno:doctor` (see CHANGELOG; not yet executed in CI).

## Ideas (not implemented)

- A PHPUnit/Pest helper API for access-control regression tests. The test author would supply principals, factories and expected statuses; no assumption about a `User` model.
- Human-reviewed test scaffolding with clear TODO markers, never overwriting existing tests, never asserting unconditional truths.
- Better static signals for authentication/authorization (still not a verdict).
- Optional, opt-in, clearly marked LLM-assisted suggestions, disabled by default, with data minimization and redaction.
- Adapters for other frameworks, only after validating demand.
- Hosted team reporting/history, only after product-market fit.

None of these are present in the current code.
