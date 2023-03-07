<?php

/**
 * Deployer Recipe for Magento 2.4 Deployments
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2023 August Ash (https://www.augustash.com)
 */

declare(strict_types=1);

namespace Deployer;

use Deployer\Exception\GracefulShutdownException;
use Deployer\Host\Host;

desc('Normalize Magento filesystem permissions');
task('magento:deploy:permissions', function () {
    within('{{release_or_current_path}}', function () {
        $dirs = get('magento_perms_dirs', '2770');
        $files = get('magento_perms_files', '0660');

        run('find {{release_or_current_path}}/{{magento_root}} -type d ! -perm ' . $dirs . ' -exec chmod ' . $dirs . ' {} +');
        run('find {{release_or_current_path}}/{{magento_root}} -type f ! -perm ' . $files . ' -exec chmod ' . $files . ' {} +');
        run('chmod +x {{release_or_current_path}}/{{magento_root}}bin/magento');
        run('chmod +x {{release_or_current_path}}/{{magento_root}}vendor/bin/*');
    });
})->select('role=app');

desc('Verify Magento deployability on the remote server');
task('magento:deploy:verify', function () {
    if (test('[ ! -f {{release_path}}/{{magento_root}}app/etc/config.php ]')) {
        throw new GracefulShutdownException(
            'The repository is missing `app/etc/config.php`. Please install the application and retry!'
        );
    }

    if (!test('php -r \'$cfg = include "{{release_path}}/{{magento_root}}app/etc/env.php"; exit((int)!isset($cfg["install"]["date"]));\'')) {
        throw new GracefulShutdownException(
            'No environment configuration could be found. Please configure `app/etc/env.php` and retry!'
        );
    }
})->select('role=app');

desc('Enable developer application mode');
task('magento:deploy_mode:developer', function () {
    within('{{release_or_current_path}}', function () {
        run('{{bin/magento}} deploy:mode:set developer');
    });
})->select('role=app');

desc('Enable production application mode');
task('magento:deploy_mode:production', function () {
    within('{{release_or_current_path}}', function () {
        if (test('[ -f {{release_path}}/{{magento_root}}app/etc/env.php ]')) {
            run('{{bin/magento}} deploy:mode:set production --skip-compilation');
        } else {
            run('echo "<?php return [\'MAGE_MODE\' => \'production\'];" > {{release_path}}/{{magento_root}}app/etc/env.php');
        }
    });
})->select('role=app');

desc('Display the current application mode');
task('magento:deploy_mode:show', function () {
    within('{{release_or_current_path}}', function () {
        run('{{bin/magento}} deploy:mode:show');
    });
})->select('role=app');

desc('Generate dependency injection');
task('magento:di:compile', function () {
    within('{{release_or_current_path}}', function () {
        run('{{bin/magento}} setup:di:compile --no-interaction');
    });
})->select('role=app');

desc('Generate the Magento 2 static content');
task('magento:static-content:deploy', function () {
    within('{{release_or_current_path}}', function () {
        $jobs = get('magento_deploy_jobs', 1);
        $languages = \implode(' ', get('magento_deploy_languages', ['en_US']));
        $strategy = get('magento_compilation_strategy', '');
        $themes = '';

        if (\count(get('magento_deploy_themes', [])) > 0) {
            $themes = ' -t ' . \implode(' -t ', get('magento_deploy_themes'));
        }

        if ($strategy) {
            $strategy = ' -s ' . $strategy;
        }

        run(\sprintf(
            '{{bin/magento}} setup:static-content:deploy -f -n --content-version={{content_version}} -j %d%s%s -- %s',
            $jobs,
            $strategy,
            $themes,
            $languages
        ));
    });
})->select('role=app');

desc('Sync asset content version');
task('magento:sync:content_version', function () {
    $timestamp = time();
    on(select('all'), function (Host $host) use ($timestamp) {
        $host->set('content_version', $timestamp);
    });
})->once();

desc('Sync cache prefix');
task('magento:sync:cache_prefix', function () {
    within('{{release_or_current_path}}', function () {
        if (test('[ ! -f {{release_or_current_path}}/{{magento_root}}pub/VERSION.json ]')) {
            $cachePrefix = run('cat {{deploy_path}}/.dep/latest_release || echo 0');
        } else {
            $cachePrefix = run('grep -oP \'(?<="run_id": ")[^"]*\' {{release_or_current_path}}/{{magento_root}}pub/VERSION.json || echo 0');
        }

        // set cache prefix to the deploy run ID
        run(\sprintf(
            '{{bin/magento}} setup:config:set -n --cache-id-prefix="%s_" --page-cache-id-prefix="%s_"',
            $cachePrefix,
            $cachePrefix
        ));

        // update preload keys
        run('{{bin/sed}} -i "s/.*EAV_ENTITY_TYPES.*/                        \'' . $cachePrefix . '_EAV_ENTITY_TYPES\',/" \
            {{deploy_path}}/shared/{{magento_root}}app/etc/env.php');
        run('{{bin/sed}} -i "s/.*GLOBAL_PLUGIN_LIST.*/                        \'' . $cachePrefix . '_GLOBAL_PLUGIN_LIST\',/" \
            {{deploy_path}}/shared/{{magento_root}}app/etc/env.php');
        run('{{bin/sed}} -i "s/.*DB_IS_UP_TO_DATE.*/                        \'' . $cachePrefix . '_DB_IS_UP_TO_DATE\',/" \
            {{deploy_path}}/shared/{{magento_root}}app/etc/env.php');
        run('{{bin/sed}} -i "s/.*SYSTEM_DEFAULT.*/                        \'' . $cachePrefix . '_SYSTEM_DEFAULT\',/" \
            {{deploy_path}}/shared/{{magento_root}}app/etc/env.php');
    });
})->select('role=app');
