<?php

declare(strict_types=0);

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
        $api->configure(array_merge([
            'username' => 'tester',
            'password' => 'secret',
            'server' => 'music.example',
        ], $overrides));

        return $api;
    }

    /**
     */
    private function readProperty(AmpacheApi $api, string $name)
    {
        $property = (new ReflectionClass(AmpacheApi::class))->getProperty($name);
        $property->setAccessible(true);

        return $property->getValue($api);
    }
}
