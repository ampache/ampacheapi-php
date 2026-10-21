<?php

declare(strict_types=0);

/**
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright Ampache.org, 2001-2026
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

namespace AmpacheApi\Tests\Tools;

use AmpacheApi\AmpacheApi;
use AmpacheApi\Tests\Fixture\StubServer;

use function canary_for;
use function check_version;
use function command_url;
use function error_code;
use function flag;
use function option;

use PHPUnit\Framework\TestCase;

use function probe;

use ReflectionClass;

require_once dirname(__DIR__, 2) . '/tools/VerifyMethods.php';

/**
 * The pure helpers behind tools/verify-methods.php: a 429-line CLI script that
 * otherwise has no test coverage at all, because it runs its whole body on
 * require. VerifyMethods.php carries only the functions, so they can be
 * required and called directly here.
 */
class VerifyMethodsTest extends TestCase
{
    private static StubServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = new StubServer();
        self::$server->start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    public function testCanaryForPicksTheHighestLaterVersionMethod(): void
    {
        $methods  = ['stream', 'collections', 'songs'];
        $minimums = ['stream' => 4, 'collections' => 8, 'songs' => 3];

        self::assertSame('collections', canary_for(6, $methods, $minimums));
    }

    public function testCanaryForReturnsNullWhenNothingIsLater(): void
    {
        $methods  = ['songs', 'artists'];
        $minimums = ['songs' => 3, 'artists' => 3];

        self::assertNull(canary_for(8, $methods, $minimums));
    }

    public function testCanaryForSkipsMethodsThatWouldActuallyRun(): void
    {
        // 'stream' would belong to a later version here, but SKIP_METHODS excludes it because calling it would return binary audio
        $methods  = ['stream', 'collections'];
        $minimums = ['stream' => 4, 'collections' => 8];

        self::assertSame('collections', canary_for(6, $methods, $minimums));
    }

    public function testCheckVersionCountsSkippedMismatchedAndInconclusiveMethods(): void
    {
        $api = $this->connectedApi(['server_version' => 4]);

        $methods  = ['artists', 'browse', 'video', 'download'];
        $minimums = ['artists' => 3, 'browse' => 4, 'video' => 3, 'download' => 4];

        $outcome = check_version($api, 4, $this->endpoint(), 'xml', 'tester', 5, $methods, $minimums, [4 => '443000']);

        self::assertSame('checked', $outcome['outcome']);
        self::assertSame(3, $outcome['checked'], 'download is skipped rather than probed, so it does not count as checked');
        self::assertSame(1, $outcome['skipped']);
        self::assertSame(1, $outcome['mismatched'], 'browse is not valid at api4 but the stub serves everything it does not special-case');
        self::assertSame(1, $outcome['inconclusive'], "video answers a bare 405, ambiguous for api3/api4's missing-parameter reuse");

        $types = array_column($outcome['events'], 'type', 'method');
        self::assertSame('ok', $types['artists']);
        self::assertSame('mismatch', $types['browse']);
        self::assertSame('inconclusive', $types['video']);
        self::assertArrayNotHasKey('download', $types, 'a skipped method is never probed, so it leaves no event');
        self::assertSame(['download' => 'returns binary audio'], $outcome['skippedMethods']);
    }

    public function testCheckVersionReportsHandshakeFailedWhenNotConnected(): void
    {
        /** @var AmpacheApi $api */
        $api = (new ReflectionClass(AmpacheApi::class))->newInstanceWithoutConstructor();
        $api->configure(['username' => 'tester', 'password' => 'secret', 'server' => 'music.example']);

        $outcome = check_version($api, 6, 'https://music.example/server/xml.server.php', 'xml', 'tester', 5, [], [], []);

        self::assertSame('handshake_failed', $outcome['outcome']);
        self::assertSame('READY', $outcome['detail']);
    }

    public function testCheckVersionReportsNoAuthWhenConnectedWithoutARealHandshake(): void
    {
        /** @var AmpacheApi $api */
        $api = (new ReflectionClass(AmpacheApi::class))->newInstanceWithoutConstructor();
        $api->configure(['username' => 'tester', 'password' => 'secret', 'server' => 'music.example']);
        // state() is the only thing check_version trusts without a real connect(), so this is the one way to reach the defensive no-auth branch
        $api->set_state('CONNECTED');

        $outcome = check_version($api, 6, 'https://music.example/server/xml.server.php', 'xml', 'tester', 5, [], [], []);

        self::assertSame('no_auth', $outcome['outcome']);
    }

    public function testCheckVersionReportsVersionNotHonouredWhenACanaryMethodIsServed(): void
    {
        $api = $this->connectedApi(['server_version' => 6]);

        $outcome = check_version($api, 6, $this->endpoint(), 'xml', 'tester', 5, ['collections'], ['collections' => 8], [6 => '6.9.1']);

        self::assertSame('version_not_honoured', $outcome['outcome']);
        self::assertSame('collections', $outcome['detail']);
        self::assertTrue($outcome['canary']);
    }

    public function testCheckVersionReportsVersionNotHonouredWhenTheHandshakeAnsweredAnotherVersion(): void
    {
        $api = $this->connectedApi(['server_version' => 6]);

        $outcome = check_version($api, 8, $this->endpoint(), 'xml', 'tester', 5, ['artists'], ['artists' => 3], [8 => '8.0.0']);

        self::assertSame('version_not_honoured', $outcome['outcome']);
        self::assertSame('6', $outcome['detail']);
        self::assertFalse($outcome['canary']);
    }

    public function testCommandUrlBuildsTheExpectedQueryString(): void
    {
        $url = command_url('https://host/server/xml.server.php', 'songs', 'AUTHTOKEN', 'tester', '6.9.1');

        self::assertSame(
            'https://host/server/xml.server.php?action=songs&auth=AUTHTOKEN&username=tester&version=6.9.1&limit=1',
            $url
        );
    }

    public function testErrorCodeFallsBackToTheOldJsonCodeKey(): void
    {
        self::assertSame(405, error_code((string) json_encode(['error' => ['code' => '405']]), 'json'));
    }

    public function testErrorCodeFallsBackToTheOldXmlCodeAttribute(): void
    {
        self::assertSame(405, error_code('<root><error code="405"><![CDATA[Invalid Request]]></error></root>', 'xml'));
    }

    public function testErrorCodePullsErrorCodeFromJsonAtApi5Shape(): void
    {
        self::assertSame(4705, error_code((string) json_encode(['error' => ['errorCode' => '4705']]), 'json'));
    }

    public function testErrorCodePullsErrorCodeFromXmlAttribute(): void
    {
        self::assertSame(4706, error_code('<root><error errorCode="4706"></error></root>', 'xml'));
    }

    public function testErrorCodeReturnsNullForJsonWithoutAnError(): void
    {
        self::assertNull(error_code((string) json_encode(['ok' => 1]), 'json'));
    }

    public function testErrorCodeReturnsNullForMalformedJson(): void
    {
        self::assertNull(error_code('{not valid json', 'json'));
    }

    public function testErrorCodeReturnsNullForMalformedXml(): void
    {
        self::assertNull(error_code('not valid xml<<<', 'xml'));
    }

    public function testErrorCodeReturnsNullForXmlWithoutAnError(): void
    {
        self::assertNull(error_code('<root><ok>1</ok></root>', 'xml'));
    }

    public function testFlagIsFalseWhenAbsent(): void
    {
        $this->withArgv(['verify-methods.php', '--host=x'], static function (): void {
            self::assertFalse(flag('verbose'));
        });
    }

    public function testFlagIsTrueWhenPresent(): void
    {
        $this->withArgv(['verify-methods.php', '--verbose'], static function (): void {
            self::assertTrue(flag('verbose'));
        });
    }

    public function testOptionFallsBackToTheDefaultWhenNeitherIsSet(): void
    {
        $this->withArgv(['verify-methods.php'], static function (): void {
            self::assertSame('xml', option('format', 'AMPACHE_API_FORMAT_DOES_NOT_EXIST', 'xml'));
        });
    }

    public function testOptionFallsBackToTheEnvironment(): void
    {
        putenv('AMPACHE_TEST_OPTION=from-env');
        $this->withArgv(['verify-methods.php'], static function (): void {
            self::assertSame('from-env', option('host', 'AMPACHE_TEST_OPTION'));
        });
        putenv('AMPACHE_TEST_OPTION');
    }

    public function testOptionReadsFromTheCommandLineFirst(): void
    {
        putenv('AMPACHE_TEST_OPTION=from-env');
        $this->withArgv(['verify-methods.php', '--host=from-argv'], static function (): void {
            self::assertSame('from-argv', option('host', 'AMPACHE_TEST_OPTION'));
        });
        putenv('AMPACHE_TEST_OPTION');
    }

    public function testProbeClassifiesANotServedMethod(): void
    {
        $url = 'http://' . self::$server->host() . '/?action=browse&respond_error_code=405';

        self::assertSame('not served', probe($url, 5, 'xml'));
    }

    public function testProbeClassifiesAnUnreachableServer(): void
    {
        self::assertSame('unreachable', probe('http://127.0.0.1:1/?action=artists', 1, 'xml'));
    }

    public function testProbeClassifiesARemovedMethod(): void
    {
        $url = 'http://' . self::$server->host() . '/?action=tags&respond_error_code=4706';

        self::assertSame('removed', probe($url, 5, 'xml'));
    }

    public function testProbeClassifiesAServedMethod(): void
    {
        $url = 'http://' . self::$server->host() . '/?action=artists';

        self::assertSame('served', probe($url, 5, 'xml'));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function connectedApi(array $overrides = []): AmpacheApi
    {
        /** @var array{username: string, password: string, server: string} $config */
        $config = array_merge([
            'username' => 'tester',
            'password' => 'secret',
            'server' => 'http://' . self::$server->host(),
            'api_secure' => false,
            'api_format' => 'xml',
            'timeout' => 5,
        ], $overrides);

        return new AmpacheApi($config);
    }

    private function endpoint(): string
    {
        return 'http://' . self::$server->host() . '/server/xml.server.php';
    }

    /**
     * option()/flag() read $_SERVER['argv'] directly, so this swaps it in for the duration of the callback and always restores it.
     * @param string[] $argv
     */
    private function withArgv(array $argv, callable $callback): void
    {
        $previous              = $_SERVER['argv'] ?? [];
        $_SERVER['argv']       = $argv;

        try {
            $callback();
        } finally {
            $_SERVER['argv'] = $previous;
        }
    }
}
