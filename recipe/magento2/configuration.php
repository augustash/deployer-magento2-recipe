<?php

/**
 * Magento 2.3.x/2.4.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2021 August Ash (https://www.augustash.com)
 * @license   MIT
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
