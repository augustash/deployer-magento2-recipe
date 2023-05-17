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
 * phpcs:disable Magento2.Security.IncludeFile.FoundIncludeFile
 */
require_once 'recipe/common.php';
require_once 'magento2/artifact.php';
require_once 'magento2/backup.php';
require_once 'magento2/build.php';
require_once 'magento2/cache.php';
require_once 'magento2/composer.php';
require_once 'magento2/configuration.php';
require_once 'magento2/cron.php';
require_once 'magento2/database.php';
require_once 'magento2/indexer.php';
require_once 'magento2/maintenance.php';
require_once 'magento2/override.php';

/**
 * Default settings.
 */
set('allow_anonymous_stats', false);
set('application', null);
set('artifact_excludes_file', '.excludes');
set('artifact_excludes_path', 'deploy');
set('artifact_file', 'artifact_bundle.tar');
set('artifact_path', 'artifacts');
set('bin/magento', '{{bin/php}} -f {{release_or_current_path}}/{{magento_root}}bin/magento');
set('clear_paths', []);
set('keep_releases', 5);
set('magento_compilation_strategy', null);
set('magento_composer_auth_config', []);
set('magento_composer_options', [
    '--no-progress',
    '--no-interaction',
    '--no-scripts',
    '--no-dev',
    '--prefer-dist',
]);
set('magento_deploy_jobs', 8);
set('magento_deploy_languages', ['en_US']);
set('magento_deploy_themes', []);
set('magento_dev_modules', [
    'Augustash_Archi',
    'Augustash_WeltPixelLicenseOverride',
]);
set('magento_override_files', []);
set('magento_perms_dirs', '2755');
set('magento_perms_files', '0644');
set('magento_root', 'src/');
set('release_name', function () {
    return \date('YmdHis');
});
set('repository', null);

set('shared_dirs', [
    '{{magento_root}}pub/errors/augustash_maintenance',
    '{{magento_root}}pub/errors/default',
    '{{magento_root}}pub/media',
    '{{magento_root}}pub/sitemap',
    '{{magento_root}}pub/static/_cache',
    '{{magento_root}}var/backups',
    '{{magento_root}}var/composer_home',
    '{{magento_root}}var/export',
    '{{magento_root}}var/import_history',
    '{{magento_root}}var/import',
    '{{magento_root}}var/importexport',
    '{{magento_root}}var/log',
    '{{magento_root}}var/report',
    '{{magento_root}}var/session',
    '{{magento_root}}var/tmp',
]);

set('shared_files', [
    '{{magento_root}}app/etc/env.php',
    '{{magento_root}}var/.maintenance.ip',
]);

set('writable_dirs', [
    '{{magento_root}}generated',
    '{{magento_root}}pub/media',
    '{{magento_root}}pub/static',
    '{{magento_root}}var',
    '{{magento_root}}var/page_cache',
]);

/**
 * Tasks.
 */
desc('Deploy Magento 2');
task('deploy', [
    'deploy:prepare',
    'deploy:clear_paths',
    'deploy:magento',
    'deploy:publish',
]);

desc('Magento 2 Deployment Tasks');
task('deploy:magento', [
    'magento:deploy:verify',
    'magento:composer:auth_config',
    'magento:composer:install',
    'magento:override:files',
    'magento:deploy_mode:production',
    'magento:di:compile',
    'magento:static-content:deploy',
    'magento:composer:autoload',
    'magento:deploy:permissions',
    'magento:update'
]);

/**
 * If not deploying artifact with a CI processes, first run:
 *
 * $ dep artifact:build local
 */
desc('Deploy Magento 2 build artifact');
task('deploy:artifact', [
    'artifact:prepare',
    'magento:deploy:verify',
    'magento:override:files',
    'magento:update',
    // 'cachetool:clear:opcache',
    'deploy:publish',
]);

desc('Deploy Magento 2 build artifact');
task('magento:update', [
    'magento:crontab:disable',
    'magento:maintenance:enable',
    'magento:database:upgrade',
    'magento:configuration:import',
    'magento:crontab:enable',
    'magento:maintenance:disable',
    'magento:cache:flush',
]);

desc('Build Magento 2 artifact ');
task('artifact:build', [
    'build:prepare',
    'magento:composer:auth_config',
    'magento:composer:install',
    'magento:deploy_mode:production',
    'magento:di:compile',
    'magento:static-content:deploy',
    'magento:composer:autoload',
    'magento:deploy:permissions',
    'artifact:package',
]);

desc('Prepares a Magento build artifact for deployment');
task('artifact:prepare', [
    'deploy:info',
    'deploy:setup',
    'deploy:lock',
    'deploy:release',
    'artifact:upload',
    'artifact:extract',
    'deploy:clear_paths',
    'deploy:shared',
    'deploy:writable',
]);

/**
 * Events.
 */
fail('deploy:artifact', 'deploy:failed');
after('deploy:failed', 'deploy:unlock');
after('magento:override:files', 'magento:override:modules');
before('magento:static-content:deploy', 'magento:sync:content_version');
before('magento:configuration:import', 'magento:sync:cache_prefix');
