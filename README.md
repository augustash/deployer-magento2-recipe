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
