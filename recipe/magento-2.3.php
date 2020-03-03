<?php

/**
 * Magento 2.3.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2020 August Ash (https://www.augustash.com)
 * @license   MIT
 */

namespace Deployer;

require_once 'recipe/common.php';
require_once 'magento2/backup.php';
require_once 'magento2/build.php';
require_once 'magento2/cache.php';
require_once 'magento2/composer.php';
require_once 'magento2/configuration.php';
require_once 'magento2/cron.php';
require_once 'magento2/database.php';
require_once 'magento2/indexer.php';
require_once 'magento2/maintenance.php';
require_once 'magento2/patches.php';
require_once 'magento2/queue.php';
require_once 'magento2/slack.php';
require_once 'magento2/validation.php';

use Deployer\Exception\Exception;

/**
 * Default settings.
 */
set('allow_anonymous_stats', false);
set('keep_releases', 3);
set('release_name', function () {
    return date('YmdHis');
});
set('verbose', '--quiet');

/**
 * Binary locations.
 */
set('bin/composer', '/bin/composer');
set('bin/curl', '/bin/curl');
set('bin/magento', '/usr/bin/env php -f {{magento_dir}}/bin/magento');
set('bin/n98', '/usr/local/bin/n98-magerun2');

/**
 * Notification settings.
 */

set('slack_color_deploy', '#2C649E');
set('slack_color_failure', '#9c0d38');
set('slack_color_success', '#5CB589');
set('slack_text_deploy', ':rocket:  Deploying `{{branch}}` to *{{target}}* on {{hostname}}');
set('slack_text_failure', ':boom:  Failed to deploy `{{branch}}` to *{{target}}* on {{hostname}}');
set('slack_text_success', ':sparkles:  Successfully deployed `{{branch}}` to *{{target}}* on {{hostname}}');
set('slack_title', function () {
    return get('application', 'Project');
});

/**
 * Magento settings.
 */
set('magento_dir', 'src');
set('magento_chmod_dirs', '2755');
set('magento_chmod_files', '0644');
set('magento_compilation_strategy', '');
set('magento_composer_auth_config', []);
set('magento_composer_options', '--no-progress --no-dev --prefer-dist --no-interaction');
set('magento_deploy_languages', ['en_US']);
set('magento_deploy_production', true);
set('magento_deploy_themes', []);
set('magento_patched_files', []);
set('magento_timeout', 300);

set('clear_paths', [
    '.env.sample',
    '.eslintignore',
    'deploy.php',
    'docker-compose.local.yml',
    'docker-compose.yml',
    'grumphp.yml',
    'README.md',
    '.git/',
    '.vscode/',
    'build/',
    'config/',
    'db/',
    'deploy/',
    'docker/',
    'docs/',
    '{{magento_dir}}/var/cache',
    '{{magento_dir}}/var/page_cache',
    '{{magento_dir}}/var/view_preprocessed',
]);

set('shared_dirs', [
    '{{magento_dir}}/pub/media',
    '{{magento_dir}}/pub/sitemap',
    '{{magento_dir}}/var/backups',
    '{{magento_dir}}/var/composer_home',
    '{{magento_dir}}/var/import_history',
    '{{magento_dir}}/var/importexport',
    '{{magento_dir}}/var/log',
    '{{magento_dir}}/var/report',
    '{{magento_dir}}/var/tmp',
]);

set('shared_files', [
    '{{magento_dir}}/app/etc/env.php',
    '{{magento_dir}}/var/.maintenance.ip',
    '{{magento_dir}}/var/.setup_cronjob_status',
    '{{magento_dir}}/var/.update_cronjob_status',
]);

/**
 * Tasks.
 */
desc('Magento Deployment Tasks');
task('deploy:magento', [
    'magento:deploy:verify',
    'magento:composer:install',
    'magento:deploy:mode:production',
    'magento:setup:static-content:deploy',
    'magento:setup:di:compile',
    'magento:composer:autoload',
    'magento:setup:permissions',
    'magento:deploy:maintenance',
    'magento:crontab:disable',
    'magento:database:upgrade',
    'magento:configuration:import',
    'magento:crontab:enable',
    'magento:cache:flush',
    'magento:maintenance:disable',
]);

desc('Deploy New Release');
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'deploy:magento',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success',
]);

before('magento:composer:install', 'magento:composer:auth_config');
after('magento:composer:install', 'magento:deploy:patches');
after('magento:composer:install', 'magento:deploy:patches:files');
after('magento:deploy:patches:files', 'deploy:clear_paths');
after('magento:crontab:disable', 'magento:consumers:remove');
after('deploy:failed', 'deploy:unlock');

desc('Rollback to previous release');
task('rollback:validate', function () {
    $errorMessage = 'Secure rollback not possible' . PHP_EOL;
    $errorMessage .= PHP_EOL;
    $errorMessage .= 'This Magento version has the following constraints:' . PHP_EOL;
    $errorMessage .= '- Not possible to know if previous release is compatible with current DB schema' . PHP_EOL;
    $errorMessage .= PHP_EOL;
    $errorMessage .= 'You can still do a manual rollback at your own risk' . PHP_EOL;
    throw new Exception($errorMessage);
});
