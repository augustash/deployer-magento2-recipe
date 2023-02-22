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
 * Binary locations.
 */
set('bin/composer', '/bin/composer');

/**
 * Tasks.
 */
desc('Generate Composer HTTP-Basic Auth');
task('magento:composer:auth_config', function () {
    within('{{release_or_current_path}}/{{magento_root}}', function () {
        $composerAuthConfig = get('magento_composer_auth_config', []);
        foreach ($composerAuthConfig as $authConfig) {
            $host = $authConfig['host'] ?? false;
            $user = $authConfig['user'] ?? false;
            $pass = $authConfig['pass'] ?? false;
            $value = \sprintf('http-basic.%s %s %s', $host, $user, $pass);

            if ($host && $user && $pass) {
                run('{{bin/composer}} --working-dir={{release_or_current_path}}/{{magento_root}} -q config ' . $value);
            }
        }
    });
})->select('role=app');

desc('Dump optimized Composer autoload files');
task('magento:composer:autoload', function () {
    within('{{release_or_current_path}}/{{magento_root}}', function () {
        run('{{bin/composer}} --working-dir={{release_or_current_path}}/{{magento_root}} dump-autoload --optimize --apcu --no-dev');
    });
})->select('role=app');

desc('Run Composer install');
task('magento:composer:install', function () {
    within('{{release_or_current_path}}/{{magento_root}}', function () {
        $options = \implode(' ', get('magento_composer_options', []));
        run('{{bin/composer}} --working-dir={{release_or_current_path}}/{{magento_root}} install ' . $options . ' 2>&1');
    });
})->select('role=app');
