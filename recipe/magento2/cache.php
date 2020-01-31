<?php

/**
 * Magento 2.3.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2020 August Ash (https://www.augustash.com)
 * @license   MIT
 */

namespace Deployer;

desc('Check Magento cache status');
task('magento:cache:status', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} {{verbose}} cache:status');
    });
});

desc('Clean Magento cache storage');
task('magento:cache:clean', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} {{verbose}} cache:clean');
    });
});

desc('Flush Magento cache storage');
task('magento:cache:flush', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} {{verbose}} cache:flush');
    });
});

desc('Enable Magento cache');
task('magento:cache:enable', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} {{verbose}} cache:enable');
    });
});

desc('Disable Magento cache');
task('magento:cache:disable', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} {{verbose}} cache:disable');
    });
});
