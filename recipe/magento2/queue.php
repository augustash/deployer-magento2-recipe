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
set('bin/pkill', function () {
    return which('pkill');
});
set('bin/python', function () {
    return which('python');
});
set('bin/supervisord', function () {
    return which('supervisord');
});
set('supervisor_config', '${HOME}/supervisord/supervisord.conf');

/**
 * Tasks.
 */
desc('Terminate Magento Message Consumers');
task('magento:queue:consumers:remove', function () {
    run('{{bin/pkill}} -f queue:consumers:start || true');
})->select('role=app');

desc('Terminate Supervisor');
task('magento:queue:supervisor:remove', function () {
    run('{{bin/pkill}} -f supervisord || true');
})->select('role=app');

desc('Start Supervisor');
task('magento:queue:supervisor:start', function () {
    if (test('[ -f {{supervisor_config}} ]')) {
        run('{{bin/python}} {{bin/supervisord}} -c {{supervisor_config}}');
    }
})->select('role=app');
