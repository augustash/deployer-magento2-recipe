<?php

/**
 * Deployer Recipe for Magento 2.4 Deployments
 *
 * @author    Josh Johnson <josh@augustash.com>
 * @copyright Copyright (c) 2026 August Ash (https://www.augustash.com)
 */

declare(strict_types=1);

namespace Deployer;

use Augustash\Deployer\PhpUnitGate;
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
require_once __DIR__ . '/../../src/PhpUnitGate.php';

/**
 * Settings. The bin, config, test and exclude paths are relative to {{magento_root}}.
 * Test and exclude paths accept glob patterns; every Test/Unit directory beneath a test path is run
 * unless it (or a parent directory) matches an exclude path, e.g. a third-party module in app/code.
 */
set('phpunit_test_paths', ['app/code', 'local-src', 'vendor/augustash/*']);
set('phpunit_exclude_paths', []);
// Only run tests for modules enabled in {{magento_root}}app/etc/config.php.
set('phpunit_enabled_modules_only', true);
set('phpunit_bin', 'vendor/bin/phpunit');
set('phpunit_config', 'dev/tests/unit/phpunit.xml.dist');
set('phpunit_options', PhpUnitGate::DEFAULT_OPTIONS);
// Raw shell prefix: tests run in the DDEV web container, from the PHPUnit config's directory.
// Use an empty string to run on the host PHP.
set('phpunit_wrapper', function (): string {
    $parts = \array_filter(
        [\trim((string) get('magento_root'), '/'), \dirname((string) get('phpunit_config'))],
        static fn(string $part): bool => $part !== '' && $part !== '.'
    );

    return 'ddev exec --dir ' . \implode('/', ['/var/www/html', ...$parts]);
});
set('phpunit_timeout', null);
// Report detail: "summary" prints one result line per module (plus details of failing tests),
// "tests" lists every test of every module.
set('phpunit_report', PhpUnitGate::REPORT_SUMMARY);
set('skip_tests', false);

desc('Run the PHPUnit suite locally before deploying');
task('deploy:phpunit', function (): void {
    $gate = new PhpUnitGate();

    if ($gate->isSkipRequested(get('skip_tests'))) {
        if ($gate->requiresSkipConfirmation(get('stage'))
            && !askConfirmation('Skip PHPUnit for PRODUCTION?', false)
        ) {
            throw new GracefulShutdownException('Deploy aborted: PHPUnit skip for production was not confirmed.');
        }
        warning("*****************************************\n"
            . "* SKIPPING PHPUNIT (skip_tests)         *\n"
            . "* Deploying without running unit tests. *\n"
            . '*****************************************');

        return;
    }

    $wrapper = trim((string) get('phpunit_wrapper'));
    $magentoRoot = rtrim((string) get('magento_root'), '/');
    // Resolve the project root the same way Deployer does for runLocally().
    $projectRoot = \getenv('DEPLOYER_ROOT') ?: \dirname((string) DEPLOYER_DEPLOY_FILE);
    $baseDir = $projectRoot . '/' . $magentoRoot;

    try {
        $paths = $gate->normalizeList(get('phpunit_test_paths'));
        $options = $gate->normalizeList(get('phpunit_options'));
        $timeout = $gate->normalizeTimeout(get('phpunit_timeout'));
        $listTests = $gate->normalizeReportMode(get('phpunit_report')) === PhpUnitGate::REPORT_TESTS;

        if (!$gate->phpunitBinExists($baseDir, (string) get('phpunit_bin'))) {
            throw new GracefulShutdownException(
                sprintf(
                    'PHPUnit not found at %s/%s. Run "composer install" (with dev deps) in %s.',
                    $magentoRoot,
                    get('phpunit_bin'),
                    $magentoRoot === '' ? 'the project root' : $magentoRoot . '/'
                )
            );
        }
        $paths = $gate->resolveTestDirs($baseDir, $paths, $gate->normalizeList(get('phpunit_exclude_paths')));
        $skipped = [];
        if ($gate->isTruthy(get('phpunit_enabled_modules_only'))) {
            [$paths, $skipped] = $gate->partitionByEnabledModule(
                $baseDir,
                $paths,
                $gate->enabledModules($baseDir . '/app/etc/config.php')
            );
            if ($paths === []) {
                throw new GracefulShutdownException('No test directories belong to enabled modules.');
            }
        }
        // One PHPUnit run per module so each module's results are reported under its name.
        $suites = [];
        foreach ($paths as $testDir) {
            $suites[$testDir] = [
                'command' => $gate->buildCommand(
                    $magentoRoot,
                    (string) get('phpunit_bin'),
                    (string) get('phpunit_config'),
                    [$testDir],
                    [...$options, ...PhpUnitGate::REPORT_OPTIONS],
                    $wrapper
                ),
                'label' => $gate->moduleNameForTestDir($baseDir, $testDir) ?? $testDir,
            ];
        }
    } catch (InvalidArgumentException $e) {
        throw new GracefulShutdownException($e->getMessage());
    }

    if (str_starts_with($wrapper, 'ddev')) {
        try {
            runLocally('ddev exec true');
        } catch (RunException) {
            throw new GracefulShutdownException('DDEV is not running. Run "ddev start" and try again.');
        }
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
    } catch (RunException) {
        warning('Could not inspect the local git checkout; continuing without branch checks.');
    }

    $out = output();
    $out->writeln('');
    $out->writeln(sprintf('<info>PHPUnit</info>: testing %d module(s)', count($suites)));
    foreach ($skipped as $testDir => $module) {
        $out->writeln(sprintf(
            '  <comment>skip</comment> %s <fg=gray>(%s)</>',
            OutputFormatter::escape($module ?? $testDir),
            $module === null ? 'not a Magento module' : 'not enabled'
        ));
    }

    $width = max(array_map(static fn(array $suite): int => mb_strlen($suite['label']), $suites));
    $resultLine = static fn(bool $passed, string $label, string $summary): string => sprintf(
        '  %s %s  %s',
        $passed ? '<info>✔</info>' : '<error>✘</error>',
        OutputFormatter::escape(str_pad($label, $width)),
        OutputFormatter::escape($summary)
    );

    $results = [];
    foreach ($suites as $testDir => $suite) {
        if ($listTests) {
            $out->writeln('');
            $out->writeln(sprintf(
                '<info>▸ %s</info> <fg=gray>%s</>',
                OutputFormatter::escape($suite['label']),
                OutputFormatter::escape($testDir)
            ));
        }
        try {
            $report = runLocally($suite['command'], ['timeout' => $timeout]);
            $passed = true;
        } catch (RunException $e) {
            // stderr only carries the wrapper's own failure notice unless PHPUnit crashed before reporting.
            $report = $gate->summaryLine($e->getOutput()) !== ''
                ? $e->getOutput()
                : $e->getOutput() . "\n" . $e->getErrorOutput();
            $passed = false;
        } catch (TimeoutException) {
            $report = '';
            $passed = false;
        }
        $summary = $gate->summaryLine($report) ?: ($report === '' ? 'timed out' : 'PHPUnit failed');
        $results[$suite['label']] = [$passed, $summary];

        if (!$listTests) {
            $out->writeln($resultLine($passed, $suite['label'], $summary));
        }
        $indent = $listTests ? '  ' : '      ';
        foreach ($gate->formatReport($report, !$listTests) as $line) {
            $out->writeln($line === '' ? '' : $indent . OutputFormatter::escape($line));
        }
    }

    if ($listTests) {
        $out->writeln('');
        $out->writeln('<info>PHPUnit summary</info>');
        foreach ($results as $label => [$passed, $summary]) {
            $out->writeln($resultLine($passed, $label, $summary));
        }
    }
    $out->writeln('');

    $failed = array_keys(array_filter($results, static fn(array $result): bool => !$result[0]));
    if ($failed !== []) {
        throw new GracefulShutdownException(
            sprintf('PHPUnit failed for %s; aborting the deploy.', implode(', ', $failed))
        );
    }
})->once();
