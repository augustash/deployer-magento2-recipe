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

require_once 'magento2/queue.php';

/**
 * Binary locations.
 */
set('bin/python', '/usr/bin/python3');
set('bin/supervisord', '${HOME}/supervisord/supervisord');

/**
 * Config location.
 */
set('supervisor_config', '${HOME}/supervisord/supervisord.conf');

/**
 * Tasks.
 */
after('magento:crontab:disable', 'magento:supervisor:remove');
after('magento:supervisor:remove', 'magento:consumers:remove');
after('deploy:magento', 'magento:supervisor:start');
