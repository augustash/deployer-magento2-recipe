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

require_once 'magento2/sass.php';

/**
 * Binary locations.
 */
set('bin/gulp', '${HOME}/.npm-packages/bin/gulp');
set('bin/npm', '/usr/bin/env npm');

/**
 * Magento settings.
 */
add('shared_files', [
    '{{magento_dir}}/gulp-config.json',
    '{{magento_dir}}/dev/tools/gulp/configs/themes.local.js',
]);

/**
 * Tasks.
 */
before('magento:setup:static-content:deploy', 'magento:setup:carbon:deploy');
after('magento:deploy:patches:files', 'magento:setup:gulp');
after('magento:setup:gulp', 'magento:setup:carbon:install');
