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
 * Binary locations.
 */
set('bin/npm', '/usr/bin/env npm');

/**
 * Magento settings.
 */
set('hyva_themes', []);

/**
 * Tasks.
 */
desc('Install Hyvä Themes dependencies');
task('magento:hyva:install', function () {
    $themes = get('hyva_themes', []);
    foreach ($themes as $theme) {
        run(\sprintf(
            "{{bin/npm}} --prefix {{release_or_current_path}}/{{magento_root}}app/design/frontend/%s/web/tailwind/ %s",
            $theme,
            "ci"
        ));
    }
})->select('role=app');

desc('Generate Hyvä Themes assets');
task('magento:hyva:deploy', function () {
    $themes = get('hyva_themes', []);
    foreach ($themes as $theme) {
        run(\sprintf(
            "{{bin/npm}} --prefix {{release_or_current_path}}/{{magento_root}}app/design/frontend/%s/web/tailwind/ %s",
            $theme,
            "run build-prod"
        ));
    }
})->select('role=app');

/**
 * Events.
 */
after('magento:composer:install', 'magento:hyva:install');
before('magento:static-content:deploy', 'magento:hyva:deploy');
