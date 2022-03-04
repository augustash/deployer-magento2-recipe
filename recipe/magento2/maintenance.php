<?php

/**
 * Magento 2.4.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2022 August Ash (https://www.augustash.com)
 * @license   MIT
 */

namespace Deployer;

desc('Display maintenance status');
task('magento:maintenance:status', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} maintenance:status');
    });
});

desc('Enable maintenance mode');
task('magento:maintenance:enable', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} maintenance:enable');
    });
});

desc('Disable maintenance mode');
task('magento:maintenance:disable', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} maintenance:disable');
    });
});

desc('Set maintenance mode exempt IPs');
task('magento:maintenance:allow-ips', function () {
    within('{{release_path}}', function () {
        $ip = ask('Enter the IP to allow: ');
        if ($ip) {
            run('{{bin/magento}} maintenance:allow-ips ' . $ip);
        }
    });
});

desc('Enable maintenance during deploy');
task('magento:deploy:maintenance', function () {
    invoke('magento:maintenance:enable');

    if (test('[ -f {{deploy_path}}/current/{{magento_dir}}/bin/magento ]')) {
        within('{{current_path}}', function () {
            run('{{bin/magento}} maintenance:enable');
        });
    }
});
