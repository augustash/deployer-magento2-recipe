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

desc('Show allowed indexers');
task('magento:indexer:info', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} indexer:info');
    });
});

desc('Show status of all indexers');
task('magento:indexer:status', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} indexer:status');
    });
});

desc('Re-index data by all indexers');
task('magento:indexer:reindex', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} indexer:reindex');
    });
});
