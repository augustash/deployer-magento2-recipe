<?php

/**
 * Deployer Recipe for Magento 2.4 Deployments
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2023 August Ash (https://www.augustash.com)
 */

declare(strict_types=1);

namespace Deployer;

desc('Re-index data by all indexers');
task('magento:indexer:reindex', function () {
    within('{{release_or_current_path}}', function () {
        run('{{bin/magento}} indexer:reindex --no-interaction');
    });
})->select('role=app');

desc('Show status of all indexers');
task('magento:indexer:status', function () {
    within('{{release_or_current_path}}', function () {
        run('{{bin/magento}} indexer:status');
    });
})->select('role=app');
