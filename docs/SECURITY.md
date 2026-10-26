# Security

## Posture

fynoVerify reads route metadata and configuration, and writes only a report you request. It makes **no network requests**, sends **no telemetry**, uploads **no source**, and has **no AI dependency**. It never invokes controllers, runs migrations, probes HTTP endpoints, or reads/executes test file contents (evidence files are only checked for existence and readability).

## Threat model

Route names, URIs, action strings, middleware labels, configuration strings and paths are treated as untrusted. Mitigations: path traversal and symlink-escape checks for evidence paths and report destinations; denylist for source/config/dependency locations; no overwrite without `--force`; Markdown, terminal and JSON output escaping; `doctor` prints versions, counts and project-relative paths only.

## Reports are sensitive

A route inventory reveals your API surface. Treat generated reports like internal documentation and do not publish them.

## No security guarantee

fynoVerify provides no security verdict and cannot prove a route is protected. Use the words "potential risk signal", "suggested scenario" and "requires application-specific review".

## Reporting a vulnerability

Please do not open a public issue for a suspected vulnerability. Use GitHub private vulnerability reporting on the repository (Security tab, "Report a vulnerability") at https://github.com/Rafiqul-jakir/fynoverify/security.
