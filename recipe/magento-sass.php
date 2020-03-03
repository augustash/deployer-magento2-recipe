<?php

/**
 * Magento 2.3.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2020 August Ash (https://www.augustash.com)
 * @license   MIT
 */

namespace Deployer;

require_once 'magento2/sass.php';

/**
 * Binary locations.
 */
set('bin/gulp', '${HOME}/.npm-packages/bin/gulp');
set('bin/npm', '/usr/bin/env npm');
set('bin/yarn', '${HOME}/.npm-packages/bin/yarn');

/**
 * Tasks.
 */
before('magento:deploy:mode:production', 'magento:setup:gulp');
before('magento:setup:static-content:deploy', 'magento:setup:gulp:deploy');
