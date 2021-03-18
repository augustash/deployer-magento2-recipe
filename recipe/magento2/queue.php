<?php

/**
 * Magento 2.3.x/2.4.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2021 August Ash (https://www.augustash.com)
 * @license   MIT
 */

namespace Deployer;

desc('Terminate Magento Message Consumers');
task('magento:consumers:remove', function () {
    run("ps -ef | grep 'queue:consumers:start' | grep -v grep | awk '{print $2}' | xargs -r kill -9");
    run('rm -f {{current_path}}/{{magento_dir}}/var/*.pid');
})->onRoles('app');
