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
require_once 'magento2/playwright.php';

/**
 * Events.
 *
 * Runs the local Playwright gate before anything connects to a server. Require this file after
 * magento-2.php, which defines the deploy tasks hooked here.
 *
 * The gate is hooked before the first task of "deploy" and "deploy:artifact" rather than before the
 * tasks themselves. Deployer prepends before-hooks, so the last one registered runs first; hooking
 * "deploy" directly would run Playwright ahead of the PHPUnit gate whenever magento-phpunit.php is
 * required first. Hooking the first child keeps the order PHPUnit, Playwright, deploy:info in either
 * require order.
 */
before('deploy:prepare', 'deploy:playwright');
before('artifact:prepare', 'deploy:playwright');
