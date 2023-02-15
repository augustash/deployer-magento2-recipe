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
    '{{magento_root}}/dev/tools/gulp/configs/themes.local.js',
    '{{magento_root}}/gulp-config.json',
]);

/**
 * Tasks.
 */
after('magento:carbon:setup', 'magento:carbon:install');
after('magento:override:files', 'magento:carbon:setup');
before('magento:setup:static-content:deploy', 'magento:carbon:deploy');
