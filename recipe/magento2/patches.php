<?php

/**
 * Magento 2.3.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2020 August Ash (https://www.augustash.com)
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
