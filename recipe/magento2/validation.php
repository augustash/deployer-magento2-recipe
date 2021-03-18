<?php

/**
 * Magento 2.3.x/2.4.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2021 August Ash (https://www.augustash.com)
 * @license   MIT
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
