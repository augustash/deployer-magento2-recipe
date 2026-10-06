<?php

declare(strict_types=1);

namespace Augustash\Deployer\Test\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Augustash\Deployer\PhpUnitGate;

// phpcs:disable Generic.Files.LineLength.TooLong
// phpcs:disable Magento2.Functions.DiscouragedFunction

class PhpUnitGateTest extends TestCase
{
    private const SHA = 'abcdef1234567890abcdef1234567890abcdef12';

    private PhpUnitGate $gate;

    private string $fixtureRoot = '';

    protected function setUp(): void
    {
        $this->gate = new PhpUnitGate();
    }

    protected function tearDown(): void
    {
        if ($this->fixtureRoot !== '' && is_dir($this->fixtureRoot)) {
            $this->removeDirectory($this->fixtureRoot);
        }
        $this->fixtureRoot = '';
    }

    /**
     * Create a temporary Magento root fixture with test dirs and a PHPUnit bin.
     *
     * @return string
     */
    private function createFixture(): string
    {
        $this->fixtureRoot = sys_get_temp_dir() . '/phpunit-gate-' . uniqid('', true);
        mkdir($this->fixtureRoot . '/app/code', 0777, true);
        mkdir($this->fixtureRoot . '/local-src', 0777, true);
        mkdir($this->fixtureRoot . '/vendor/bin', 0777, true);
        touch($this->fixtureRoot . '/vendor/bin/phpunit');

        return $this->fixtureRoot;
    }

    /**
     * Create a test directory beneath the fixture root.
     *
     * @param string $root
     * @param string $path
     * @return void
     */
    private function createTestDir(string $root, string $path): void
    {
        mkdir($root . '/' . $path, 0777, true);
    }

    /**
     * Sample PHPUnit --testdox output for a passing module.
     *
     * @return string
     */
    private function testdoxOutput(): string
    {
        return "PHPUnit 10.5.64 by Sebastian Bergmann and contributors.\n\n"
            . "Runtime:       PHP 8.3.32\n"
            . "Configuration: /var/www/html/src/dev/tests/unit/phpunit.xml.dist\n\n"
            . "Time: 00:00.040, Memory: 8.00 MB\n\n"
            . "Validator (Spe\\Foo\\Test\\Unit\\Service\\Validator)\n"
            . " ✔ Date range is invalid when disabled\n\n"
            . "OK (16 tests, 19 assertions)\n";
    }

    /**
     * Create a module with a Test/Unit directory and an etc/module.xml beneath the fixture root.
     *
     * @param string $root
     * @param string $path
     * @param string $name
     * @return void
     */
    private function createModule(string $root, string $path, string $name): void
    {
        $this->createTestDir($root, $path . '/Test/Unit');
        mkdir($root . '/' . $path . '/etc', 0777, true);
        file_put_contents(
            $root . '/' . $path . '/etc/module.xml',
            '<?xml version="1.0"?><config><module name="' . $name . '"><sequence>'
            . '<module name="Magento_Store"/></sequence></module></config>'
        );
    }

    /**
     * Write an app/etc/config.php with the given module flags beneath the fixture root.
     *
     * @param string $root
     * @param array<string, int> $modules
     * @return void
     */
    private function writeConfig(string $root, array $modules): void
    {
        mkdir($root . '/app/etc', 0777, true);
        file_put_contents(
            $root . '/app/etc/config.php',
            '<?php return ' . var_export(['modules' => $modules], true) . ';'
        );
    }

    /**
     * Recursively remove a directory.
     *
     * @param string $dir
     * @return void
     */
    private function removeDirectory(string $dir): void
    {
        $items = scandir($dir) ?: [];
        foreach (array_diff($items, ['.', '..']) as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Build the command using the default deploy settings.
     *
     * @return string
     */
    private function defaultCommand(): string
    {
        return $this->gate->buildCommand(
            'src',
            'vendor/bin/phpunit',
            'dev/tests/unit/phpunit.xml.dist',
            ['app/code', 'local-src'],
            ['--no-coverage']
        );
    }

    public function testBuildCommandIncludesConfigFlagAndAllTestPaths(): void
    {
        $command = $this->defaultCommand();

        $this->assertStringContainsString("-c 'phpunit.xml.dist'", $command);
        $this->assertStringContainsString("'../../../app/code'", $command);
        $this->assertStringContainsString("'../../../local-src'", $command);
    }

    public function testBuildCommandChangesIntoConfigDirectoryFirst(): void
    {
        $this->assertSame(
            "cd 'src/dev/tests/unit' && '../../../vendor/bin/phpunit' -c 'phpunit.xml.dist' '--no-coverage' '../../../app/code' '../../../local-src'",
            $this->defaultCommand()
        );
    }

    public function testBuildCommandRelativizesBinAndPathsToConfigDirectory(): void
    {
        $command = $this->gate->buildCommand('src', 'vendor/bin/phpunit', 'phpunit.xml.dist', ['app/code'], []);

        $this->assertSame("cd 'src' && 'vendor/bin/phpunit' -c 'phpunit.xml.dist' 'app/code'", $command);

        $command = $this->gate->buildCommand('src', 'vendor/bin/phpunit', 'a/b/phpunit.xml.dist', ['app/code'], []);

        $this->assertStringContainsString("'../../vendor/bin/phpunit'", $command);
        $this->assertStringContainsString("'../../app/code'", $command);
    }

    #[DataProvider('invalidConfigProvider')]
    public function testBuildCommandThrowsWhenConfigPathIsAbsoluteOrEscapesRoot(string $config): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->gate->buildCommand('src', 'vendor/bin/phpunit', $config, ['app/code'], []);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidConfigProvider(): array
    {
        return [
            'absolute' => ['/etc/phpunit.xml'],
            'nested parent segment' => ['dev/../../phpunit.xml'],
            'parent segment' => ['../phpunit.xml'],
        ];
    }

    public function testBuildCommandPrefixesWrapperUnescapedWhenProvided(): void
    {
        $command = $this->gate->buildCommand(
            'src',
            'vendor/bin/phpunit',
            'dev/tests/unit/phpunit.xml.dist',
            ['app/code'],
            [],
            'ddev exec --dir /var/www/html/src/dev/tests/unit'
        );

        $this->assertStringContainsString(
            "&& ddev exec --dir /var/www/html/src/dev/tests/unit '../../../vendor/bin/phpunit' -c",
            $command
        );
    }

    public function testBuildCommandShellEscapesPathsContainingSpaces(): void
    {
        $command = $this->gate->buildCommand(
            'src',
            'vendor/bin/phpunit',
            'dev/tests/unit/phpunit.xml.dist',
            ['my code'],
            []
        );

        $this->assertStringContainsString("'../../../my code'", $command);
    }

    public function testBuildCommandAppendsExtraOptionsAfterPaths(): void
    {
        $command = $this->gate->buildCommand(
            'src',
            'vendor/bin/phpunit',
            'dev/tests/unit/phpunit.xml.dist',
            ['app/code'],
            ['--no-coverage', '--testdox']
        );

        $this->assertStringEndsWith("'--no-coverage' '--testdox' '../../../app/code'", $command);
    }

    public function testBuildCommandDefaultsToNoCoverageOption(): void
    {
        $this->assertContains('--no-coverage', PhpUnitGate::DEFAULT_OPTIONS);
    }

    public function testResolveTestDirsThrowsWhenPathListIsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->gate->resolveTestDirs($this->createFixture(), [], []);
    }

    #[DataProvider('escapingPathProvider')]
    public function testResolveTestDirsThrowsWhenIncludePathEscapesMagentoRoot(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->gate->resolveTestDirs($this->createFixture(), [$path], []);
    }

    #[DataProvider('escapingPathProvider')]
    public function testResolveTestDirsThrowsWhenExcludePathEscapesMagentoRoot(string $path): void
    {
        $root = $this->createFixture();
        $this->createTestDir($root, 'app/code/Spe/Foo/Test/Unit');

        $this->expectException(InvalidArgumentException::class);

        $this->gate->resolveTestDirs($root, ['app/code'], [$path]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function escapingPathProvider(): array
    {
        return [
            'absolute' => ['/etc'],
            'parent' => ['../etc'],
        ];
    }

    public function testResolveTestDirsFindsTestUnitDirectoriesBeneathIncludePaths(): void
    {
        $root = $this->createFixture();
        $this->createTestDir($root, 'app/code/Spe/Foo/Test/Unit');
        $this->createTestDir($root, 'local-src/Spe_Bar/src/Test/Unit');
        mkdir($root . '/app/code/Spe/NoTests', 0777, true);

        $this->assertSame(
            ['app/code/Spe/Foo/Test/Unit', 'local-src/Spe_Bar/src/Test/Unit'],
            $this->gate->resolveTestDirs($root, ['app/code', 'local-src'], [])
        );
    }

    public function testResolveTestDirsExpandsGlobIncludePaths(): void
    {
        $root = $this->createFixture();
        $this->createTestDir($root, 'vendor/augustash/module-a/src/Test/Unit');
        $this->createTestDir($root, 'vendor/augustash/module-b/Test/Unit');
        $this->createTestDir($root, 'vendor/other/module-c/Test/Unit');

        $this->assertSame(
            ['vendor/augustash/module-a/src/Test/Unit', 'vendor/augustash/module-b/Test/Unit'],
            $this->gate->resolveTestDirs($root, ['vendor/augustash/*'], [])
        );
    }

    public function testResolveTestDirsDropsDirectoriesUnderAnExcludedPath(): void
    {
        $root = $this->createFixture();
        $this->createTestDir($root, 'app/code/Spe/Foo/Test/Unit');
        $this->createTestDir($root, 'app/code/Amasty/Base/Test/Unit');
        $this->createTestDir($root, 'app/code/Amasty/Rules/Test/Unit');

        $this->assertSame(
            ['app/code/Spe/Foo/Test/Unit'],
            $this->gate->resolveTestDirs($root, ['app/code'], ['app/code/Amasty'])
        );
    }

    public function testResolveTestDirsSupportsGlobExcludePaths(): void
    {
        $root = $this->createFixture();
        $this->createTestDir($root, 'app/code/Spe/Foo/Test/Unit');
        $this->createTestDir($root, 'app/code/Spe/LegacyBar/Test/Unit');
        $this->createTestDir($root, 'app/code/Acme/LegacyBaz/Test/Unit');

        $this->assertSame(
            ['app/code/Spe/Foo/Test/Unit'],
            $this->gate->resolveTestDirs($root, ['app/code'], ['app/code/*/Legacy*'])
        );
    }

    public function testResolveTestDirsDoesNotTreatExcludeAsPlainStringPrefix(): void
    {
        $root = $this->createFixture();
        $this->createTestDir($root, 'app/code/Spe/Foo/Test/Unit');
        $this->createTestDir($root, 'app/code/Spe/FooBar/Test/Unit');

        $this->assertSame(
            ['app/code/Spe/FooBar/Test/Unit'],
            $this->gate->resolveTestDirs($root, ['app/code'], ['app/code/Spe/Foo'])
        );
    }

    public function testResolveTestDirsSkipsNestedVendorAndNodeModulesDirectories(): void
    {
        $root = $this->createFixture();
        $this->createTestDir($root, 'local-src/Spe_Bar/src/Test/Unit');
        $this->createTestDir($root, 'local-src/Spe_Bar/vendor/acme/lib/Test/Unit');
        $this->createTestDir($root, 'local-src/Spe_Bar/node_modules/pkg/Test/Unit');

        $this->assertSame(
            ['local-src/Spe_Bar/src/Test/Unit'],
            $this->gate->resolveTestDirs($root, ['local-src'], [])
        );
    }

    public function testResolveTestDirsIgnoresIncludePathsThatDoNotExist(): void
    {
        $root = $this->createFixture();
        $this->createTestDir($root, 'app/code/Spe/Foo/Test/Unit');

        $this->assertSame(
            ['app/code/Spe/Foo/Test/Unit'],
            $this->gate->resolveTestDirs($root, ['app/code', 'missing/dir', 'vendor/nobody/*'], [])
        );
    }

    public function testResolveTestDirsThrowsWhenNoTestDirectoriesRemain(): void
    {
        $root = $this->createFixture();
        $this->createTestDir($root, 'app/code/Amasty/Base/Test/Unit');

        $this->expectException(InvalidArgumentException::class);

        $this->gate->resolveTestDirs($root, ['app/code', 'missing/dir'], ['app/code/Amasty']);
    }

    public function testEnabledModulesReturnsOnlyModulesFlaggedOneInConfig(): void
    {
        $root = $this->createFixture();
        $this->writeConfig($root, ['Spe_Foo' => 1, 'Spe_Off' => 0, 'Acme_Bar' => 1]);

        $this->assertSame(['Spe_Foo', 'Acme_Bar'], $this->gate->enabledModules($root . '/app/etc/config.php'));
    }

    public function testEnabledModulesThrowsWhenConfigFileIsMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->gate->enabledModules($this->createFixture() . '/app/etc/config.php');
    }

    public function testModuleNameForTestDirReadsNameFromModuleXml(): void
    {
        $root = $this->createFixture();
        $this->createModule($root, 'local-src/Spe_Foo/src', 'Spe_Foo');

        $this->assertSame('Spe_Foo', $this->gate->moduleNameForTestDir($root, 'local-src/Spe_Foo/src/Test/Unit'));
    }

    public function testModuleNameForTestDirReturnsNullWithoutModuleXml(): void
    {
        $root = $this->createFixture();
        $this->createTestDir($root, 'vendor/augustash/lib/Test/Unit');

        $this->assertNull($this->gate->moduleNameForTestDir($root, 'vendor/augustash/lib/Test/Unit'));
    }

    public function testPartitionByEnabledModuleKeepsEnabledModules(): void
    {
        $root = $this->createFixture();
        $this->createModule($root, 'app/code/Spe/Foo', 'Spe_Foo');

        [$run] = $this->gate->partitionByEnabledModule($root, ['app/code/Spe/Foo/Test/Unit'], ['Spe_Foo']);

        $this->assertSame(['app/code/Spe/Foo/Test/Unit'], $run);
    }

    public function testPartitionByEnabledModuleSkipsDisabledModulesWithTheirName(): void
    {
        $root = $this->createFixture();
        $this->createModule($root, 'app/code/Spe/Foo', 'Spe_Foo');
        $this->createModule($root, 'app/code/Spe/Off', 'Spe_Off');

        [$run, $skipped] = $this->gate->partitionByEnabledModule(
            $root,
            ['app/code/Spe/Foo/Test/Unit', 'app/code/Spe/Off/Test/Unit'],
            ['Spe_Foo']
        );

        $this->assertSame(['app/code/Spe/Foo/Test/Unit'], $run);
        $this->assertSame(['app/code/Spe/Off/Test/Unit' => 'Spe_Off'], $skipped);
    }

    public function testPartitionByEnabledModuleSkipsDirectoriesThatAreNotMagentoModules(): void
    {
        $root = $this->createFixture();
        $this->createTestDir($root, 'vendor/augustash/lib/Test/Unit');

        [$run, $skipped] = $this->gate->partitionByEnabledModule($root, ['vendor/augustash/lib/Test/Unit'], []);

        $this->assertSame([], $run);
        $this->assertSame(['vendor/augustash/lib/Test/Unit' => null], $skipped);
    }

    #[DataProvider('booleanSettingProvider')]
    public function testIsTruthyParsesBooleanSettings(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, $this->gate->isTruthy($value));
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function booleanSettingProvider(): array
    {
        return [
            'bool false' => [false, false],
            'bool true' => [true, true],
            'string false' => ['false', false],
            'string one' => ['1', true],
            'string zero' => ['0', false],
        ];
    }

    public function testReportOptionsUseTestdoxWithoutProgressOrColors(): void
    {
        $this->assertSame(['--testdox', '--no-progress', '--colors=never'], PhpUnitGate::REPORT_OPTIONS);
    }

    public function testFormatReportDropsPhpunitHeaderTimingAndSummaryLines(): void
    {
        $this->assertSame(
            ['Validator', ' ✔ Date range is invalid when disabled'],
            $this->gate->formatReport($this->testdoxOutput())
        );
    }

    public function testFormatReportShortensClassHeadersToTheReadableName(): void
    {
        $output = "Quote Resolver (Spe\\Foo\\Test\\Unit\\Service\\QuoteResolver)\n ✔ Loads quote\n";

        $this->assertSame(['Quote Resolver', ' ✔ Loads quote'], $this->gate->formatReport($output));
    }

    public function testFormatReportKeepsFailureDetails(): void
    {
        $output = "Transport (A\\Transport)\n ✘ Sends message\n   │\n   │ TypeError: bad argument\n"
            . "\nERRORS!\nTests: 1, Assertions: 0, Errors: 1.\n";

        $this->assertSame(
            ['Transport', ' ✘ Sends message', '   │', '   │ TypeError: bad argument'],
            $this->gate->formatReport($output)
        );
    }

    public function testFormatReportCollapsesRepeatedBlankLines(): void
    {
        $output = "A (X\\A)\n ✔ One\n\n\n\nB (X\\B)\n ✔ Two\n";

        $this->assertSame(['A', ' ✔ One', '', 'B', ' ✔ Two'], $this->gate->formatReport($output));
    }

    public function testFormatReportFailuresOnlyKeepsFailingTestsWithDetailsAndTheirClass(): void
    {
        $output = "Config (A\\\\Config)\n ✔ Reads domain\n\n"
            . "Transport (A\\\\Transport)\n ✔ Builds params\n ✘ Sends message\n   │\n   │ TypeError: bad\n"
            . " ✔ Skips when disabled\n\nERRORS!\nTests: 4, Assertions: 3, Errors: 1.\n";

        $this->assertSame(
            ['Transport', ' ✘ Sends message', '   │', '   │ TypeError: bad'],
            $this->gate->formatReport($output, true)
        );
    }

    public function testFormatReportFailuresOnlyKeepsCrashOutputWithoutTestLines(): void
    {
        $output = "PHP Fatal error:  Cannot redeclare foo() in /x.php on line 3\n";

        $this->assertSame(
            ['PHP Fatal error:  Cannot redeclare foo() in /x.php on line 3'],
            $this->gate->formatReport($output, true)
        );
    }

    public function testFormatReportFailuresOnlyReturnsNothingWhenAllTestsPass(): void
    {
        $this->assertSame([], $this->gate->formatReport($this->testdoxOutput(), true));
    }

    #[DataProvider('reportModeProvider')]
    public function testNormalizeReportModeAcceptsKnownModes(mixed $value, string $expected): void
    {
        $this->assertSame($expected, $this->gate->normalizeReportMode($value));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function reportModeProvider(): array
    {
        return [
            'empty defaults to summary' => ['', PhpUnitGate::REPORT_SUMMARY],
            'mixed case tests' => [' Tests ', PhpUnitGate::REPORT_TESTS],
            'null defaults to summary' => [null, PhpUnitGate::REPORT_SUMMARY],
            'summary' => ['summary', PhpUnitGate::REPORT_SUMMARY],
            'tests' => ['tests', PhpUnitGate::REPORT_TESTS],
        ];
    }

    public function testNormalizeReportModeThrowsForUnknownMode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->gate->normalizeReportMode('verbose');
    }

    #[DataProvider('summaryLineProvider')]
    public function testSummaryLineReturnsPhpunitResultLine(string $output, string $expected): void
    {
        $this->assertSame($expected, $this->gate->summaryLine($output));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function summaryLineProvider(): array
    {
        return [
            'errors' => ["ERRORS!\nTests: 43, Assertions: 60, Errors: 10.\n", 'Tests: 43, Assertions: 60, Errors: 10.'],
            'no output' => ['', ''],
            'no tests' => ["No tests executed!\n", 'No tests executed!'],
            'ok' => ["Validator\n ✔ One\n\nOK (16 tests, 19 assertions)\n", 'OK (16 tests, 19 assertions)'],
            'ok with issues' => [
                "OK, but there were issues!\nTests: 3, Assertions: 3, Deprecations: 1.\n",
                'Tests: 3, Assertions: 3, Deprecations: 1.',
            ],
        ];
    }

    public function testPhpunitBinExistsChecksFileOnHost(): void
    {
        $root = $this->createFixture();

        $this->assertTrue($this->gate->phpunitBinExists($root, 'vendor/bin/phpunit'));
        $this->assertFalse($this->gate->phpunitBinExists($root, 'vendor/bin/nope'));
    }

    /**
     * @param mixed $input
     * @param string[] $expected
     */
    #[DataProvider('listProvider')]
    public function testNormalizeListSplitsCommaSeparatedString(mixed $input, array $expected): void
    {
        $this->assertSame($expected, $this->gate->normalizeList($input));
    }

    /**
     * @return array<string, array{mixed, string[]}>
     */
    public static function listProvider(): array
    {
        return [
            'array' => [['app/code'], ['app/code']],
            'comma separated' => ['app/code,local-src', ['app/code', 'local-src']],
            'padded with empties' => [' app/code , ', ['app/code']],
        ];
    }

    public function testNormalizeTimeoutReturnsIntForNumericString(): void
    {
        $this->assertSame(900, $this->gate->normalizeTimeout('900'));
    }

    #[DataProvider('emptyTimeoutProvider')]
    public function testNormalizeTimeoutReturnsNullForEmptyOrZero(mixed $value): void
    {
        $this->assertNull($this->gate->normalizeTimeout($value));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function emptyTimeoutProvider(): array
    {
        return [
            'empty string' => [''],
            'int zero' => [0],
            'null' => [null],
            'string zero' => ['0'],
        ];
    }

    public function testNormalizeTimeoutThrowsForNonNumericValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->gate->normalizeTimeout('soon');
    }

    #[DataProvider('truthySkipProvider')]
    public function testIsSkipRequestedReturnsTrueForTruthyStrings(mixed $value): void
    {
        $this->assertTrue($this->gate->isSkipRequested($value));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function truthySkipProvider(): array
    {
        return [
            'on' => ['on'],
            'one' => ['1'],
            'true bool' => [true],
            'true string' => ['true'],
            'yes' => ['yes'],
        ];
    }

    #[DataProvider('falsySkipProvider')]
    public function testIsSkipRequestedReturnsFalseForFalsyOrMissingValues(mixed $value): void
    {
        $this->assertFalse($this->gate->isSkipRequested($value));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function falsySkipProvider(): array
    {
        return [
            'empty' => [''],
            'false bool' => [false],
            'false string' => ['false'],
            'no' => ['no'],
            'null' => [null],
            'zero' => ['0'],
        ];
    }

    public function testRequiresSkipConfirmationReturnsTrueForProductionStage(): void
    {
        $this->assertTrue($this->gate->requiresSkipConfirmation('production'));
    }

    #[DataProvider('nonProductionStageProvider')]
    public function testRequiresSkipConfirmationReturnsFalseForOtherOrMissingStages(?string $stage): void
    {
        $this->assertFalse($this->gate->requiresSkipConfirmation($stage));
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function nonProductionStageProvider(): array
    {
        return [
            'empty' => [''],
            'null' => [null],
            'staging' => ['staging'],
        ];
    }

    public function testCheckoutWarningsIsEmptyWhenBranchMatchesAndTreeClean(): void
    {
        $this->assertSame([], $this->gate->checkoutWarnings('develop', 'develop', self::SHA, false));
    }

    public function testCheckoutWarningsReportsBranchMismatch(): void
    {
        $warnings = $this->gate->checkoutWarnings('master', 'develop', self::SHA, false);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('master', $warnings[0]);
        $this->assertStringContainsString('develop', $warnings[0]);
    }

    public function testCheckoutWarningsReportsDirtyWorkingTree(): void
    {
        $warnings = $this->gate->checkoutWarnings('develop', 'develop', self::SHA, true);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('uncommitted', $warnings[0]);
    }

    #[DataProvider('unsetTargetProvider')]
    public function testCheckoutWarningsSkipsBranchCheckWhenHostBranchUnset(?string $target): void
    {
        $this->assertSame([], $this->gate->checkoutWarnings($target, 'develop', self::SHA, false));
    }

    /**
     * @return array<string, array{string|null}>
     */
    public static function unsetTargetProvider(): array
    {
        return [
            'empty' => [''],
            'HEAD fallback' => ['HEAD'],
            'null' => [null],
        ];
    }

    public function testCheckoutWarningsTreatsTargetMatchingLocalShaAsMatch(): void
    {
        $this->assertSame([], $this->gate->checkoutWarnings(self::SHA, 'HEAD', self::SHA, false));
        $this->assertSame([], $this->gate->checkoutWarnings(substr(self::SHA, 0, 7), 'HEAD', self::SHA, false));
        $this->assertNotSame([], $this->gate->checkoutWarnings(substr(self::SHA, 0, 6), 'develop', self::SHA, false));
    }

    public function testCheckoutWarningsReportsDetachedHeadWhenTargetIsABranch(): void
    {
        $warnings = $this->gate->checkoutWarnings('develop', 'HEAD', self::SHA, false);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('detached HEAD', $warnings[0]);
    }

    public function testCheckoutWarningsReportsBothMismatchAndDirtyTogether(): void
    {
        $this->assertCount(2, $this->gate->checkoutWarnings('master', 'develop', self::SHA, true));
    }
}
