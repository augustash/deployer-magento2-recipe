<?php

/**
 * Magento 2.4.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2022 August Ash (https://www.augustash.com)
 * @license   MIT
 */

namespace Deployer;

require_once 'magento2/sass.php';

/**
 * Binary locations.
 */
set('bin/gulp', '${HOME}/.npm-packages/bin/gulp');
set('bin/npm', '/usr/bin/env npm');
set('bin/yarn', '${HOME}/.npm-packages/bin/yarn');

/**
 * Magento settings.
 */
set('shared_files', [
    '{{magento_dir}}/gulp-config.json',
    '{{magento_dir}}/app/etc/env.php',
    '{{magento_dir}}/dev/tools/gulp/configs/themes.local.js',
    '{{magento_dir}}/var/.maintenance.ip',
    '{{magento_dir}}/var/.setup_cronjob_status',
    '{{magento_dir}}/var/.update_cronjob_status',
]);

/**
 * Tasks.
 */
before('magento:setup:static-content:deploy', 'magento:setup:carbon:deploy');
after('magento:deploy:patches:files', 'magento:setup:gulp');
after('magento:setup:gulp', 'magento:setup:carbon:install');
