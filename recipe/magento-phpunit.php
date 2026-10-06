<?php

/**
 * Deployer Recipe for Magento 2.4 Deployments
 *
 * @author    Josh Johnson <josh@augustash.com>
 * @copyright Copyright (c) 2026 August Ash (https://www.augustash.com)
 */

declare(strict_types=1);

namespace Deployer;

/**
 * phpcs:disable Magento2.Security.IncludeFile.FoundIncludeFile
 */
require_once 'magento2/phpunit.php';

/**
 * Events.
 *
 * Runs the local PHPUnit gate before anything connects to a server. Require this file after
 * magento-2.php, which defines the deploy tasks hooked here.
 */
before('deploy', 'deploy:phpunit');
before('deploy:artifact', 'deploy:phpunit');
