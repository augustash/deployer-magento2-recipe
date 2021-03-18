<?php

/**
 * Magento 2.3.x/2.4.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2021 August Ash (https://www.augustash.com)
 * @license   MIT
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
