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

desc('Terminate Supervisor');
task('magento:supervisor:remove', function () {
    try {
        run("pkill -f supervisord");
    } catch (\Deployer\Exception\RuntimeException $e) {
        writeln('No supervisord process found');
    }
})->select('role=app');

desc('Terminate Magento Message Consumers');
task('magento:consumers:remove', function () {
    try {
        run("pkill -f queue:consumers:start");
        run('rm -f {{current_path}}/{{magento_dir}}/var/*.pid');
    } catch (\Deployer\Exception\RuntimeException $e) {
        writeln('No consumer processes found');
    }
})->select('role=app');

desc('Start Supervisor');
task('magento:supervisor:start', function () {
    run("{{bin/python}} {{bin/supervisord}} -c {{supervisor_config}}");
})->select('role=app');
