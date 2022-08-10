<?php

/**
 * Magento 2.4.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2022 August Ash (https://www.augustash.com)
 * @license   MIT
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