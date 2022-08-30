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
