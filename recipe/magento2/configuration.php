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

desc('Export data to shared config files');
task('magento:configuration:export', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} app:config:dump');
    });
});

desc('Import data from shared config files');
task('magento:configuration:import', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} app:config:import --no-interaction');
    });
});
