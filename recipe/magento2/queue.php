<?php

/**
 * Magento 2.4.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2022 August Ash (https://www.augustash.com)
 * @license   MIT
 */

namespace Deployer;

desc('Terminate Supervisor');
task('magento:supervisor:remove', function () {
    run("pkill -f supervisord");
})->onRoles('app');

desc('Terminate Magento Message Consumers');
task('magento:consumers:remove', function () {
    run("pkill -f queue:consumers:start");
    run('rm -f {{current_path}}/{{magento_dir}}/var/*.pid');
})->onRoles('app');

desc('Start Supervisor');
task('magento:supervisor:start', function () {
    run("{{bin/python}} {{bin/supervisord}} -c {{supervisor_config}}");
})->onRoles('app');
