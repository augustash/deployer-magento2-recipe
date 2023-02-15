<?php

/**
 * Deployer Recipe for Magento 2.4 Deployments
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2023 August Ash (https://www.augustash.com)
 */

declare(strict_types=1);

namespace Deployer;

use Deployer\Exception\Exception;

/**
 * Binary locations.
 */
set('bin/n98-magerun2', '/usr/local/bin/n98-magerun2');
set('default_timeout', 300);
set('download_name', 'deployer_backup');

/**
 * Tasks.
 */
desc('Check for n98-magerun2');
task('magento:deploy:check:n98', function () {
    if (test('[ ! -f {{bin/n98-magerun2}} ]')) {
        throw new Exception('n98-magerun2 is not installed! Check the `bin/n98-magerun2` variable.');
    }
})->select('role=app');

desc('Create a database dump (not including logs, sessions)');
task('magento:database:dump-dev', function () {
    within('{{release_or_current_path}}/{{magento_root}}var', function () {
        run('{{bin/n98-magerun2}} db:dump --no-interaction --strip="@stripped @development" -c gzip $(date +%Y%m%d%H%M%S)-dev.sql.gz');
    });
})->select('role=app');

desc('Create a quick database dump');
task('magento:database:dump-quick', function () {
    within('{{release_or_current_path}}/{{magento_root}}var', function () {
        run('{{bin/n98-magerun2}} db:dump --no-interaction --strip="@stripped" -c gzip $(date +%Y%m%d%H%M%S)-quick.sql.gz');
    });
})->select('role=app');

desc('Create a full database dump');
task('magento:database:dump', function () {
    within('{{release_or_current_path}}/{{magento_root}}var', function () {
        run('{{bin/n98-magerun2}} db:dump --no-interaction -c gzip $(date +%Y%m%d%H%M%S).sql.gz');
    });
})->select('role=app');

desc('Download a full database dump');
task('magento:download:database', function () {
    within('{{release_or_current_path}}/{{magento_root}}var', function () {
        $filename = get('download_name', 'deployer_backup') . '_db.sql.gz';
        $localPath = runLocally('pwd');
        $timeout = get('default_timeout', 300);

        run('{{bin/n98-magerun2}} db:dump --no-interaction -c gzip ' . $filename);
        download(
            '{{release_or_current_path}}/{{magento_root}}var/' . $filename,
            $localPath . '/',
            [
                'timeout' => $timeout,
            ]
        );
        run('rm -f ' . $filename);

        writeln(\sprintf(
            'The downloaded backup file: %s',
            $filename
        ));
    });
})->select('role=app');

desc('Download a full media dump');
task('magento:download:media', function () {
    within('{{release_or_current_path}}/{{magento_root}}var', function () {
        $filename = get('download_name', 'deployer_backup') . '_media.zip';
        $localPath = runLocally('pwd');
        $timeout = get('default_timeout', 300);

        run('{{bin/n98-magerun2}} media:dump --no-interaction --strip ' . $filename);
        download(
            '{{release_or_current_path}}/{{magento_root}}var/' . $filename,
            $localPath . '/',
            [
                'timeout' => $timeout,
            ]
        );
        run('rm -f ' . $filename);

        writeln(\sprintf(
            'The downloaded backup file: %s',
            $filename
        ));
    });
})->select('role=app');

desc('Create a full media dump');
task('magento:media:dump', function () {
    within('{{release_or_current_path}}/{{magento_root}}var', function () {
        run('{{bin/n98-magerun2}} media:dump --no-interaction --strip $(date +%Y%m%d%H%M%S).zip');
    });
})->select('role=app');

/**
 * Events
 */
before('magento:database:dump-dev', 'magento:deploy:check:n98');
before('magento:database:dump-quick', 'magento:deploy:check:n98');
before('magento:database:dump', 'magento:deploy:check:n98');
before('magento:download:database', 'magento:deploy:check:n98');
before('magento:download:media', 'magento:deploy:check:n98');
before('magento:media:dump', 'magento:deploy:check:n98');
