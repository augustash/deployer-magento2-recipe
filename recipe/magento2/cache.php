<?php

/**
 * Deployer Recipe for Magento 2.4 Deployments
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2023 August Ash (https://www.augustash.com)
 */

declare(strict_types=1);

namespace Deployer;

use Symfony\Component\Console\Input\InputOption;

option('cache-types', null, InputOption::VALUE_OPTIONAL, 'A comma-separated list of cache types to enable or disable.');

/**
 * Tasks.
 */
desc('Clean Magento cache storage');
task('magento:cache:clean', function () {
    within('{{release_or_current_path}}', function () {
        run('{{bin/magento}} cache:clean');
    });
})->select('role=app');

desc('Enable a Magento cache');
task('magento:cache:enable', function () {
    within('{{release_or_current_path}}', function () {
        if (input()->hasOption('cache-types')) {
            $cacheTypes = \explode(',', input()->getOption('cache-types'));
            if (!empty($cacheTypes)) {
                run('{{bin/magento}} cache:enable -- ' . \implode(' ', $cacheTypes));
            }
        }
    });
})->select('role=app');

desc('Disable a Magento cache');
task('magento:cache:disable', function () {
    within('{{release_or_current_path}}', function () {
        if (input()->hasOption('cache-types')) {
            $cacheTypes = \explode(',', input()->getOption('cache-types'));
            if (!empty($cacheTypes)) {
                run('{{bin/magento}} cache:disable -- ' . \implode(' ', $cacheTypes));
            }
        }
    });
})->select('role=app');

desc('Flush Magento cache storage');
task('magento:cache:flush', function () {
    within('{{release_or_current_path}}', function () {
        run('{{bin/magento}} cache:flush');
    });
})->select('role=app');

desc('Check Magento cache status');
task('magento:cache:status', function () {
    within('{{release_or_current_path}}', function () {
        run('{{bin/magento}} cache:status');
    });
})->select('role=app');
