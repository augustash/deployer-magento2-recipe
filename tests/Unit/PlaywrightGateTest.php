<?php

declare(strict_types=1);

namespace Augustash\Deployer\Test\Unit;

use Augustash\Deployer\PlaywrightGate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.Files.LineLength.TooLong
// phpcs:disable Magento2.Functions.DiscouragedFunction

class PlaywrightGateTest extends TestCase
{
    private const THEME = 'Streichers/HyvaCspFrontend';

    private PlaywrightGate $gate;

    protected function setUp(): void
    {
        $this->gate = new PlaywrightGate();
    }

    /**
     * Read a Playwright JSON report fixture.
     *
     * @param string $name
     * @return string
     */
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/_files/playwright/' . $name . '.json');
    }

    /**
     * Parse a Playwright JSON report fixture.
     *
     * @param string $name
     * @return array<string, mixed>
     */
    private function parsed(string $name): array
    {
        return $this->gate->parseReport($this->fixture($name));
    }

    public function testBuildCommandIncludesCiThemeAndProjects(): void
    {
        $this->assertSame(
            "ddev playwright test --ci '--theme=Streichers/HyvaCspFrontend' '--project=chromium' '--project=firefox'",
            $this->gate->buildCommand(self::THEME, ['chromium', 'firefox'], '', null, [])
        );
    }

    public function testBuildCommandShellEscapesGrepAndOptions(): void
    {
        $command = $this->gate->buildCommand(self::THEME, ['chromium'], "it's; rm -rf /", null, ['--repeat-each=2', 'a b']);

        $this->assertStringContainsString("'--grep=it'\\''s; rm -rf /'", $command);
        $this->assertStringEndsWith("'--repeat-each=2' 'a b'", $command);
    }

    public function testBuildCommandAddsGlobalTimeoutInMillisecondsWhenTimeoutSet(): void
    {
        $this->assertStringContainsString(
            "'--global-timeout=600000'",
            $this->gate->buildCommand(self::THEME, ['chromium'], '', 600, [])
        );
        $this->assertStringNotContainsString(
            'global-timeout',
            $this->gate->buildCommand(self::THEME, ['chromium'], '', null, [])
        );
    }

    #[DataProvider('invalidThemes')]
    public function testBuildCommandRejectsThemeOutsideVendorThemeFormat(string $theme): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->gate->buildCommand($theme, ['chromium'], '', null, []);
    }

    /**
     * @return array<string, string[]>
     */
    public static function invalidThemes(): array
    {
        return [
            'no vendor' => ['HyvaCspFrontend'],
            'traversal' => ['../etc/passwd'],
            'three segments' => ['A/B/C'],
            'shell chars' => ['A/B;ls'],
            'empty' => [''],
        ];
    }

    #[DataProvider('magentoRoots')]
    public function testResultsPathIsContainerPathUnderVarWwwHtml(string $root, string $expected): void
    {
        $this->assertSame($expected, $this->gate->resultsPath($root, self::THEME));
    }

    /**
     * @return array<string, string[]>
     */
    public static function magentoRoots(): array
    {
        $tail = 'app/design/frontend/Streichers/HyvaCspFrontend/web/playwright/test-results/deploy-gate.json';

        return [
            'empty' => ['', '/var/www/html/' . $tail],
            'src' => ['src', '/var/www/html/src/' . $tail],
            'src slash' => ['src/', '/var/www/html/src/' . $tail],
        ];
    }

    public function testReadResultsCommandCatsTheEscapedContainerPath(): void
    {
        $this->assertSame(
            "ddev exec cat '" . $this->gate->resultsPath('src', self::THEME) . "'",
            $this->gate->readResultsCommand('src', self::THEME)
        );
    }

    public function testParseReportFallsBackWhenAGlobalErrorHasNoMessage(): void
    {
        $json = '{"suites":[],"errors":[{"stack":"at globalSetup"}],'
            . '"stats":{"expected":0,"unexpected":0,"flaky":0,"skipped":0,"duration":5}}';

        $this->assertSame(['Unknown error'], $this->gate->parseReport($json)['errors']);
    }

    public function testParseReportExposesTopLevelErrorsWithZeroTests(): void
    {
        $parsed = $this->parsed('global-setup-error');

        $this->assertSame([], $parsed['tests']);
        $this->assertCount(1, $parsed['errors']);
        $this->assertStringContainsString('ERR_CONNECTION_REFUSED', $parsed['errors'][0]);
        $this->assertSame('0 passed, 1 error (2s)', $this->gate->summaryLine($parsed));
        $this->assertStringContainsString('ERR_CONNECTION_REFUSED', implode("\n", $this->gate->formatReport($parsed)));
    }

    public function testPassedRequiresZeroExitNoUnexpectedAndNoErrors(): void
    {
        $this->assertTrue($this->gate->passed(0, $this->parsed('passing')));
        $this->assertFalse($this->gate->passed(1, $this->parsed('passing')));
        $this->assertFalse($this->gate->passed(0, null));
        $this->assertFalse($this->gate->passed(0, $this->parsed('failures')));
        $this->assertFalse($this->gate->passed(0, $this->parsed('global-setup-error')));
    }

    public function testParseReportCountsPassedFailedFlakyAndSkippedFromStats(): void
    {
        $this->assertSame(
            ['passed' => 1, 'failed' => 1, 'flaky' => 1, 'skipped' => 2, 'durationMs' => 192000.0],
            $this->parsed('failures')['stats']
        );
    }

    public function testParseReportCollectsFailuresWithTitlePathProjectAndFirstErrorLines(): void
    {
        $failures = $this->parsed('failures')['failures'];

        $this->assertCount(1, $failures);
        $this->assertSame('base-tests/checkout.spec.ts', $failures[0]['file']);
        $this->assertSame('base-tests/checkout.spec.ts › Add coupon', $failures[0]['titlePath']);
        $this->assertSame('chromium', $failures[0]['project']);
        $this->assertStringContainsString('Locator: #coupon', $failures[0]['error']);
        $this->assertStringNotContainsString('waiting even more', $failures[0]['error']);
    }

    public function testParseReportFallsBackToErrorMessageAndListsSetupFailures(): void
    {
        $parsed = $this->parsed('setup-failed');

        $this->assertSame('setup', $parsed['failures'][0]['project']);
        $this->assertSame('Login failed', $parsed['failures'][0]['error']);
        $this->assertSame(1, $parsed['stats']['skipped']);
    }

    public function testParseReportWalksNestedSuites(): void
    {
        $parsed = $this->parsed('nested');

        $this->assertCount(2, $parsed['tests']);
        $this->assertSame(
            'base-tests/cart.spec.ts › Cart › Coupons › Applies code',
            $parsed['failures'][0]['titlePath']
        );
    }

    public function testParseReportStripsAnsiEscapesFromErrors(): void
    {
        $parsed = $this->parsed('failures');

        $this->assertStringNotContainsString("\x1b", $parsed['failures'][0]['error']);
        $this->assertStringContainsString('Error: expect(locator).toBeVisible()', $parsed['failures'][0]['error']);
        $this->assertStringNotContainsString("\x1b", $this->parsed('global-setup-error')['errors'][0]);
    }

    public function testParseReportThrowsOnInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->gate->parseReport('not json');
    }

    public function testParseReportThrowsWhenStatsAreMissing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->gate->parseReport('{"suites":[]}');
    }

    public function testSummaryLineFormatsCountsAndDuration(): void
    {
        $this->assertSame('1 passed, 1 failed, 1 flaky, 2 skipped (3m 12s)', $this->gate->summaryLine($this->parsed('failures')));
        $this->assertSame('2 passed (1m 5s)', $this->gate->summaryLine($this->parsed('passing')));
    }

    public function testFormatReportSummaryModeListsOnlyFailures(): void
    {
        $output = implode("\n", $this->gate->formatReport($this->parsed('failures')));

        $this->assertStringContainsString('✘ [chromium] base-tests/checkout.spec.ts › Add coupon', $output);
        $this->assertStringContainsString('Locator: #coupon', $output);
        $this->assertStringNotContainsString('Place order', $output);
        $this->assertSame([], $this->gate->formatReport($this->parsed('passing')));
    }

    public function testFormatReportTestsModeListsEveryTestBySpecFile(): void
    {
        $lines = $this->gate->formatReport($this->parsed('failures'), PlaywrightGate::REPORT_TESTS);
        $output = implode("\n", $lines);

        $this->assertSame('base-tests/checkout.spec.ts', $lines[0]);
        $this->assertStringContainsString(' ✘ Add coupon [chromium]', $output);
        $this->assertStringContainsString(' ~ Place order [chromium]', $output);
        $this->assertStringContainsString(' ✔ Pay by PO [chromium]', $output);
        $this->assertStringContainsString(' - Pay by CC [chromium]', $output);
        $this->assertStringContainsString('Locator: #coupon', $output);
    }

    public function testRealMixedReportParsesCountsAndFailure(): void
    {
        $parsed = $this->parsed('real-1.63-mixed');

        $this->assertSame('2 passed, 1 failed, 1 flaky, 1 skipped (2s)', $this->gate->summaryLine($parsed));
        $this->assertCount(1, $parsed['failures']);
        $this->assertSame('probe.spec.ts › Group › fails', $parsed['failures'][0]['titlePath']);
        $this->assertFalse($this->gate->passed(1, $parsed));
    }

    public function testRealGlobalSetupErrorReportParsesAsFailureWithZeroTests(): void
    {
        $parsed = $this->parsed('real-1.63-global-setup-error');

        $this->assertSame([], $parsed['tests']);
        $this->assertFalse($this->gate->passed(1, $parsed));
        $this->assertSame(['Error: site is down'], $this->gate->formatReport($parsed));
    }

    public function testFailureExcerptSkipsBlankLinesSoTheReceivedLineIsKept(): void
    {
        $lines = $this->gate->formatReport($this->parsed('real-1.63-mixed'));

        $this->assertContains('     Received: "a"', $lines);
        $this->assertNotContains('', array_slice($lines, 1));
    }
}
