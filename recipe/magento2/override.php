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

desc('Deploy custom file overrides');
task('magento:override:files', function () {
    within('{{release_or_current_path}}', function () {
        $files = get('magento_override_files', []);
        foreach ($files as $file) {
            if (!test("[ -f {{deploy_path}}/shared/{{magento_root}}$file ]")) {
                throw new Exception(\sprintf(
                    'File "%s" does not exist in shared directory.',
                    $file
                ));
            }

            run(\sprintf('rm -f {{release_or_current_path}}/{{magento_root}}%s', $file));
            run(\sprintf('cp -f {{deploy_path}}/shared/{{magento_root}}%s {{release_or_current_path}}/{{magento_root}}%s', $file, $file));
        }
    });
})->select('role=app');

desc('Remove modules (devlopment) from configuration');
task('magento:override:modules', function () {
    within('{{release_or_current_path}}', function () {
        $modules = get('magento_dev_modules', []);
        foreach ($modules as $module) {
            run(\sprintf("sed -i '/%s/d' {{release_or_current_path}}/{{magento_root}}app/etc/config.php", $module));
        }
    });
})->select('role=app');
