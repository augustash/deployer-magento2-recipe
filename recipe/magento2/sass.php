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
 * Binary locations.
 */
set('bin/gulp', '${HOME}/.npm-packages/bin/gulp');
set('bin/npm', '/usr/bin/env npm');

/**
 * Tasks.
 */
desc('Deploy processed Carbon Sass into CSS');
task('magento:carbon:deploy', function () {
    within('{{release_or_current_path}}/{{magento_root}}', function () {
        run('{{bin/gulp}} build');
    });
})->select('role=app');

desc('Install Carbon Sass packages');
task('magento:carbon:install', function () {
    within('{{release_or_current_path}}/{{magento_root}}', function () {
        run('{{bin/npm}} install');
    });
})->select('role=app');

desc('Setup the Sass preprocessor');
task('magento:carbon:setup', function () {
    if (test('[ ! -f {{bin/gulp}} ]')) {
        within('{{release_or_current_path}}/{{magento_root}}', function () {
            run('{{bin/npm}} install -g gulp-cli');
        });
    }
})->select('role=app');
