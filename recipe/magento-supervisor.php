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
 * phpcs:disable Magento2.Security.IncludeFile.FoundIncludeFile
 */
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
after('magento:crontab:disable', 'magento:queue:supervisor:remove');
after('magento:queue:supervisor:remove', 'magento:queue:consumers:remove');
after('deploy:magento', 'magento:queue:supervisor:start');
