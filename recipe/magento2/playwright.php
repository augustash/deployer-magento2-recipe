<?php

/**
 * Deployer Recipe for Magento 2.4 Deployments
 *
 * @author    Josh Johnson <josh@augustash.com>
 * @copyright Copyright (c) 2026 August Ash (https://www.augustash.com)
 */

declare(strict_types=1);

namespace Deployer;

use Augustash\Deployer\PlaywrightGate;
use Deployer\Exception\GracefulShutdownException;
use Deployer\Exception\RunException;
use Deployer\Exception\TimeoutException;
use InvalidArgumentException;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * phpcs:disable Magento2.Security.IncludeFile.FoundIncludeFile
 */
// Loaded directly as well as via Composer so the recipe also works with a global/phar Deployer.
require_once __DIR__ . '/../../src/AbstractGate.php';
require_once __DIR__ . '/../../src/PlaywrightGate.php';

/**
 * Settings. The tests run through the ddev-magento-playwright add-on (>= 0.2.0) against the local DDEV site.
 */
// Themes as <vendor>/<theme>. Empty reads PLAYWRIGHT_THEME_DIRS from the DDEV web container.
set('playwright_themes', []);
// Playwright projects to run; the seed and setup projects run as dependencies.
set('playwright_projects', ['chromium']);
// Optional --grep pattern to narrow the suite.
set('playwright_grep', '');
// Extra raw arguments passed to "playwright test".
set('playwright_options', []);
// Seconds. Passed to Playwright as --global-timeout, so it still writes its report when the time is up.
set('playwright_timeout', null);
// Report detail: "summary" prints one result line per theme (plus details of failing tests),
// "tests" lists every test of every theme.
set('playwright_report', PlaywrightGate::REPORT_SUMMARY);
set('skip_playwright', false);

// Seconds the local process may outlive Playwright's own global timeout.
const PLAYWRIGHT_TIMEOUT_GRACE = 120;
const PLAYWRIGHT_OUTPUT_TAIL_LINES = 30;

desc('Run the Playwright suite locally before deploying');
task('deploy:playwright', function (): void {
    $gate = new PlaywrightGate();

    if ($gate->isSkipRequested(get('skip_playwright', false)) || $gate->isSkipRequested(get('skip_tests', false))) {
        if ($gate->requiresSkipConfirmation(get('stage'))
            && !askConfirmation('Skip Playwright for PRODUCTION?', false)
        ) {
            throw new GracefulShutdownException('Deploy aborted: Playwright skip for production was not confirmed.');
        }
        warning("*****************************************\n"
            . "* SKIPPING PLAYWRIGHT (skip_playwright) *\n"
            . "* Deploying without end-to-end tests.   *\n"
            . '*****************************************');

        return;
    }

    $magentoRoot = rtrim((string) get('magento_root'), '/');

    try {
        $projects = $gate->normalizeList(get('playwright_projects'));
        $options = $gate->normalizeList(get('playwright_options'));
        $timeout = $gate->normalizeTimeout(get('playwright_timeout'), 'playwright_timeout');
        $reportMode = $gate->normalizeReportMode(get('playwright_report'), 'playwright_report');
        $themes = $gate->normalizeList(get('playwright_themes'));
    } catch (InvalidArgumentException $e) {
        throw new GracefulShutdownException($e->getMessage());
    }

    try {
        runLocally('ddev exec true');
    } catch (RunException | TimeoutException) {
        throw new GracefulShutdownException('DDEV is not running. Run "ddev start" and try again.');
    }

    try {
        $help = runLocally('ddev help playwright');
    } catch (RunException | TimeoutException) {
        $help = '';
    }
    if (!str_contains($help, '--ci')) {
        throw new GracefulShutdownException(
            'The ddev-magento-playwright add-on is missing or has no CI mode. '
            . 'Update the ddev-magento-playwright add-on to a version with --ci (>= 0.2.0).'
        );
    }

    if ($themes === []) {
        try {
            $themes = $gate->normalizeList(
                \preg_replace('/\s+/', ',', runLocally('ddev exec \'printf %s "${PLAYWRIGHT_THEME_DIRS}"\'')) ?? ''
            );
        } catch (RunException | TimeoutException) {
            throw new GracefulShutdownException('Could not read PLAYWRIGHT_THEME_DIRS from DDEV.');
        }
    }
    if ($themes === []) {
        throw new GracefulShutdownException(
            'No Playwright themes configured. Set playwright_themes or PLAYWRIGHT_THEME_DIRS in DDEV.'
        );
    }

    try {
        $commands = [];
        foreach ($themes as $theme) {
            $commands[$theme] = [
                'run' => $gate->buildCommand(
                    $theme,
                    $projects,
                    (string) get('playwright_grep'),
                    $timeout,
                    $options
                ),
                'read' => $gate->readResultsCommand($magentoRoot, $theme),
            ];
        }
    } catch (InvalidArgumentException $e) {
        throw new GracefulShutdownException($e->getMessage());
    }

    // Branch and cleanliness checks are advisory only and must never abort the gate.
    try {
        $branch = trim(runLocally('git rev-parse --abbrev-ref HEAD'));
        $sha = trim(runLocally('git rev-parse HEAD'));
        $dirty = trim(runLocally('git status --porcelain --untracked-files=no')) !== '';
        $target = get('target');
        foreach ($gate->checkoutWarnings(is_string($target) ? $target : null, $branch, $sha, $dirty) as $message) {
            warning($message);
        }
    } catch (RunException | TimeoutException) {
        warning('Could not inspect the local git checkout; continuing without branch checks.');
    }

    $out = output();
    $out->writeln('');
    $out->writeln(sprintf('<info>Playwright</info>: testing %d theme(s)', count($themes)));

    $width = max(array_map('mb_strlen', $themes));
    $resultLine = static fn(bool $passed, string $theme, string $summary): string => sprintf(
        '  %s %s  %s',
        $passed ? '<info>✔</info>' : '<error>✘</error>',
        OutputFormatter::escape(str_pad($theme, $width)),
        OutputFormatter::escape($summary)
    );
    $indentLines = static function (array $lines, string $indent) use ($out): void {
        foreach ($lines as $line) {
            $out->writeln($line === '' ? '' : $indent . OutputFormatter::escape($line));
        }
    };

    $results = [];
    foreach ($commands as $theme => $command) {
        // runLocally buffers all output, so announce the run to keep a long one from looking hung.
        $out->writeln('');
        $out->writeln(sprintf(
            '<info>▸ Running Playwright for %s (%s)...</info>',
            OutputFormatter::escape($theme),
            OutputFormatter::escape(implode(', ', $projects))
        ));

        $exitCode = 0;
        $addonOutput = '';
        $timedOut = false;
        try {
            runLocally($command['run'], [
                'timeout' => $timeout === null ? null : $timeout + PLAYWRIGHT_TIMEOUT_GRACE,
            ]);
        } catch (RunException $e) {
            $exitCode = $e->getExitCode() ?: 1;
            $addonOutput = $e->getOutput() . "\n" . $e->getErrorOutput();
        } catch (TimeoutException) {
            $exitCode = 1;
            $timedOut = true;
            // Killing the local process leaves "npx playwright" running in the container; stop it so
            // no orphan keeps writing to the database or the report.
            try {
                runLocally('ddev exec "pkill -f \'playwright test\'"');
            } catch (RunException | TimeoutException) {
                // Best effort only.
            }
        }

        // Always read through the container: the host copy can lag behind (Mutagen).
        $parsed = null;
        $problem = $timedOut ? 'Playwright timed out' : 'Playwright did not produce a JSON report';
        try {
            $parsed = $gate->parseReport(runLocally($command['read']));
        } catch (InvalidArgumentException $e) {
            $problem = $e->getMessage();
        } catch (RunException | TimeoutException) {
            // No report was written; keep the default problem.
        }

        $passed = $gate->passed($exitCode, $parsed);
        $results[$theme] = $passed;

        if ($parsed === null) {
            $out->writeln($resultLine(false, $theme, $problem));
            $tail = array_slice(
                array_filter(
                    preg_split('/\R/', trim($addonOutput)) ?: [],
                    static fn(string $line): bool => trim($line) !== ''
                ),
                -PLAYWRIGHT_OUTPUT_TAIL_LINES
            );
            $indentLines($tail, '      ');
            continue;
        }

        $summary = $gate->summaryLine($parsed);
        if (!$passed && $parsed['stats']['failed'] === 0 && $parsed['errors'] === []) {
            $summary .= sprintf(' [exit code %d]', $exitCode);
        }
        $out->writeln($resultLine($passed, $theme, $summary));
        if ($passed && $parsed['tests'] === []) {
            warning(sprintf(
                'Playwright ran no tests for %s (check playwright_grep and playwright_projects).',
                $theme
            ));
        }
        $indentLines($gate->formatReport($parsed, $reportMode), '      ');
    }
    $out->writeln('');

    $failed = array_keys(array_filter($results, static fn(bool $passed): bool => !$passed));
    if ($failed !== []) {
        throw new GracefulShutdownException(
            sprintf('Playwright failed for %s; aborting the deploy.', implode(', ', $failed))
        );
    }
})->once();
