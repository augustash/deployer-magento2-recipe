<?php

/**
 * Magento 2.3.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2020 August Ash (https://www.augustash.com)
 * @license   MIT
 */

namespace Deployer;

desc('Show allowed indexers');
task('magento:indexer:info', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} indexer:info {{verbose}}');
    });
});

desc('Show status of all indexers');
task('magento:indexer:status', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} indexer:status {{verbose}}');
    });
});

desc('Re-index data by all indexers');
task('magento:indexer:reindex', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} indexer:reindex {{verbose}}');
    });
});
