<?php

/**
 * Magento 2.3.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2020 August Ash (https://www.augustash.com)
 * @license   MIT
 */

namespace Deployer;

desc('Generate Composer HTTP-Basic Auth');
task('magento:composer:auth_config', function () {
    within('{{release_path}}', function () {
        $composerAuthConfig = get('magento_composer_auth_config', []);
        foreach ($composerAuthConfig as $authConfig) {
            $host = $authConfig['host'] ?? false;
            $user = $authConfig['user'] ?? false;
            $pass = $authConfig['pass'] ?? false;
            $value = \sprintf('http-basic.%s %s %s', $host, $user, $pass);

            if ($host && $user && $pass) {
                run('{{bin/composer}} {{verbose}} --working-dir={{release_path}}/{{magento_dir}} -q config ' . $value);
            }
        }
    });
});

desc('Generate Composer Autoloader');
task('magento:composer:autoload', function () {
    within('{{release_path}}', function () {
        run('{{bin/composer}} {{verbose}} --working-dir={{release_path}}/{{magento_dir}} dump-autoload -o --apcu');
    });
});

desc('Run Composer install');
task('magento:composer:install', function () {
    within('{{release_path}}', function () {
        $options = get('magento_composer_options', '--no-progress --no-dev --prefer-dist --no-interaction');
        $isProduction = get('magento_deploy_production', true);

        if ($isProduction === true) {
            $options .= ' --optimize-autoloader';
        }

        run('{{bin/composer}} {{verbose}} --working-dir={{release_path}}/{{magento_dir}} install ' . $options);
    });
});
