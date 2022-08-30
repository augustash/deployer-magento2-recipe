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

desc('Run Sass preprocessor setup');
task('magento:setup:gulp', function () {
    if (test('[ ! -f {{bin/gulp}} ]')) {
        within('{{release_path}}', function () {
            run('mkdir -p ${HOME}/.npm-packages');
            run('{{bin/npm}} config set prefix ${HOME}/.npm-packages');
            run('{{bin/npm}} install -g gulp-cli');
        });
    }
});

desc('Install Carbon Sass packages');
task('magento:setup:carbon:install', function () {
    within('{{release_path}}/{{magento_dir}}', function () {
        run('{{bin/npm}} install');
    });
});

desc('Deploy processed Carbon Sass into CSS');
task('magento:setup:carbon:deploy', function () {
    within('{{release_path}}/{{magento_dir}}', function () {
        run('{{bin/gulp}} build');
    });
});
