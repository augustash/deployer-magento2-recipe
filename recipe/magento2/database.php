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

/**
 * Settings.
 */
set('bin/magento', '{{bin/php}} -f {{release_or_current_path}}/{{magento_root}}bin/magento');

/**
 * Tasks.
 */
desc('Check Magento if database upgrade needed');
task('magento:database:needs_upgrade', function () {
    within('{{release_or_current_path}}', function () {
        $upgradeNeeded = false;
        try {
            run('{{bin/magento}} setup:db:status');
        } catch (RunException $e) {
            if ($e->getExitCode() !== 2) {
                throw $e;
            }
            $upgradeNeeded = true;
        }
        return $upgradeNeeded;
    });
})->select('role=app');

desc('Upgrade Magento database');
task('magento:database:upgrade', function () {
    within('{{release_or_current_path}}', function () {
        run('{{bin/magento}} setup:upgrade --keep-generated --no-interaction');
    });
})->select('role=app');

desc('Upgrade Magento database fixtures');
task('magento:database:upgrade:data', function () {
    within('{{release_or_current_path}}', function () {
        run('{{bin/magento}} setup:db-data:upgrade --no-interaction');
    });
})->select('role=app');

desc('Upgrade Magento data schema');
task('magento:database:upgrade:schema', function () {
    within('{{release_or_current_path}}', function () {
        run('{{bin/magento}} setup:db-schema:upgrade --no-interaction');
    });
})->select('role=app');
