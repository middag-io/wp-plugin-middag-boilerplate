<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Guards the main plugin file's "Version:" header against annotation leakage.
 *
 * WordPress reads the header with a regex and _cleanup_header_comment() only
 * strips a trailing "*​/" or "?>", NOT a "//" comment. An inline
 * "// x-release-please-version" on the header line would corrupt the parsed
 * version to e.g. "0.1.0 // x-release-please-version". release-please must bump
 * the header via block markers (x-release-please-start-version /
 * x-release-please-end) on their own lines instead. This test parses the header
 * exactly like WordPress and asserts it is a clean semver that matches the
 * version const.
 */
final class PluginHeaderTest extends TestCase
{
    private const MAIN_FILE = '/middag-boilerplate.php';
    private const VERSION_CONST = 'MIDDAG_BOILERPLATE_VERSION';

    public function testVersionHeaderIsCleanSemverMatchingTheConst(): void
    {
        $file = (string) file_get_contents(dirname(__DIR__, 2) . self::MAIN_FILE);

        // Parse the "Version:" header the way WordPress get_file_data() does.
        self::assertSame(1, preg_match(
            '/^(?:[ \t]*<\?php)?[ \t\/*#@]*Version:(.*)$/mi',
            $file,
            $headerMatch
        ), 'The main plugin file must declare a "Version:" header.');
        $header = trim((string) preg_replace('/\s*(?:\*\/|\?>).*/', '', $headerMatch[1]));

        // Read the version const from the same file.
        self::assertSame(1, preg_match(
            '/const ' . self::VERSION_CONST . " = '([^']+)'/",
            $file,
            $constMatch
        ), 'The main plugin file must define the ' . self::VERSION_CONST . ' const.');
        $const = $constMatch[1];

        self::assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+$/',
            $header,
            'The WordPress-parsed "Version:" header must be a clean semver — an '
            . 'inline "// x-release-please-version" annotation would corrupt it. '
            . 'Use release-please block markers on their own lines instead.'
        );
        self::assertSame(
            $const,
            $header,
            'The "Version:" header and the version const must stay in lockstep.'
        );
    }
}
