<?php

/**
 * Deployer Recipe for Magento 2.4 Deployments
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2023 August Ash (https://www.augustash.com)
 */

declare(strict_types=1);

namespace Deployer;

use Deployer\Exception\RunException;

desc('Export store data to shared config files');
task('magento:configuration:export', function () {
    within('{{release_or_current_path}}', function () {
        run('{{bin/magento}} app:config:dump scopes');
    });
})->select('role=app');

desc('Import Magento configuration from filesystem');
task('magento:configuration:import', function () {
    within('{{release_or_current_path}}', function () {
        if (get('magento:configuration:needs_import')) {
            run('{{bin/magento}} app:config:import --no-interaction');
        }
    });
})->select('role=app');

desc('Check Magento if configuration import needed');
task('magento:configuration:needs_import', function () {
    within('{{release_or_current_path}}', function () {
        $importNeeded = false;
        try {
            run('{{bin/magento}} app:config:status');
        } catch (RunException $e) {
            if ($e->getExitCode() !== 2) {
                throw $e;
            }
            $importNeeded = true;
        }
        return $importNeeded;
    });
})->select('role=app');

desc('Check Magento configuration status');
task('magento:configuration:status', function () {
    within('{{release_or_current_path}}', function () {
        run('{{bin/magento}} app:config:status');
    });
})->select('role=app');
