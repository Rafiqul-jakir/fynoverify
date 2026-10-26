# Contributing

```bash
composer install
composer test
composer lint
composer analyse
```

Guidelines: keep commands thin and logic in services; add tests for expected and failure behavior; never add network calls, telemetry or process execution (a unit test guards this); do not describe declared test-evidence coverage as code coverage or as a security verdict; update docs and CHANGELOG only for behavior that is implemented and tested.
