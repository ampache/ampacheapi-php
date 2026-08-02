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

namespace AmpacheApi\Tests;

use AmpacheApi\AmpacheApi;
use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Everything that can be asserted without a server: command validation, version
 * resolution and url building.
 */
class AmpacheApiTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: int, 2: bool}>
     */
    public function commandProvider(): array
    {
        return [
            'available since api3' => ['songs', 3, true],
            'search was missing from the old lists' => ['search', 3, true],
            'added in api6, absent from api5' => ['browse', 5, false],
            'added in api6' => ['browse', 6, true],
            'added in api8, absent from api6' => ['collections', 6, false],
            'added in api8' => ['collections', 8, true],
            'tags served by api4' => ['tags', 4, true],
            'tags dropped in api5' => ['tags', 5, false],
            'tags still gone in api8' => ['tags', 8, false],
            'get_indexes served by api6' => ['get_indexes', 6, true],
            'get_indexes dropped in api8' => ['get_indexes', 8, false],
            'unknown command' => ['not_a_method', 6, false],
            'empty command' => ['', 6, false],
        ];
    }

    public function testBooleanOptionsBecomeTheZeroOrOneAmpacheExpects(): void
    {
        $url = $this->configured()->get_command_url('songs', ['exact' => true, 'unique' => false]);

        self::assertStringContainsString('&exact=1', $url);
        self::assertStringContainsString('&unique=0', $url);
    }

    public function testCallerSuppliedVersionIsNotDuplicated(): void
    {
        $url = $this->configured(['server_version' => 6])->get_command_url('handshake', ['version' => '390001']);

        self::assertSame(1, substr_count($url, 'version='));
        self::assertStringContainsString('version=390001', $url);
    }

    public function testCommandsUsedByTheRemoteCatalogAreValidOnBothLiveVersions(): void
    {
        $commands = ['album', 'artist', 'artists', 'download', 'ping', 'song', 'song_tags', 'songs', 'stream', 'url_to_song'];
        foreach ([6, 8] as $version) {
            $api = $this->configured(['server_version' => $version]);
            foreach ($commands as $command) {
                self::assertTrue($api->validate_command($command), "$command should be valid on api$version");
            }
        }
    }

    public function testConfigureRejectsIncompleteConfigWithoutRaisingWarnings(): void
    {
        $api = (new ReflectionClass(AmpacheApi::class))->newInstanceWithoutConstructor();

        $raised = [];
        set_error_handler(static function (int $number, string $message) use (&$raised): bool {
            $raised[] = $message;

            return true;
        });
        // @phpstan-ignore-next-line the config is deliberately incomplete, which is what this asserts
        $result = $api->configure(['username' => 'tester']);
        restore_error_handler();

        self::assertFalse($result);
        self::assertCount(1, $raised, 'only the configure complaint should be raised, not an undefined key warning');
        self::assertStringContainsString('unable to configure', $raised[0]);
    }

    public function testDefaultsToApi6(): void
    {
        self::assertSame(6, $this->readProperty($this->configured(), 'server_version'));
    }

    /**
     * PingMethod rewrites the session to Api::$version when a ping arrives without one, so every command carries its version.
     */
    public function testEveryCommandCarriesTheApiVersion(): void
    {
        $url = $this->configured(['server_version' => 6])->get_command_url('ping');

        self::assertStringContainsString('&version=6.9.1', $url);
    }

    public function testInfoRefusesToAnswerBeforeAHandshake(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('non-ready state');

        $this->configured()->info();
    }

    public function testLastErrorIsEmptyBeforeAnythingIsSent(): void
    {
        self::assertNull($this->configured()->last_error());
    }

    public function testNonCallableDebugCallbackIsReportedRatherThanStored(): void
    {
        $raised = [];
        set_error_handler(static function (int $number, string $message) use (&$raised): bool {
            $raised[] = $message;

            return true;
        });

        // the config is left incomplete so the constructor stops before it tries to connect
        // @phpstan-ignore-next-line
        new AmpacheApi(['username' => 'tester', 'password' => 'secret', 'debug_callback' => 'definitely_not_a_function']);
        restore_error_handler();

        self::assertNotEmpty(array_filter($raised, static fn(string $m): bool => strpos($m, 'not callable') !== false));
    }

    public function testNonScalarOptionIsRefusedWithAClearMessage(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('must be a scalar value');

        $this->configured()->get_command_url('songs', ['filter' => [1, 2]]);
    }

    public function testNullOptionsAreOmittedRatherThanSentEmpty(): void
    {
        $url = $this->configured()->get_command_url('songs', ['limit' => 5, 'offset' => null]);

        self::assertStringContainsString('&limit=5', $url);
        self::assertStringNotContainsString('offset', $url);
    }

    public function testOptionsAreUrlEncoded(): void
    {
        $url = $this->configured()->get_command_url('advanced_search', ['rule_1_input' => 'a b&c']);

        self::assertStringContainsString('&rule_1_input=a+b%26c', $url);
    }

    public function testSchemeInTheServerAddressIsReplacedByTheOneApiSecureAsksFor(): void
    {
        $api = $this->configured(['server' => 'http://music.example', 'api_secure' => true]);

        self::assertStringStartsWith('https://music.example/server/', $api->get_command_url('ping'));
    }

    public function testStartsUnconfiguredAndBecomesReady(): void
    {
        $api = (new ReflectionClass(AmpacheApi::class))->newInstanceWithoutConstructor();
        self::assertSame('UNCONFIGURED', $api->state());

        $api->configure(['username' => 'tester', 'password' => 'secret', 'server' => 'music.example']);
        self::assertSame('READY', $api->state());
    }

    public function testSubdirectoryInstallsKeepTheirPath(): void
    {
        $api = $this->configured(['server' => 'https://example.com/ampache']);

        self::assertStringStartsWith('https://example.com/ampache/server/', $api->get_command_url('ping'));
    }

    public function testTrailingSlashIsTrimmedFromTheServerAddress(): void
    {
        $api = $this->configured(['server' => 'https://music.example/']);

        self::assertStringStartsWith('https://music.example/server/', $api->get_command_url('ping'));
    }

    public function testUnknownCommandIsRefusedBeforeAnyRequest(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid/Unknown command');

        $this->configured()->get_command_url('not_a_method');
    }

    public function testUrlUsesTheFormatAsTheEndpoint(): void
    {
        self::assertStringStartsWith(
            'https://music.example/server/json.server.php?action=ping',
            $this->configured(['api_format' => 'json'])->get_command_url('ping')
        );
    }

    /**
     * @dataProvider commandProvider
     */
    public function testValidateCommand(string $command, int $version, bool $expected): void
    {
        self::assertSame($expected, $this->configured(['server_version' => $version])->validate_command($command));
    }

    /**
     * @dataProvider versionProvider
     * @param int|string $requested
     */
    public function testVersionResolution($requested, int $expected, string $handshake): void
    {
        $api = $this->configured(['server_version' => $requested]);

        self::assertSame($expected, $this->readProperty($api, 'server_version'));
        self::assertSame($handshake, $this->readProperty($api, 'handshake_version'));
    }

    /**
     * @return array<string, array{0: int|string, 1: int, 2: string}>
     */
    public function versionProvider(): array
    {
        return [
            'api3' => [3, 3, '390001'],
            'api4' => [4, 4, '443000'],
            'api5' => [5, 5, '5.5.6'],
            'api6' => [6, 6, '6.9.1'],
            'api8' => [8, 8, '8.0.0'],
            'version string' => ['8.0.0', 8, '8.0.0'],
            'no api7 exists' => [7, 6, '6.9.1'],
            'unknown falls back' => [9, 6, '6.9.1'],
            'nonsense falls back' => ['nonsense', 6, '6.9.1'],
        ];
    }

    /**
     * Configures an instance without letting the constructor dial out.
     * @param array<string, mixed> $overrides
     */
    private function configured(array $overrides = []): AmpacheApi
    {
        $api = (new ReflectionClass(AmpacheApi::class))->newInstanceWithoutConstructor();

        /** @var array{username: string, password: string, server: string} $config */
        $config = array_merge([
            'username' => 'tester',
            'password' => 'secret',
            'server' => 'music.example',
        ], $overrides);
        $api->configure($config);

        return $api;
    }

    /**
     * Reads a private property, which is the only way to see the resolved version.
     * @return int|string
     */
    private function readProperty(AmpacheApi $api, string $name)
    {
        $property = (new ReflectionClass(AmpacheApi::class))->getProperty($name);

        // a no-op since 8.1 and deprecated in 8.5, but still required on the 7.4 floor
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }

        return $property->getValue($api);
    }
}
