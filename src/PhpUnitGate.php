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

// phpcs:disable Magento2.Functions.DiscouragedFunction

/**
 * Command building and input validation for the deploy PHPUnit gate.
 */
class PhpUnitGate extends AbstractGate
{
    public const DEFAULT_OPTIONS = ['--no-coverage'];

    /**
     * Options that make PHPUnit print readable per-test results that the gate reformats.
     */
    public const REPORT_OPTIONS = ['--testdox', '--no-progress', '--colors=never'];

    private const HEADER_PATTERN = '/^(PHPUnit \\d|Runtime:|Configuration:|Time: )/';

    private const RESULT_PATTERN = '/^(OK \\(|OK, but |Tests: |No tests executed!|[A-Z]+!$)/';

    /**
     * Build the shell command that runs PHPUnit from the config file's directory.
     *
     * The bin, config and paths are relative to the Magento root. The wrapper is a trusted raw shell prefix.
     *
     * @param string $magentoRoot
     * @param string $phpunitBin
     * @param string $config
     * @param string[] $paths
     * @param string[] $options
     * @param string $wrapper
     * @return string
     * @throws InvalidArgumentException
     */
    public function buildCommand(
        string $magentoRoot,
        string $phpunitBin,
        string $config,
        array $paths,
        array $options,
        string $wrapper = ''
    ): string {
        if (str_starts_with($config, '/') || in_array('..', explode('/', $config), true)) {
            throw new InvalidArgumentException(
                sprintf('PHPUnit config "%s" must be relative to the Magento root and must not contain "..".', $config)
            );
        }

        $configDir = dirname($config);
        $prefix = $configDir === '.' ? '' : str_repeat('../', count(explode('/', $configDir)));
        $workingDir = $configDir === '.' ? $magentoRoot : $magentoRoot . '/' . $configDir;

        $parts = [];
        if ($wrapper !== '') {
            $parts[] = $wrapper;
        }
        $parts[] = escapeshellarg($prefix . $phpunitBin);
        $parts[] = '-c ' . escapeshellarg(basename($config));
        foreach ($options as $option) {
            $parts[] = escapeshellarg($option);
        }
        foreach ($paths as $path) {
            $parts[] = escapeshellarg($prefix . $path);
        }

        return 'cd ' . escapeshellarg($workingDir) . ' && ' . implode(' ', $parts);
    }

    /**
     * Find the Test/Unit directories to run beneath the Magento root.
     *
     * Include and exclude entries are paths or glob patterns relative to the Magento root. A Test/Unit directory
     * is dropped when it, or any of its parent directories, matches an exclude pattern. Nested vendor and
     * node_modules directories inside an include path are not searched.
     *
     * @param string $baseDir
     * @param string[] $includes
     * @param string[] $excludes
     * @return string[] Test directories relative to the Magento root, sorted
     * @throws InvalidArgumentException
     */
    public function resolveTestDirs(string $baseDir, array $includes, array $excludes): array
    {
        if ($includes === []) {
            throw new InvalidArgumentException('No PHPUnit test paths configured.');
        }
        foreach ([...$includes, ...$excludes] as $path) {
            $this->assertRelativePath($path);
        }

        $testDirs = [];
        foreach ($includes as $pattern) {
            foreach (glob($baseDir . '/' . $pattern, GLOB_ONLYDIR) ?: [] as $dir) {
                $relative = substr($dir, strlen($baseDir) + 1);
                foreach ($this->findTestDirs($baseDir, $relative) as $testDir) {
                    if (!$this->isExcluded($testDir, $excludes)) {
                        $testDirs[$testDir] = $testDir;
                    }
                }
            }
        }

        if ($testDirs === []) {
            throw new InvalidArgumentException(sprintf(
                'No Test/Unit directories found in the configured PHPUnit test paths: %s',
                implode(', ', $includes)
            ));
        }
        sort($testDirs);

        return array_values($testDirs);
    }

    /**
     * Read the names of the modules enabled in Magento's app/etc/config.php.
     *
     * @param string $configFile
     * @return string[]
     * @throws InvalidArgumentException
     */
    public function enabledModules(string $configFile): array
    {
        if (!is_file($configFile)) {
            throw new InvalidArgumentException(sprintf('Magento config file not found: %s', $configFile));
        }
        // phpcs:ignore Magento2.Security.IncludeFile
        $config = include $configFile;
        $modules = is_array($config) && is_array($config['modules'] ?? null) ? $config['modules'] : [];

        return array_map('strval', array_keys(array_filter($modules, static fn($flag): bool => (int) $flag === 1)));
    }

    /**
     * Read the module name from the etc/module.xml of the module that owns a Test/Unit directory.
     *
     * @param string $baseDir
     * @param string $testDir Test/Unit directory relative to the Magento root
     * @return string|null Null when the directory does not belong to a Magento module
     */
    public function moduleNameForTestDir(string $baseDir, string $testDir): ?string
    {
        $moduleXml = $baseDir . '/' . dirname($testDir, 2) . '/etc/module.xml';
        if (!is_file($moduleXml)) {
            return null;
        }
        $xml = simplexml_load_file($moduleXml);
        $name = $xml === false ? '' : (string) ($xml->module['name'] ?? '');

        return $name === '' ? null : $name;
    }

    /**
     * Split Test/Unit directories into those of enabled modules and those to skip.
     *
     * @param string $baseDir
     * @param string[] $testDirs
     * @param string[] $enabledModules
     * @return array{0: string[], 1: array<string, string|null>} Directories to run, and skipped directories
     *     mapped to their module name (null when not a Magento module)
     */
    public function partitionByEnabledModule(string $baseDir, array $testDirs, array $enabledModules): array
    {
        $run = [];
        $skipped = [];
        foreach ($testDirs as $testDir) {
            $module = $this->moduleNameForTestDir($baseDir, $testDir);
            if ($module !== null && in_array($module, $enabledModules, true)) {
                $run[] = $testDir;
            } else {
                $skipped[$testDir] = $module;
            }
        }

        return [$run, $skipped];
    }

    /**
     * Reduce PHPUnit --testdox output to the test classes, their tests and any failure details.
     *
     * With $failuresOnly, passing tests are dropped and only failing tests (with their details and
     * test class) and any output that is not a test result, such as a PHP fatal error, are kept.
     *
     * @param string $output
     * @param bool $failuresOnly
     * @return string[]
     */
    public function formatReport(string $output, bool $failuresOnly = false): array
    {
        $lines = [];
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = rtrim($line);
            if (preg_match(self::HEADER_PATTERN, $line) || preg_match(self::RESULT_PATTERN, $line)) {
                continue;
            }
            if ($line === '' && ($lines === [] || end($lines) === '')) {
                continue;
            }
            $lines[] = preg_replace('/^(\S.*?) \([\w\\\\]+\)$/', '$1', $line) ?? $line;
        }
        while ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return $failuresOnly ? $this->onlyFailures($lines) : $lines;
    }

    /**
     * Return PHPUnit's final result line, e.g. "OK (16 tests, 19 assertions)".
     *
     * @param string $output
     * @return string Empty when PHPUnit printed no result
     */
    public function summaryLine(string $output): string
    {
        $summary = '';
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $line = trim($line);
            if (preg_match('/^(OK \\(|Tests: |No tests executed!)/', $line)) {
                $summary = $line;
            }
        }

        return $summary;
    }

    /**
     * Check that the PHPUnit binary exists on the host.
     *
     * @param string $magentoRoot
     * @param string $phpunitBin
     * @return bool
     */
    public function phpunitBinExists(string $magentoRoot, string $phpunitBin): bool
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        return is_file($magentoRoot . '/' . $phpunitBin);
    }

    /**
     * Reject absolute paths and paths that leave the Magento root.
     *
     * @param string $path
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertRelativePath(string $path): void
    {
        if (str_starts_with($path, '/') || in_array('..', explode('/', $path), true)) {
            throw new InvalidArgumentException(
                sprintf('PHPUnit test path "%s" must be relative to the Magento root.', $path)
            );
        }
    }

    /**
     * Recursively collect Test/Unit directories beneath a directory.
     *
     * @param string $baseDir
     * @param string $relative
     * @return string[]
     */
    private function findTestDirs(string $baseDir, string $relative): array
    {
        if (str_ends_with('/' . $relative, '/Test/Unit')) {
            return [$relative];
        }

        $found = [];
        foreach (scandir($baseDir . '/' . $relative) ?: [] as $entry) {
            if (in_array($entry, ['.', '..', 'vendor', 'node_modules'], true)
                || !is_dir($baseDir . '/' . $relative . '/' . $entry)
            ) {
                continue;
            }
            array_push($found, ...$this->findTestDirs($baseDir, $relative . '/' . $entry));
        }

        return $found;
    }

    /**
     * Whether a directory or any of its parent directories matches an exclude pattern.
     *
     * @param string $dir
     * @param string[] $excludes
     * @return bool
     */
    private function isExcluded(string $dir, array $excludes): bool
    {
        $segments = explode('/', $dir);
        for ($i = 1, $count = count($segments); $i <= $count; $i++) {
            $candidate = implode('/', array_slice($segments, 0, $i));
            foreach ($excludes as $pattern) {
                if (fnmatch(rtrim($pattern, '/'), $candidate, FNM_PATHNAME)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Keep failing tests with their details and test class, plus any output that is not a test result.
     *
     * @param string[] $lines Lines from formatReport()
     * @return string[]
     */
    private function onlyFailures(array $lines): array
    {
        $kept = [];
        $pendingHeader = null;
        $inFailure = false;
        foreach ($lines as $i => $line) {
            if ($line === '') {
                $pendingHeader = null;
                $inFailure = false;
            } elseif (str_starts_with($line, ' ✘')) {
                if ($pendingHeader !== null) {
                    if ($kept !== []) {
                        $kept[] = '';
                    }
                    $kept[] = $pendingHeader;
                    $pendingHeader = null;
                }
                $kept[] = $line;
                $inFailure = true;
            } elseif ($this->isTestResultLine($line)) {
                // Any other test result: passed, skipped, incomplete or risky.
                $inFailure = false;
            } elseif (str_starts_with($line, '  ')) {
                if ($inFailure) {
                    $kept[] = $line;
                }
            } elseif (array_key_exists($i + 1, $lines) && $this->isTestResultLine($lines[$i + 1])) {
                $pendingHeader = $line;
            } else {
                $kept[] = $line;
            }
        }

        return $kept;
    }

    /**
     * Whether a --testdox line is a single test's result, e.g. " ✔ Loads quote".
     *
     * @param string $line
     * @return bool
     */
    private function isTestResultLine(string $line): bool
    {
        return str_starts_with($line, ' ') && !str_starts_with($line, '  ');
    }
}
