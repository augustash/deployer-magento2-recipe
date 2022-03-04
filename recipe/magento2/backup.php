<?php

/**
 * Magento 2.4.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2022 August Ash (https://www.augustash.com)
 * @license   MIT
 */

namespace Deployer;

use Deployer\Exception\Exception;

desc('Create a database dump');
task('magento:dump:db', function () {
    if (test('[ ! -f {{bin/n98}} ]')) {
        throw new Exception('n98-magerun is not installed! Check the `bin/n98` variable.');
    }

    within('{{release_path}}/{{magento_dir}}/var', function () {
        run('{{bin/n98}} db:dump -s @development -c gzip $(date +%Y%m%d%H%M%S).sql.gz {{verbose}}');
    });
});

desc('Download a database dump');
task('magento:download:db', function () {
    if (test('[ ! -f {{bin/n98}} ]')) {
        throw new Exception('n98-magerun is not installed! Check the `bin/n98` variable.');
    }

    within('{{release_path}}/{{magento_dir}}/var', function () {
        $timeout = get('magento_timeout', 300);
        $localPath = runLocally('pwd');

        writeln('Creating a new database dump...');
        run('{{bin/n98}} db:dump -s @development -c gzip deployer_db_backup.sql.gz {{verbose}}');

        writeln('Downloading the database dump...');
        download(
            '{{release_path}}/{{magento_dir}}/var/deployer_db_backup.sql.gz',
            $localPath . '/',
            [
                'timeout' => $timeout,
            ]
        );
        run('rm -f deployer_db_backup.sql.gz');
    });

    writeln('Your database file is called: deployer_db_backup.sql.gz');
});

desc('Create a media dump');
task('magento:dump:media', function () {
    if (test('[ ! -f {{bin/n98}} ]')) {
        throw new Exception('n98-magerun is not installed! Check the `bin/n98` variable.');
    }

    within('{{release_path}}/{{magento_dir}}/var', function () {
        run('{{bin/n98}} media:dump --strip media-$(date +%Y%m%d%H%M%S).zip {{verbose}}');
    });
});

desc('Download a media dump');
task('magento:download:media', function () {
    if (test('[ ! -f {{bin/n98}} ]')) {
        throw new Exception('n98-magerun is not installed! Check the `bin/n98` variable.');
    }

    within('{{release_path}}/{{magento_dir}}/var', function () {
        $timeout = get('magento_timeout', 300);
        $localPath = runLocally('pwd');

        writeln('Creating a new media dump...');
        run('{{bin/n98}} media:dump --strip deployer_media_backup.zip {{verbose}}');

        writeln('Downloading the media dump...');
        download(
            '{{release_path}}/{{magento_dir}}/var/deployer_media_backup.zip',
            $localPath . '/',
            [
                'timeout' => $timeout,
            ]
        );

        run('rm -f deployer_media_backup.zip');
    });

    writeln('Your media file is called: deployer_media_backup.zip');
});
