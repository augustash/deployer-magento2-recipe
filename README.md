# Magento 2.3.x/2.4.x Deployer Recipe

![https://www.augustash.com](http://augustash.s3.amazonaws.com/logos/ash-inline-color-500.png)

**This recipe is not currently aimed at public consumption. It exists primarily for internal August Ash use.**

Piggy-backing on the excellent Deployer PHP tool, this recipe makes it easy to deploy Magento 2.3.x+ to your servers. This assumes a release/symlink strategy.

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
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2021 August Ash (https://www.augustash.com)
 */

namespace Deployer;

require_once __DIR__ . '/src/vendor/augustash/deployer-magento2-recipe/recipe/magento-2.3.php';

/**
 * Settings
 */
set('application', 'example.com');
set('repository', 'git@github.com:augustash/example.com.git');

/**
 * Inventory.
 */
inventory('deploy/hosts.yml');
```

Create a `hosts.yml` file that will contain information about your deployment targets. Here is a sample containing a production and staging server:

```yaml
.base: &base
  hostname: example.com
  user: deploy_user
  deploy_path: /home/deploy_user/code/{{stage}}
  forwardAgent: true
  multiplexing: true
  sshOptions:
    StrictHostKeyChecking: no
  roles:
    - app
    - db
  magento_composer_auth_config:
    - host: repo.magento.com
      user: <public_auth_key>
      pass: <private_auth_key>

test:
  <<: *base
  stage: staging
  branch: develop
  magento_deploy_production: false

live:
  <<: *base
  stage: production
  branch: master
  magento_deploy_production: true
```

### Include Sass Compilation

If the project is using our Sass process, you can include some additional configuration and tasks by adding the following to your `deploy.php` file:

```php
require_once __DIR__ . '/src/vendor/augustash/deployer-magento2-recipe/recipe/magento-sass.php';
```

or

```php
require_once __DIR__ . '/src/vendor/augustash/deployer-magento2-recipe/recipe/magento-carbon.php';
```

## Notifications

This recipe comes with the ability to send a deployment notification message to a Slack channel. It is a very simple Web Hook implementation but should get the job done.

### Configuration

`slack_webhook` - Define the Slack incoming webhook URL, *required*

`slack_title` - The Slack message title, defaults to `{{application}}`

`slack_color_deploy` - The color attachment for a deployment message

`slack_color_failure` - The color attachment for a deployment failure message

`slack_color_success` - The color attachment for a deployment successful message

`slack_text_deploy` - The deployment message template, Markdown is supported

`slack_text_failure` - The failure message template, Markdown is supported

`slack_text_success` - The successful message template, Markdown is supported

### Sample Usage

You must define the `slack_webhook` variable and then activate at least one notification hook for the messages to work. Here is a sample configuration:

```php
/**
 * Settings
 */
set('slack_webhook', 'https://hooks.slack.com/services/...');

/**
 * Notifications.
 */
before('deploy', 'slack:notify');
after('success', 'slack:notify:success');
after('deploy:failed', 'slack:notify:failure');
```
