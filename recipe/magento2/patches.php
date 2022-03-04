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

desc('Deploy patches for Composer packages');
task('magento:deploy:patches', function () {
    within('{{release_path}}', function () {
        if (test('[ -f {{release_path}}/patches/apply-patches.sh ]')) {
            run("PATH_ROOT={{release_path}} ./patches/apply-patches.sh");
        }
    });
});

desc('Deploy patched files');
task('magento:deploy:patches:files', function () {
    $files = get('magento_patched_files', []);

    foreach ($files as $file) {
        // Check if shared file does not exist in shared.
        if (!test("[ -f {{deploy_path}}/shared/$file ]")) {
            throw new Exception("Missing patched file: $file.");
        }

        // Remove from source.
        run("if [ -f $(echo {{release_path}}/$file) ]; then rm -rf {{release_path}}/$file; fi");

        // Copy shared file to release file
        run("cp -f {{deploy_path}}/shared/$file {{release_path}}/$file");
    }
});

desc('Deploy patches for development modules');
task('magento:deploy:patches:modules', function () {
    $modules = get('magento_dev_modules', []);

    foreach ($modules as $module) {
        // Remove dev module from config.
        run("sed -i '/$module/d' {{release_path}}/src/app/etc/config.php");
    }
});
