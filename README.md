# Magento 2.4.x Deployer Recipe

![https://www.augustash.com](http://augustash.s3.amazonaws.com/logos/ash-inline-color-500.png)

**This recipe is not currently aimed at public consumption. It exists primarily for internal August Ash use.**

Piggy-backing on the excellent Deployer PHP tool, this recipe makes it easy to deploy Magento 2.4.x+ to your servers. This assumes a release/symlink strategy.

## Installation

Well, first you need to have [Deployer installed](https://deployer.org/docs/installation.html). After that's done, install the Magento recipe:

```bash
composer require augustash/deployer-magento2-recipe
```

## Usage

At this point you've got all the dependencies, now you need to create a project specific deployment file. The deployment will require a main instructions file and then host definitions. Generally I would suggest keeping your host info in a separate file. Create a `deploy.php` file your project's root directory. Here is a sample:

```php
<?php

/**
 * Deployer Recipe for Magento 2.4 Deployments
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2023 August Ash (https://www.augustash.com)
 */

declare(strict_types=1);

namespace Deployer;

/**
 * phpcs:disable Magento2.Security.IncludeFile.FoundIncludeFile
 */
require_once __DIR__ . '/src/vendor/augustash/deployer-magento2-recipe/recipe/magento-2.php';

/**
 * Project Settings.
 */
set('bin/composer', '~/.local/bin/composer');
set('bin/n98-magerun2', '~/.local/bin/n98-magerun2');
set('repository', 'git@github.com:augustash/example.com.git');

/**
 * Files.
 */
add('magento_override_files', [
    'app/etc/logrotate.conf',
    'pub/.htaccess',
    'pub/.user.ini',
]);

/**
 * Inventory.
 */
import('deploy/hosts.yml');
```

Create a `hosts.yml` file that will contain information about your deployment targets. Here is a sample containing a production and staging server:

```yaml
hosts:
  .base: &base
    cloudflare_key:
    deploy_path: /home/{{http_user}}/code/{{stage}}
    git_ssh_command: ssh -o StrictHostKeyChecking=no
    magento_composer_auth_config:
      - host: repo.magento.com
        user: MAGENTO_USER_TOKEN # Client's user/public token
        pass: MAGENTO_PASSWORD_TOKEN # Client's password/secret token
      - host: augustash.repo.repman.io
        user: token
        pass: AAI_REPMAN_TOKEN
    magento_deploy_production: true

  staging:
    <<: *base
    branch: develop
    cloudflare_zone:
    hostname: STAGING_HOSTNAME
    http_group: STAGING_HTTP_GROUP
    http_user: STAGING_HTTP_USER
    labels:
      role: app
      stage: staging
    remote_user: STAGING_SSH_USER
    stage: staging

  production:
    <<: *base
    branch: master
    cloudflare_zone:
    hostname: PRODUCTION_HOSTNAME
    http_group: PRODUCTION_HTTP_GROUP
    http_user: PRODUCTION_HTTP_USER
    labels:
      role: app
      stage: production
    remote_user: PRODUCTION_SSH_USER
    stage: production
```

### Include Supervisor

If the project is using RabbitMQ & Supervisor, you can include some additional configuration and tasks by adding the following to your `deploy.php` file:

```php
require_once __DIR__ . '/src/vendor/augustash/deployer-magento2-recipe/recipe/magento-supervisor.php';
```

### Include the PHPUnit deploy gate

To run the project's unit tests before every deploy, add the following to your `deploy.php` file, **after** `magento-2.php`:

```php
require_once __DIR__ . '/src/vendor/augustash/deployer-magento2-recipe/recipe/magento-phpunit.php';
```

This hooks `deploy:phpunit` before `deploy` and `deploy:artifact`. The task runs only local commands, before Deployer connects to any server. By default it runs PHPUnit inside DDEV, so DDEV must be running and the project's dev dependencies must be installed. It runs one module at a time and stops the deploy if any module fails. A failure doesn't run `deploy:failed`, so no server is contacted and the deploy lock is left alone. You can also run it on its own with `dep deploy:phpunit <stage>`.

The gate finds every `Test/Unit` directory under `phpunit_test_paths`. It skips directories under `phpunit_exclude_paths` and, by default, modules that aren't enabled in `app/etc/config.php`.

| Setting | Default | Purpose |
|---|---|---|
| `phpunit_test_paths` | `['app/code', 'local-src', 'vendor/augustash/*']` | Where to look for `Test/Unit` directories (globs allowed, relative to `magento_root`) |
| `phpunit_exclude_paths` | `[]` | Paths or globs to skip, e.g. third-party modules installed in `app/code` |
| `phpunit_enabled_modules_only` | `true` | Only test modules enabled in `app/etc/config.php` |
| `phpunit_report` | `summary` | `summary`: one line per module, with details only for failing tests. `tests`: every test, then a summary table. |
| `phpunit_bin` | `vendor/bin/phpunit` | PHPUnit binary |
| `phpunit_config` | `dev/tests/unit/phpunit.xml.dist` | PHPUnit config. Each run starts in this file's directory. |
| `phpunit_options` | `['--no-coverage']` | Extra PHPUnit options |
| `phpunit_wrapper` | `ddev exec --dir /var/www/html/<magento_root>/<config dir>` | Command prefix. Set it to `''` to run on host PHP. |
| `phpunit_timeout` | `null` (no limit) | Timeout in seconds, per module |
| `skip_tests` | `false` | Emergency bypass, e.g. `-o skip_tests=true`. Production asks for confirmation, and `-n` aborts. |

Set these per host in `hosts.yml`, or for one run with `-o`. With `-o`, give lists comma-separated, e.g. `-o phpunit_exclude_paths=app/code/Amasty,app/code/Acme`. `-o` values can't contain `=`. Deployer's `--no-hooks` and `--start-from` options skip the gate.

The gate also warns when the local checkout differs from the deploy target, or when tracked files have uncommitted changes. The tests run against your local working tree, not the ref the server will pull.

### Include the Playwright deploy gate

To run the project's Playwright end-to-end tests before every deploy, add the following to your `deploy.php` file, **after** `magento-2.php`:

```php
require_once __DIR__ . '/src/vendor/augustash/deployer-magento2-recipe/recipe/magento-playwright.php';
```

The gate hooks `deploy:playwright` before `deploy:prepare` and `artifact:prepare`, the first steps of `deploy` and `deploy:artifact`. It doesn't hook `deploy` itself: Deployer runs later-registered `before()` hooks first, so a hook on `deploy` would run Playwright ahead of the PHPUnit gate whenever `magento-phpunit.php` is required first. Hooking the first step means `deploy:phpunit` always runs first, then `deploy:playwright`, whatever the require order. The task runs only local commands, before Deployer connects to any server. A failure doesn't run `deploy:failed`, so no server is contacted and the deploy lock is left alone. You can also run it on its own with `dep deploy:playwright <stage>`.

Prerequisites:

- DDEV is running.
- The `ddev-magento-playwright` add-on is installed at a version with the `--ci` mode (>= 0.2.0). The gate checks `ddev help playwright` and stops with an update hint otherwise.
- `PLAYWRIGHT_THEME_DIRS` is set in DDEV (e.g. `Streichers/HyvaCspFrontend`), unless you set `playwright_themes`.
- Each theme's Playwright `.env` is configured and points at the local DDEV site.

The gate runs `ddev playwright test --ci` once per theme. In CI mode no browser opens and no report server starts, so nothing waits for input. It runs every theme, then stops the deploy if any failed. A theme fails when Playwright exits non-zero, the JSON report is missing or invalid, a test fails, or the run has errors outside tests (e.g. the site is down). A theme that runs no tests only gets a warning.

The tests run against the local DDEV site exactly as it is. The gate doesn't build the site or refresh its database, so a stale or different local site can make tests pass or fail spuriously. The seeders write to the local database on every run.

By default the gate runs the full suite on the Chromium project only (the seed and setup projects run as dependencies). Expect several minutes. To choose which tests run, use these three settings:

- `playwright_grep`: run only tests whose title matches one of these patterns.
- `playwright_grep_invert`: skip tests that match one of these patterns.
- `playwright_test_files`: run only these spec files.

Patterns are regexes matched against the full test title, which includes its tags, so tags like `@hot` or `@checkout` work directly. Several patterns are combined into one, so a test is included or excluded if it matches any of them. The seed and setup projects always run, whatever the filters. For example, in `hosts.yml`:

```yaml
  staging:
    <<: *base
    playwright_grep:
      - '@hot'
    playwright_grep_invert:
      - '@coupon-code'
      - 'Change_password'
    playwright_test_files:
      - base-tests/checkout.spec.ts
      - tests/checkout-po.spec.ts
```

Or for one run: `-o playwright_grep=@hot -o playwright_grep_invert=@coupon-code,@accessibility`.

| Setting | Default | Purpose |
|---|---|---|
| `playwright_themes` | `[]` | Themes as `<vendor>/<theme>`. Empty reads `PLAYWRIGHT_THEME_DIRS` from DDEV. |
| `playwright_projects` | `['chromium']` | Playwright projects to run |
| `playwright_grep` | `[]` | Only run tests whose title matches one of these regexes or tags (`--grep`) |
| `playwright_grep_invert` | `[]` | Skip tests whose title matches one of these regexes or tags (`--grep-invert`) |
| `playwright_test_files` | `[]` | Spec files or path patterns, relative to the theme's `web/playwright` folder |
| `playwright_options` | `[]` | Extra arguments passed to `playwright test` |
| `playwright_timeout` | `null` (no limit) | Time limit in seconds, per theme |
| `playwright_report` | `summary` | `summary`: one line per theme, with details only for failing tests. `tests`: every test of every theme. |
| `skip_playwright` | `false` | Emergency bypass, e.g. `-o skip_playwright=true`. `skip_tests=true` skips it too. Production asks for confirmation, and `-n` aborts. |

`playwright_timeout` becomes Playwright's `--global-timeout`, so Playwright stops itself and still writes its report. The local process gets 120 seconds more as a backstop. If that hard timeout is hit, the gate kills any leftover `playwright test` in the web container so it can't keep writing to the database.

Set these per host in `hosts.yml`, or for one run with `-o`. With `-o`, give lists comma-separated, e.g. `-o playwright_projects=chromium,firefox`. `-o` values can't contain `=`, so something like `playwright_options=--workers=2`, or a pattern containing `=` or `,`, must go in `hosts.yml`. Patterns also can't contain `{{`, which Deployer treats as a placeholder. Deployer's `--no-hooks` and `--start-from` options skip the gate.

Like the PHPUnit gate, it also warns when the local checkout differs from the deploy target, or when tracked files have uncommitted changes.

## Development

```bash
composer install
composer test
```
