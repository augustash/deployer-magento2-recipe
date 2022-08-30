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
 * Magento Deployment
 *
 * @see https://deployer.org/docs/7.x/tasks if you need to override or create tasks.
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2022 August Ash (https://www.augustash.com)
 */

namespace Deployer;

require_once __DIR__ . '/src/vendor/augustash/deployer-magento2-recipe/recipe/magento-2.php';

/**
 * Settings
 */
set('application', 'example.com');
set('repository', 'git@github.com:augustash/example.com.git');

/**
 * Files.
 */
add('magento_patched_files', [
    '{{magento_dir}}/pub/.htaccess',
    '{{magento_dir}}/pub/.user.ini',
]);

// Use `add` to combine arrays from other recipes.
add('clear_paths', []);
add('shared_dirs', []);
add('shared_files', []);
add('writable_dirs', []);

/**
 * Inventory.
 */
import('deploy/hosts.yml');
```

Create a `hosts.yml` file that will contain information about your deployment targets. Here is a sample containing a production and staging server:

```yaml
hosts:
  .base: &base
    forward_agent: true
    git_ssh_command: ssh -o StrictHostKeyChecking=no
    ssh_multiplexing: true
    ssh_arguments:
      - '-o StrictHostKeyChecking=no'
    magento_deploy_production: true
    magento_composer_auth_config:
      - host: repo.magento.com
        user: MAGENTO_USER_TOKEN # Client's user/public token
        pass: MAGENTO_PASSWORD_TOKEN # Client's password/secret token
      - host: augustash.repo.repman.io
        user: token
        pass: AAI_REPMAN_TOKEN

  staging:
    <<: *base
    hostname: staging.example.com
    remote_user: SSH_USER_NAME
    deploy_path: /home/USER_DIRECTORY/code/{{stage}}
    http_user: USER_NAME
    http_group: USER_NAME
    labels:
      stage: staging
      role: app
    stage: staging
    branch: develop

  production:
    <<: *base
    hostname: example.com
    remote_user: SSH_USER_NAME
    deploy_path: /home/USER_DIRECTORY/code/{{stage}}
    http_user: USER_NAME
    http_group: USER_NAME
    labels:
      stage: production
      role: app
    stage: production
    branch: master
```

### Include Sass Compilation

If the project is using our Sass process, you can include some additional configuration and tasks by adding the following to your `deploy.php` file:

```php
require_once __DIR__ . '/src/vendor/augustash/deployer-magento2-recipe/recipe/magento-carbon.php';
```

### Include Supervisor

If the project is using RabbitMQ & Supervisor, you can include some additional configuration and tasks by adding the following to your `deploy.php` file:

```php
require_once __DIR__ . '/src/vendor/augustash/deployer-magento2-recipe/recipe/magento-supervisor.php';
```
