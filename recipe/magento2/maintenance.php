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
 * Settings.
 */
set('bin/magento', '{{bin/php}} -f {{release_or_current_path}}/{{magento_root}}bin/magento');

/**
 * Tasks.
 */
desc('Set maintenance mode exempt IPs');
task('magento:maintenance:allow-ips', function () {
    within('{{release_or_current_path}}', function () {
        $ip = get('maintenance_ips');
        if (!$ip) {
            $ip = ask('Enter the IP to allow: ');
        }
        if ($ip) {
            run('{{bin/magento}} maintenance:allow-ips ' . $ip);
        }
    });
})->select('role=app');

desc('Disable maintenance mode');
task('magento:maintenance:disable', function () {
    run('{{bin/magento}} maintenance:disable');

    if (test('[ -f {{release_path}}/var/.maintenance.flag ]')) {
        within('{{release_path}}/var', function () {
            info('Removing .maintenance.flag file');
            run('rm .maintenance.flag');
        });
    }
})->select('role=app');

desc('Enable maintenance mode');
task('magento:maintenance:enable', function () {
    within('{{release_or_current_path}}', function () {
        run('{{bin/magento}} maintenance:enable');
    });
})->select('role=app');

desc('Check if maintenance mode is active');
task('magento:maintenance:is_active', function () {
    within('{{release_or_current_path}}', function () {
        $maintenanceOutput = run('{{bin/magento}} maintenance:status');
        return \strpos($maintenanceOutput, 'maintenance mode is active') !== false;
    });
})->select('role=app');

desc('Display maintenance status');
task('magento:maintenance:status', function () {
    within('{{release_or_current_path}}', function () {
        run('{{bin/magento}} maintenance:status');
    });
})->select('role=app');
