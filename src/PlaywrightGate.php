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
use JsonException;

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * Command building and JSON report handling for the deploy Playwright gate.
 */
class PlaywrightGate extends AbstractGate
{
    public const RESULTS_FILE = 'deploy-gate.json';

    private const CONTAINER_ROOT = '/var/www/html';

    private const THEME_PATTERN = '/^[A-Za-z0-9_-]+\/[A-Za-z0-9_-]+$/';

    private const ERROR_LINES = 5;

    private const STATUS_MARKS = [
        'expected' => '✔',
        'unexpected' => '✘',
        'flaky' => '~',
        'skipped' => '-',
    ];

    /**
     * Build the ddev command that runs one theme's Playwright suite in CI mode.
     *
     * Grep and grep-invert patterns are regexes matched against test titles, so tags such as "@hot" work too.
     * Several patterns are combined into one alternation. Test file filters go last, after the options.
     *
     * @param string $theme Theme as <vendor>/<theme>
     * @param string[] $projects
     * @param string[] $grep Only run tests whose title matches one of these patterns
     * @param string[] $grepInvert Skip tests whose title matches one of these patterns
     * @param string[] $testFiles Playwright file filters, e.g. "base-tests/home.spec.ts"
     * @param int|null $timeoutSeconds Playwright's own global timeout, so it still writes the JSON report
     * @param string[] $options
     * @return string
     * @throws InvalidArgumentException
     */
    public function buildCommand(
        string $theme,
        array $projects,
        array $grep,
        array $grepInvert,
        array $testFiles,
        ?int $timeoutSeconds,
        array $options
    ): string {
        $this->assertTheme($theme);
        foreach ($testFiles as $testFile) {
            if (str_starts_with($testFile, '-')) {
                throw new InvalidArgumentException(sprintf(
                    'Playwright test file filter "%s" must not start with "-"; use playwright_options for options.',
                    $testFile
                ));
            }
        }

        $arguments = ['--theme=' . $theme];
        foreach ($projects as $project) {
            $arguments[] = '--project=' . $project;
        }
        if ($grep !== []) {
            $arguments[] = '--grep=' . $this->alternation($grep);
        }
        if ($grepInvert !== []) {
            $arguments[] = '--grep-invert=' . $this->alternation($grepInvert);
        }
        if ($timeoutSeconds !== null) {
            $arguments[] = '--global-timeout=' . ($timeoutSeconds * 1000);
        }
        array_push($arguments, ...$options, ...$testFiles);

        return 'ddev playwright test --ci ' . implode(' ', array_map('escapeshellarg', $arguments));
    }

    /**
     * Get the container path of the JSON results file the add-on writes for a theme.
     *
     * @param string $magentoRoot Magento root relative to the project root, e.g. "src"
     * @param string $theme
     * @return string
     * @throws InvalidArgumentException
     */
    public function resultsPath(string $magentoRoot, string $theme): string
    {
        $this->assertTheme($theme);
        $root = trim($magentoRoot, '/');

        return self::CONTAINER_ROOT . '/' . ($root === '' ? '' : $root . '/')
            . 'app/design/frontend/' . $theme . '/web/playwright/test-results/' . self::RESULTS_FILE;
    }

    /**
     * Build the command that prints a theme's JSON results file from inside the DDEV container.
     *
     * @param string $magentoRoot
     * @param string $theme
     * @return string
     * @throws InvalidArgumentException
     */
    public function readResultsCommand(string $magentoRoot, string $theme): string
    {
        return 'ddev exec cat ' . escapeshellarg($this->resultsPath($magentoRoot, $theme));
    }

    /**
     * Parse Playwright's JSON report.
     *
     * Returns stats (passed, failed, flaky, skipped, durationMs), top-level errors (messages, ANSI stripped),
     * every test and the failing tests. A test is a map of file, title, titlePath, project, status
     * (expected, unexpected, flaky, skipped) and error (empty unless it failed).
     *
     * @param string $json
     * @return array{
     *     stats: array{passed: int, failed: int, flaky: int, skipped: int, durationMs: float},
     *     errors: string[],
     *     tests: array<int, array{file: string, title: string, titlePath: string, project: string, status: string, error: string}>,
     *     failures: array<int, array{file: string, title: string, titlePath: string, project: string, status: string, error: string}>
     * }
     * @throws InvalidArgumentException
     */
    public function parseReport(string $json): array
    {
        try {
            $report = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Playwright JSON report is not valid JSON: ' . $e->getMessage(), 0, $e);
        }
        if (!is_array($report) || !is_array($report['stats'] ?? null)) {
            throw new InvalidArgumentException('Playwright JSON report has no stats.');
        }

        $stats = $report['stats'];
        $tests = [];
        foreach ((array) ($report['suites'] ?? []) as $suite) {
            array_push($tests, ...$this->collectTests((array) $suite, [], true));
        }
        $errors = [];
        foreach ((array) ($report['errors'] ?? []) as $error) {
            $errors[] = $this->stripAnsi((string) (((array) $error)['message'] ?? 'Unknown error'));
        }

        return [
            'stats' => [
                'passed' => (int) ($stats['expected'] ?? 0),
                'failed' => (int) ($stats['unexpected'] ?? 0),
                'flaky' => (int) ($stats['flaky'] ?? 0),
                'skipped' => (int) ($stats['skipped'] ?? 0),
                'durationMs' => (float) ($stats['duration'] ?? 0),
            ],
            'errors' => $errors,
            'tests' => $tests,
            'failures' => array_values(array_filter(
                $tests,
                static fn(array $test): bool => $test['status'] === 'unexpected'
            )),
        ];
    }

    /**
     * Whether a theme's run passed.
     *
     * @param int $exitCode
     * @param array<string, mixed>|null $parsed Result of parseReport(), null when the report could not be read
     * @return bool
     */
    public function passed(int $exitCode, ?array $parsed): bool
    {
        return $exitCode === 0
            && $parsed !== null
            && $parsed['stats']['failed'] === 0
            && $parsed['errors'] === [];
    }

    /**
     * Format the counts line, e.g. "12 passed, 1 failed, 1 flaky, 2 skipped (3m 12s)".
     *
     * @param array<string, mixed> $parsed Result of parseReport()
     * @return string
     */
    public function summaryLine(array $parsed): string
    {
        $stats = $parsed['stats'];
        $parts = [$stats['passed'] . ' passed'];
        foreach (['failed', 'flaky', 'skipped'] as $key) {
            if ($stats[$key] > 0) {
                $parts[] = $stats[$key] . ' ' . $key;
            }
        }
        $errorCount = count($parsed['errors']);
        if ($errorCount > 0) {
            $parts[] = $errorCount . ($errorCount === 1 ? ' error' : ' errors');
        }

        return implode(', ', $parts) . ' (' . $this->formatDuration($stats['durationMs']) . ')';
    }

    /**
     * Format a parsed report as output lines.
     *
     * Summary mode lists the top-level errors and the failing tests. Tests mode lists every test grouped by
     * spec file, with the details of failing tests.
     *
     * @param array<string, mixed> $parsed Result of parseReport()
     * @param string $mode REPORT_SUMMARY or REPORT_TESTS
     * @return string[]
     */
    public function formatReport(array $parsed, string $mode = self::REPORT_SUMMARY): array
    {
        $lines = [];
        foreach ($parsed['errors'] as $error) {
            $excerpt = $this->firstLines($error);
            $this->appendBlock($lines, [str_starts_with($excerpt, 'Error') ? $excerpt : 'Error: ' . $excerpt]);
        }

        if ($mode === self::REPORT_TESTS) {
            $byFile = [];
            foreach ($parsed['tests'] as $test) {
                $byFile[$test['file']][] = $test;
            }
            foreach ($byFile as $file => $tests) {
                $block = [(string) $file];
                foreach ($tests as $test) {
                    $block[] = ' ' . self::STATUS_MARKS[$test['status']] . ' ' . $test['title']
                        . ' [' . $test['project'] . ']';
                    array_push($block, ...$this->indentedError($test['error']));
                }
                $this->appendBlock($lines, $block);
            }

            return $lines;
        }

        foreach ($parsed['failures'] as $test) {
            $this->appendBlock($lines, [
                self::STATUS_MARKS['unexpected'] . ' [' . $test['project'] . '] ' . $test['titlePath'],
                ...$this->indentedError($test['error']),
            ]);
        }

        return $lines;
    }

    /**
     * Reject themes that are not <vendor>/<theme>, since the theme is interpolated into a path.
     *
     * @param string $theme
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertTheme(string $theme): void
    {
        if (preg_match(self::THEME_PATTERN, $theme) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Playwright theme "%s" must be in the form <vendor>/<theme>.', $theme)
            );
        }
    }

    /**
     * Recursively collect the tests of a suite.
     *
     * @param array<string, mixed> $suite
     * @param string[] $titles Titles of the parent describe blocks
     * @param bool $isFileSuite Whether the suite is a file-level suite, whose title is the file path
     * @return array<int, array{file: string, title: string, titlePath: string, project: string, status: string, error: string}>
     */
    private function collectTests(array $suite, array $titles, bool $isFileSuite): array
    {
        $file = (string) ($suite['file'] ?? $suite['title'] ?? '');
        $prefix = $isFileSuite ? [] : [...$titles, (string) ($suite['title'] ?? '')];

        $tests = [];
        foreach ((array) ($suite['specs'] ?? []) as $spec) {
            $specTitles = [...$prefix, (string) ($spec['title'] ?? '')];
            foreach ((array) ($spec['tests'] ?? []) as $test) {
                $status = (string) ($test['status'] ?? 'expected');
                $tests[] = [
                    'file' => (string) ($spec['file'] ?? $file),
                    'title' => implode(' › ', $specTitles),
                    'titlePath' => implode(' › ', [(string) ($spec['file'] ?? $file), ...$specTitles]),
                    'project' => (string) ($test['projectName'] ?? ''),
                    'status' => $status,
                    'error' => $status === 'unexpected' ? $this->testError((array) ($test['results'] ?? [])) : '',
                ];
            }
        }
        foreach ((array) ($suite['suites'] ?? []) as $child) {
            array_push($tests, ...$this->collectTests((array) $child, $prefix, false));
        }

        return $tests;
    }

    /**
     * Get the first lines of the last failing attempt's error message, ANSI escapes stripped.
     *
     * @param array<int, array<string, mixed>> $results
     * @return string
     */
    private function testError(array $results): string
    {
        foreach (array_reverse($results) as $result) {
            $message = ((array) (((array) ($result['errors'] ?? []))[0] ?? []))['message']
                ?? ((array) ($result['error'] ?? []))['message']
                ?? '';
            if ($message !== '') {
                return $this->firstLines($this->stripAnsi((string) $message));
            }
        }

        return '';
    }

    /**
     * Keep the first non-blank lines of a message.
     *
     * @param string $message
     * @return string
     */
    private function firstLines(string $message): string
    {
        $lines = array_filter(
            preg_split('/\R/', trim($message)) ?: [],
            static fn(string $line): bool => trim($line) !== ''
        );
        $lines = array_slice(array_values($lines), 0, self::ERROR_LINES);

        return rtrim(implode("\n", $lines));
    }

    /**
     * Remove ANSI escape sequences.
     *
     * @param string $text
     * @return string
     */
    private function stripAnsi(string $text): string
    {
        return preg_replace('/\x1b\[[0-9;?]*[A-Za-z]/', '', $text) ?? $text;
    }

    /**
     * Indent an error message under its test.
     *
     * @param string $error
     * @return string[]
     */
    private function indentedError(string $error): array
    {
        if ($error === '') {
            return [];
        }

        return array_map(
            static fn(string $line): string => rtrim('     ' . $line),
            preg_split('/\R/', $error) ?: []
        );
    }

    /**
     * Append a block of lines, separated from earlier output by a blank line.
     *
     * @param string[] $lines
     * @param string[] $block
     * @return void
     */
    private function appendBlock(array &$lines, array $block): void
    {
        if ($lines !== []) {
            $lines[] = '';
        }
        array_push($lines, ...$block);
    }

    /**
     * Format milliseconds as e.g. "3m 12s" or "5s".
     *
     * @param float $milliseconds
     * @return string
     */
    private function formatDuration(float $milliseconds): string
    {
        $seconds = (int) round($milliseconds / 1000);

        return $seconds >= 60 ? intdiv($seconds, 60) . 'm ' . ($seconds % 60) . 's' : $seconds . 's';
    }

    /**
     * Combine regex patterns into one alternation; a single pattern is returned unchanged.
     *
     * @param string[] $patterns
     * @return string
     */
    private function alternation(array $patterns): string
    {
        if (count($patterns) === 1) {
            return $patterns[0];
        }

        return implode('|', array_map(static fn(string $pattern): string => '(?:' . $pattern . ')', $patterns));
    }
}
