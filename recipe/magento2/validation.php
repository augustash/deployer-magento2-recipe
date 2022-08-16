<?php

/**
 * Magento 2.4.x Deployer Recipe
 *
 * Provides a Deployer-based series of recipes to properly deploy Magento 2.4+.
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright 2022 August Ash, Inc. (https://www.augustash.com)
 */

namespace Deployer;

use Deployer\Exception\Exception;

desc('Verify ability to deploy Magento');
task('magento:deploy:verify', function () {
    if (test('[ ! -f {{release_path}}/{{magento_dir}}/app/etc/config.php ]')) {
        throw new Exception(
            'The repository is missing `app/etc/config.php`. Please install the application and retry!'
        );
    }

    if (!test('php -r \'$cfg = include "{{release_path}}/{{magento_dir}}/app/etc/env.php"; exit((int)!isset($cfg["install"]["date"]));\'')) {
        throw new Exception(
            'No environment configuration could be found. Please configure `app/etc/env.php` and retry!'
        );
    }
});
