<?php

/**
 * Magento 2.4.x Deployer Recipe
 *
 * @deprecated since deployer/deployer 7.0
 * @see deployer/deployer/contrib/slack
 *
 * @author    Peter McWilliams <pmcwilliams@augustash.com>
 * @copyright Copyright (c) 2022 August Ash (https://www.augustash.com)
 * @license   MIT
 */

namespace Deployer;

use Deployer\Exception\Exception;
use Deployer\Utility\Httpie;

desc('Deploy notification to Slack');
task('slack:notify', function () {
    if (!get('slack_webhook', false)) {
        return;
    }

    $attachment = [
        'color' => get('slack_color_deploy'),
        'mrkdwn_in' => ['text'],
        'text' => get('slack_text_deploy'),
        'title' => get('slack_title'),
    ];

    postToSlack(get('slack_webhook'), $attachment);
})
    ->once()
    ->shallow()
    ->setPrivate();

desc('Deploy successful notification to Slack');
task('slack:notify:success', function () {
    if (!get('slack_webhook', false)) {
        return;
    }

    $attachment = [
        'color' => get('slack_color_success'),
        'mrkdwn_in' => ['text'],
        'text' => get('slack_text_success'),
        'title' => get('slack_title'),
    ];

    postToSlack(get('slack_webhook'), $attachment);
})
    ->once()
    ->shallow()
    ->setPrivate();

desc('Deploy failure notification to Slack');
task('slack:notify:failure', function () {
    if (!get('slack_webhook', false)) {
        return;
    }

    $attachment = [
        'color' => get('slack_color_failure'),
        'mrkdwn_in' => ['text'],
        'text' => get('slack_text_failure'),
        'title' => get('slack_title'),
    ];

    postToSlack(get('slack_webhook'), $attachment);
})
    ->once()
    ->shallow()
    ->setPrivate();

/**
 * Post message to Slack via Web Hooks.
 *
 * @param string $hook
 * @param array $attachment
 * @return void
 */
function postToSlack($hook, array $attachment)
{
    try {
        Httpie::post($hook)->body(['attachments' => [$attachment]])->send();
    } catch (\Exception $e) {
        throw new Exception('Failed to notify Slack: ' . $e->getMessage());
    }
}
