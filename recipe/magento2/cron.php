<?php

/**
 * Deployer Recipe for Magento 2.4 Deployments
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2023 August Ash (https://www.augustash.com)
 */

declare(strict_types=1);

namespace Deployer;

desc('Enable Magento Cron');
task('magento:crontab:enable', function () {
    run('crontab -l |sed "/cron:run/s/^#//" | crontab -');
})->select('role=app');

desc('Disable Magento Cron');
task('magento:crontab:disable', function () {
    run('crontab -l |sed "/cron:run/s!^!#!" | crontab -');
})->select('role=app');
