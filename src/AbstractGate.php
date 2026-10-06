<?php

/**
 * Deployer Recipe for Magento 2.4 Deployments
 *
 * @author    Josh Johnson <josh@augustash.com>
 * @copyright Copyright (c) 2026 August Ash (https://www.augustash.com)
 */

declare(strict_types=1);

namespace Augustash\Deployer;

use InvalidArgumentException;

/**
 * Input normalization and checkout checks shared by the deploy test gates.
 */
abstract class AbstractGate
{
    public const REPORT_SUMMARY = 'summary';

    public const REPORT_TESTS = 'tests';

    /**
     * Normalize a list setting given as an array or a comma-separated string.
     *
     * @param mixed $value
     * @return string[]
     */
    public function normalizeList(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);
        $items = array_map(static fn($item): string => trim((string) $item), $items);

        return array_values(array_filter($items, static fn(string $item): bool => $item !== ''));
    }

    /**
     * Normalize a timeout setting to a positive int, or null for no timeout.
     *
     * @param mixed $value
     * @param string $setting Setting name used in the error message
     * @return int|null
     * @throws InvalidArgumentException
     */
    public function normalizeTimeout(mixed $value, string $setting = 'phpunit_timeout'): ?int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }
        if ((is_int($value) || (is_string($value) && ctype_digit($value))) && (int) $value > 0) {
            return (int) $value;
        }

        throw new InvalidArgumentException(
            sprintf('%s must be a positive number of seconds or 0.', $setting)
        );
    }

    /**
     * Whether the skip_tests option value asks to skip the gate.
     *
     * Deployer only casts the literal strings true/false, so "1", "yes" and "on" arrive as raw strings.
     *
     * @param mixed $value
     * @return bool
     */
    public function isSkipRequested(mixed $value): bool
    {
        return $this->isTruthy($value);
    }

    /**
     * Parse a boolean setting given as a bool or as a raw -o string ("true", "1", "yes", "on").
     *
     * @param mixed $value
     * @return bool
     */
    public function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return is_string($value) && in_array(strtolower(trim($value)), ['true', '1', 'yes', 'on'], true);
    }

    /**
     * Whether skipping the gate needs an explicit confirmation for the given stage.
     *
     * @param string|null $stage
     * @return bool
     */
    public function requiresSkipConfirmation(?string $stage): bool
    {
        return $stage === 'production';
    }

    /**
     * Normalize a report setting to one of the REPORT_* modes.
     *
     * @param mixed $value
     * @param string $setting Setting name used in the error message
     * @return string
     * @throws InvalidArgumentException
     */
    public function normalizeReportMode(mixed $value, string $setting = 'phpunit_report'): string
    {
        $mode = strtolower(trim((string) $value));
        if ($mode === '') {
            return self::REPORT_SUMMARY;
        }
        if (!in_array($mode, [self::REPORT_SUMMARY, self::REPORT_TESTS], true)) {
            throw new InvalidArgumentException(sprintf(
                '%s must be "%s" or "%s", got "%s".',
                $setting,
                self::REPORT_SUMMARY,
                self::REPORT_TESTS,
                $mode
            ));
        }

        return $mode;
    }

    /**
     * Describe differences between the local checkout and what Deployer will deploy.
     *
     * @param string|null $deployTarget Deployer's target: branch, tag or revision
     * @param string $localBranch Output of git rev-parse --abbrev-ref HEAD ("HEAD" when detached)
     * @param string $localSha Output of git rev-parse HEAD
     * @param bool $dirty Whether tracked files have uncommitted changes
     * @return string[]
     */
    public function checkoutWarnings(?string $deployTarget, string $localBranch, string $localSha, bool $dirty): array
    {
        $warnings = [];

        if (!in_array($deployTarget, [null, '', 'HEAD'], true)
            && !$this->targetMatchesCheckout($deployTarget, $localBranch, $localSha)
        ) {
            $warnings[] = $localBranch === 'HEAD'
                ? sprintf('Local checkout is a detached HEAD, but the deploy target is "%s".', $deployTarget)
                : sprintf(
                    'Local branch "%s" differs from the deploy target "%s"; tests run against local code.',
                    $localBranch,
                    $deployTarget
                );
        }

        if ($dirty) {
            $warnings[] = 'Working tree has uncommitted changes to tracked files; tests run against them.';
        }

        return $warnings;
    }

    /**
     * Check whether the deploy target is the local branch name or the local commit.
     *
     * @param string $target
     * @param string $localBranch
     * @param string $localSha
     * @return bool
     */
    private function targetMatchesCheckout(string $target, string $localBranch, string $localSha): bool
    {
        return $target === $localBranch
            || (strlen($target) >= 7 && str_starts_with(strtolower($localSha), strtolower($target)));
    }
}
