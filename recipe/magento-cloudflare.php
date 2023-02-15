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
require_once 'magento2/cloudflare.php';

/**
 * Binary locations.
 */
set('bin/curl', '/bin/curl');

/**
 * Default settings.
 */
set('cloudflare_key', null);
set('cloudflare_zone', null);

/**
 * Tasks.
 */
after('deploy:magento', 'cloudflare:cache:flush');
