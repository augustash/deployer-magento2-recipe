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

/**
 * Binary locations.
 */
set('bin/tar', function () {
    return which('tar');
});

/**
 * Tasks.
 */
desc('Builds a Magento artifact for deployment');
task('artifact:package', function () {
    if (!currentHost()->get('local')) {
        throw new GracefulShutdownException('Artifact can only be built locally, you provided a non-local host');
    }

    $excludes = '';
    if (get('artifact_excludes_file')) {
        if (test('[ ! -f {{release_path}}/{{artifact_excludes_path}}/{{artifact_excludes_file}} ]')) {
            throw new GracefulShutdownException(
                'The artifact excludes file cannot be found!'
            );
        }
        $excludes = ' --exclude-from={{release_path}}/{{artifact_excludes_path}}/{{artifact_excludes_file}}';
    }

    runLocally('mkdir -p {{release_path}}/{{artifact_path}}');
    runLocally(\sprintf(
        '{{bin/tar}}%s --anchored -cvf {{release_path}}/{{artifact_path}}/{{artifact_file}} -C {{release_path}} .',
        $excludes
    ));
});

desc('Extracts build artifact in release path.');
task('artifact:extract', function () {
    run('{{bin/tar}} -xpf {{release_path}}/{{artifact_path}}/{{artifact_file}} -C {{release_path}}');
    run('rm -rf {{release_path}}/{{artifact_path}}');
})->select('role=app');

desc('Uploads build artifact in release folder for extraction.');
task('artifact:upload', function () {
    upload(get('artifact_path'), '{{release_path}}');
})->select('role=app');

desc('Prepard local directory for artifact build.');
task('build:prepare', function () {
    if (!currentHost()->get('local')) {
        throw new GracefulShutdownException('Artifact can only be built locally, you provided a non-local host');
    }

    $buildPath = runLocally('pwd');

    if (test('[ ! -d ' . $buildPath . ' ]')) {
        throw new GracefulShutdownException('The determined local directory "' . $buildPath . '" does not exist!');
    }

    set('current_path', $buildPath);
    set('deploy_path', $buildPath);
    set('release_path', $buildPath);

    runLocally('rm -rf ./{{magento_root}}generated/*');
    runLocally('rm -rf ./{{magento_root}}pub/static/*');
    runLocally('rm -rf ./{{magento_root}}var/view_preprocessed/*');
});
