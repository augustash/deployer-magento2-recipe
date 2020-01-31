<?php

/**
 * Magento 2.3.x Deployer Recipe
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2020 August Ash (https://www.augustash.com)
 * @license   MIT
 */

namespace Deployer;

desc('Run Sass preprocessor setup');
task('magento:setup:gulp', function () {
    if (test('[ ! -f {{bin/gulp}} ]')) {
        within('{{release_path}}', function () {
            run('mkdir -p ${HOME}/.npm-packages');
            run('{{bin/npm}} config set prefix ${HOME}/.npm-packages');
            run('{{bin/npm}} install -g gulp-cli yarn');
        });
    }
});

desc('Install Sass preprocessor packages');
task('magento:setup:gulp:install', function () {
    within('{{release_path}}/{{magento_dir}}/vendor/snowdog/frontools', function () {
        run('{{bin/yarn}} install');
        run('{{bin/yarn}} add stylelint-order stylelint-scss');
        run('{{bin/gulp}} setup');
    });
});

desc('Deploy processed Sass into CSS');
task('magento:setup:gulp:deploy', function () {
    within('{{release_path}}/{{magento_dir}}/tools', function () {
        run('{{bin/gulp}} styles --prod --disableMaps');
    });
});
