<?php

/**
 * Magento 2.3.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2020 August Ash (https://www.augustash.com)
 * @license   MIT
 */

namespace Deployer;

desc('Display current application mode');
task('magento:deploy:mode:show', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} deploy:mode:show');
    });
});

desc('Enable developer application mode');
task('magento:deploy:mode:developer', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} deploy:mode:set developer');
    });
});

desc('Enable production application mode');
task('magento:deploy:mode:production', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} deploy:mode:set production --skip-compilation');
    });
});

desc('Run dependency injection compilation');
task('magento:setup:di:compile', function () {
    within('{{release_path}}', function () {
        run('{{bin/magento}} setup:di:compile --no-ansi');
    });
});

desc('Deploy static view files');
task('magento:setup:static-content:deploy', function () {
    within('{{release_path}}', function () {
        $strategy = get('magento_compilation_strategy', '');
        $languages = get('magento_deploy_languages', ['en_US']);
        $themes = get('magento_deploy_themes', []);

        if (count($themes) > 0) {
            $themes = ' -t ' . implode(' -t ', $themes);
        } else {
            $themes = '';
        }

        if ($strategy) {
            $strategy = '-s ' . $strategy;
        }

        foreach ($languages as $lang) {
            run('{{bin/magento}} setup:static-content:deploy -f '
                . $strategy . $lang . $themes);
        }
    });
});

desc('Set proper permissions on application');
task('magento:setup:permissions', function () {
    within('{{release_path}}', function () {
        $dirs = get('magento_chmod_dirs', '2770');
        $files = get('magento_chmod_files', '0660');

        run('find {{release_path}} -type d ! -perm ' . $dirs . ' -exec chmod ' . $dirs . ' {} +');
        run('find {{release_path}} -type f ! -perm ' . $files . ' -exec chmod ' . $files . ' {} +');
        run('chmod +x {{release_path}}/{{magento_dir}}/bin/magento');

        if (test('[ -f {{release_path}}/patches/apply-patches.sh ]')) {
            run('chmod +x {{release_path}}/patches/apply-patches.sh');
        }
    });
});
