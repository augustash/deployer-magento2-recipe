<?php

/**
 * Magento 2.3.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2020 August Ash (https://www.augustash.com)
 * @license   MIT
 */

namespace Deployer;

desc('Generate and install crontab for current user');
task('magento:crontab:update', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} cron:install --force');
    });
});

desc('Remove crontab for current deploy and user');
task('magento:crontab:remove', function () {
    // the crontab entry is release specific
    if (test('[ -h {{deploy_path}}/current ]')) {
        within('{{current_path}}', function () {
            run('{{bin/magento}} cron:remove');
        });
    } else {
        within('{{release_path}}', function () {
            run('{{bin/magento}} cron:remove');
        });
    }
});

desc('Enable Magento Cron');
task('magento:crontab:enable', function () {
    run('crontab -l |sed "/cron:run/s/^#//" | crontab -');
});

desc('Disable Magento Cron');
task('magento:crontab:disable', function () {
    run('crontab -l |sed "/cron:run/s!^!#!" | crontab -');
});
