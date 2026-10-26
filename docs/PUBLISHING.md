# Publishing to Packagist

Package: `rafiquljakir/fynoverify` · Repository: https://github.com/Rafiqul-jakir/fynoverify

## Before you submit

1. **Run the checks locally.** The code was written without PHP available, so it has never been executed.
   ```bash
   composer install
   composer validate --strict
   composer test
   composer lint      # `composer format` fixes style
   composer analyse
   ```
   Fix any failure first. If a Laravel/PHP combination fails, narrow the `require` constraints in `composer.json` to what passes.
2. **Push everything** to the default branch (`main`). Make sure `composer.json` and `LICENSE` are at the repository root and `vendor/` and `composer.lock` are not committed (`.gitignore` covers this).
3. **Do not add a `version` field** to `composer.json`. Packagist reads versions from Git tags.
4. Confirm the README and `docs/SECURITY.md` say what you want publicly.

## Tag a pre-release

Use an alpha tag until the test suite has passed in CI:

```bash
git tag -a v0.1.0-alpha.1 -m "First alpha"
git push origin v0.1.0-alpha.1
```

Semantic versioning applies: `v0.1.0` after tests pass, then patch/minor bumps. Users on the default `stable` minimum-stability will not receive alpha tags unless they ask for them.

## Submit

1. Sign in at https://packagist.org with your GitHub account.
2. Open **Submit**, paste `https://github.com/Rafiqul-jakir/fynoverify`, and submit. Packagist reads the name `rafiquljakir/fynoverify` from `composer.json`.
3. Enable auto-updates: connect the GitHub integration from your Packagist profile, or add the Packagist webhook under the repository's **Settings > Webhooks**, so new tags appear automatically.

## After it is listed

```bash
composer require --dev rafiquljakir/fynoverify
```

Update the README and `docs/INSTALLATION.md` to remove the GitHub `repositories` block, and add a Packagist badge if you like.

## Naming note

The Composer vendor is `rafiquljakir`, and the PHP namespace is `Rafiquljakir\FynoVerify`. Renaming either later is a breaking change for users.
