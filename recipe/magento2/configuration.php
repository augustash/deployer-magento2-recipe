<?php

/**
 * Magento 2.3.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2020 August Ash (https://www.augustash.com)
 * @license   MIT
 */

namespace Deployer;

desc('Export data to shared config files');
task('magento:configuration:export', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} {{verbose}} app:config:dump');
    });
});

desc('Import data from shared config files');
task('magento:configuration:import', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} {{verbose}} app:config:import --no-interaction');
    });
});
