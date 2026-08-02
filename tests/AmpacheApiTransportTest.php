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
use AmpacheApi\Tests\Fixture\StubServer;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * The http behaviour: what a caller gets back for each way a request can end.
 */
class AmpacheApiTransportTest extends TestCase
{
    /** @var string[] */
    private static array $log = [];

    private static StubServer $server;

    /**
     * Collects debug output so a test can assert on what would reach a log.
     */
    public static function collect(string $type, string $message, int $level): bool
    {
        self::$log[] = $message;

        return true;
    }

    public static function setUpBeforeClass(): void
    {
        self::$server = new StubServer();
        self::$server->start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: int, 3: string}>
     */
    public function api8ErrorProvider(): array
    {
        return [
            'bad request' => ['catalogs', 400, 4710, 'Bad Request: filter'],
            'access check' => ['user_edit', 403, 4742, 'Require: 100'],
            'generic error' => ['videos', 500, 4702, 'Generic Error'],
        ];
    }

    public function testApi8ErrorRaisesNoPhpWarning(): void
    {
        $raised = [];
        set_error_handler(static function (int $number, string $message) use (&$raised): bool {
            $raised[] = $message;

            return true;
        });
        $this->connected()->send_command('catalogs');
        restore_error_handler();

        self::assertSame([], $raised, 'a warning here would quote the url and its auth token');
    }

    /**
     * api8 answers errors with a real http status, where api3 to api6 answer 200.
     * @dataProvider api8ErrorProvider
     */
    public function testApi8ErrorSurvivesItsHttpStatus(string $command, int $http, int $code, string $message): void
    {
        $api    = $this->connected();
        $result = $api->send_command($command);

        self::assertInstanceOf(SimpleXMLElement::class, $result, 'the error body must not be discarded');

        $error = $api->last_error();
        self::assertNotNull($error);
        self::assertSame($code, $error['code']);
        self::assertSame($message, $error['message']);
        self::assertSame($http, $error['http']);
    }

    public function testDebugCallbackNeverSeesTheSessionTokenOrPassphrase(): void
    {
        self::$log = [];
        $api       = $this->connected(['debug' => false, 'debug_callback' => [self::class, 'collect']]);
        $api->send_command('artists');

        self::assertNotEmpty(self::$log, 'the callback should still be fired, only redacted');

        $logged = implode("\n", self::$log);
        self::assertStringNotContainsString('SESSIONTOKEN', $logged);
        self::assertStringNotContainsString(hash('sha256', 'secret'), $logged);
        self::assertStringContainsString('auth=***', $logged);
    }

    /**
     * Json8_Data::empty() answers json_encode([]), which is falsy, so decoding it cannot be a truthiness test.
     */
    public function testEmptyApi8JsonResultIsAResultRatherThanAFailure(): void
    {
        $api    = $this->connected(['api_format' => 'json']);
        $result = $api->send_command('songs');

        self::assertSame([], $result, 'an empty json result must survive the decode check');
        self::assertNull($api->last_error());
    }

    public function testEmptyApi8ResultIsAResultRatherThanAFailure(): void
    {
        $api    = $this->connected();
        $result = $api->send_command('songs');

        self::assertInstanceOf(SimpleXMLElement::class, $result);
        self::assertNull($api->last_error(), 'a 404 with an empty root is an empty result, not an error');
    }

    public function testErrorCarriedInThebodyUnderA200IsHandedBackDecoded(): void
    {
        $api    = $this->connected(['server_version' => 6]);
        $result = $api->send_command('song');

        self::assertInstanceOf(SimpleXMLElement::class, $result);
        self::assertSame('Not Found', (string) $result->error->errorMessage);

        $error = $api->last_error();
        self::assertNotNull($error);
        self::assertSame(4704, $error['code']);
        self::assertSame('Not Found', $error['message']);
        self::assertSame(200, $error['http']);
    }

    public function testHandshakeConnects(): void
    {
        $api = $this->connected();

        self::assertSame('CONNECTED', $api->state());
        self::assertNull($api->last_error());
    }

    public function testHandshakeDetailsAreAvailableFromInfo(): void
    {
        $handshake = $this->connected()->info();

        self::assertInstanceOf(SimpleXMLElement::class, $handshake);
        self::assertSame('SESSIONTOKEN', (string) $handshake->auth);
        self::assertSame('5', (string) $handshake->songs);
    }

    public function testJsonErrorsAreUnderstoodToo(): void
    {
        $api    = $this->connected(['api_format' => 'json', 'server_version' => 6]);
        $result = $api->send_command('song');

        self::assertIsArray($result);

        $error = $api->last_error();
        self::assertNotNull($error);
        self::assertSame(4704, $error['code']);
        self::assertSame('Not Found', $error['message']);
    }

    public function testLastErrorIsClearedByTheNextSuccessfulCommand(): void
    {
        $api = $this->connected();
        $api->send_command('catalogs');
        self::assertNotNull($api->last_error());

        $api->send_command('artists');
        self::assertNull($api->last_error());
    }

    public function testOlderErrorShapeIsUnderstood(): void
    {
        $api = $this->connected(['server_version' => 4]);
        $api->send_command('video');

        $error = $api->last_error();
        self::assertNotNull($error);
        self::assertNotNull($error);
        self::assertSame(405, $error['code']);
        self::assertSame('Invalid Request', $error['message']);
    }

    public function testSuccessfulCommandReportsNoError(): void
    {
        $api    = $this->connected();
        $result = $api->send_command('artists');

        self::assertInstanceOf(SimpleXMLElement::class, $result);
        self::assertSame('Tester', (string) $result->artist->name);
        self::assertNull($api->last_error());
    }

    public function testUnreachableServerReportsATransportError(): void
    {
        $api = new AmpacheApi([
            'username' => 'tester',
            'password' => 'secret',
            'server' => 'http://127.0.0.1:1',
            'api_secure' => false,
            'server_version' => 6,
            'timeout' => 2,
        ]);

        self::assertSame('ERROR', $api->state());

        $error = $api->last_error();
        self::assertNotNull($error);
        self::assertSame('transport', $error['type']);
        self::assertSame(0, $error['http']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function connected(array $overrides = []): AmpacheApi
    {
        /** @var array{username: string, password: string, server: string} $config */
        $config = array_merge([
            'username' => 'tester',
            'password' => 'secret',
            'server' => 'http://' . self::$server->host(),
            'api_secure' => false,
            'api_format' => 'xml',
            'server_version' => 8,
            'timeout' => 5,
        ], $overrides);

        return new AmpacheApi($config);
    }
}
