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
 * Settings.
 */
set('bin/curl', function () {
    return which('curl');
});
set('cloudflare_key', null);
set('cloudflare_zone', null);

/**
 * Tasks.
 */
desc('Flush CloudFlare Zone Cache');
task('cloudflare:cache:flush', function () {
    $zone = get('cloudflare_zone', null);
    $key = get('cloudflare_key', null);

    if ($zone !== null && $key !== null) {
        run('{{bin/curl}} -X POST "https://api.cloudflare.com/client/v4/zones/{{cloudflare_zone}}/purge_cache" \
            -H "Authorization: Bearer {{cloudflare_key}}" \
            -H "Content-Type: application/json" \
            --data \'{"purge_everything":true}\'');
    }
})->select('role=app');
