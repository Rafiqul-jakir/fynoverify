# Installation

Packagist listing is pending. Until it is live, install from GitHub or a local path.

## From GitHub

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

After a tag such as `v0.1.0` exists you can require `^0.1` instead of `dev-main`. Once listed on Packagist, drop the `repositories` block and run `composer require --dev rafiquljakir/fynoverify`.

## Local path repository

In the consuming Laravel app's `composer.json`:

```json
{
    "repositories": [{ "type": "path", "url": "../fynoverify", "options": { "symlink": true } }]
}
```

```bash
composer require --dev rafiquljakir/fynoverify:@dev
```

## Verify package discovery

```bash
php artisan list fyno
```

You should see `fyno:scan`, `fyno:coverage`, `fyno:plan` and `fyno:doctor`. Then run `php artisan fyno:doctor`.

## Publish the configuration (optional)

```bash
php artisan vendor:publish --tag=fynoVerify-config
```

This copies `config/fynoVerify.php` into your app. Without it, defaults apply. A partially published config is fine: omitted keys use defaults.

## Common problems

| Symptom | Likely cause / fix |
| --- | --- |
| Commands not listed | Run `composer dump-autoload`; check `extra.laravel.providers`; make sure the package is in `require-dev` and you are not using `--no-dev`. |
| `php` / Composer constraint errors | The package declares PHP `^8.2` and Laravel 11/12. Other combinations are untested. |
| `fyno:doctor` reports a failing *Configuration* check | The named key in `config/fynoVerify.php` has the wrong type. |
| Installed with `--no-dev` in production | Expected: the commands are not available. It is a development/CI tool. |
