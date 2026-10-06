<?php

declare(strict_types=1);

namespace Augustash\Deployer\Test\Unit;

use Augustash\Deployer\AbstractGate;
use Augustash\Deployer\PhpUnitGate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.Files.LineLength.TooLong

class AbstractGateTest extends TestCase
{
    private const SHA = 'abcdef1234567890abcdef1234567890abcdef12';

    private AbstractGate $gate;

    protected function setUp(): void
    {
        $this->gate = new class extends AbstractGate {
        };
    }

    public function testAbstractGateHelpersAreInheritedByPhpUnitGate(): void
    {
        $gate = new PhpUnitGate();

        $this->assertInstanceOf(AbstractGate::class, $gate);
        $this->assertSame(['a', 'b'], $gate->normalizeList('a, b'));
        $this->assertSame(30, $gate->normalizeTimeout('30'));
        $this->assertTrue($gate->isTruthy('yes'));
        $this->assertTrue($gate->isSkipRequested('1'));
        $this->assertTrue($gate->requiresSkipConfirmation('production'));
        $this->assertSame([], $gate->checkoutWarnings('develop', 'develop', self::SHA, false));
        $this->assertSame(AbstractGate::REPORT_TESTS, $gate->normalizeReportMode('tests'));
    }

    public function testPhpUnitGateReportConstantsStillResolve(): void
    {
        $this->assertSame('summary', PhpUnitGate::REPORT_SUMMARY);
        $this->assertSame('tests', PhpUnitGate::REPORT_TESTS);
    }

    public function testNormalizeTimeoutNamesTheGivenSettingInItsError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('playwright_timeout must be');

        $this->gate->normalizeTimeout('soon', 'playwright_timeout');
    }

    public function testNormalizeTimeoutNamesPhpunitTimeoutByDefault(): void
    {
        $this->expectExceptionMessage('phpunit_timeout must be');

        $this->gate->normalizeTimeout('soon');
    }

    public function testNormalizeReportModeNamesTheGivenSettingInItsError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('playwright_report must be');

        $this->gate->normalizeReportMode('verbose', 'playwright_report');
    }

    public function testNormalizeReportModeNamesPhpunitReportByDefault(): void
    {
        $this->expectExceptionMessage('phpunit_report must be');

        $this->gate->normalizeReportMode('verbose');
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
