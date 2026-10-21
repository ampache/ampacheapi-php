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

    private static function hostAndPort(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['host'] ?? '') . ':' . ($parts['port'] ?? '');
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

    public function testBadCredentialsLeaveStateErrorWithTheServersOwnError(): void
    {
        $api = $this->connected(['username' => 'baduser']);

        self::assertSame('ERROR', $api->state());

        $error = $api->last_error();
        self::assertNotNull($error);
        self::assertSame(4701, $error['code']);
        self::assertSame('Invalid Handshake', $error['message']);
        self::assertNotSame('transport', $error['type'], 'this is the server refusing, not a transport failure');
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

        // the handshake's own auth=*** is the login passphrase, not the session token; this checks the later, authenticated command instead
        $artistsUrlLine = self::$log[array_key_last(self::$log)];
        self::assertStringContainsString('action=artists', $artistsUrlLine);
        self::assertStringNotContainsString('auth=', $artistsUrlLine, 'the session token travels in a header now, so this url has nothing to redact');
    }

    public function testDebugOutputIsRedactedTooNotJustTheCallback(): void
    {
        ob_start();
        $api = $this->connected(['debug' => true]);
        $api->send_command('artists');
        $output = (string) ob_get_clean();

        self::assertNotEmpty($output);
        self::assertStringNotContainsString(hash('sha256', 'secret'), $output);
        self::assertStringContainsString('auth=***', $output, 'the login passphrase in the handshake url should still be redacted');
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

    public function testExactlyMaxRedirectsStillSucceeds(): void
    {
        $api    = $this->connected(['url_validator' => static fn(): bool => true]);
        $result = $api->send_command('artists', ['redirect_count' => 10]);

        self::assertInstanceOf(SimpleXMLElement::class, $result);
        self::assertSame('Tester', (string) $result->artist->name);
        self::assertNull($api->last_error());
    }

    public function testGetCommandUrlStillEmbedsAuthForExternalUse(): void
    {
        $url = $this->connected()->get_command_url('download', ['id' => 1]);

        self::assertStringContainsString('&auth=SESSIONTOKEN', $url);
        self::assertStringContainsString('&username=tester', $url);
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

    public function testMalformedResponseBodyIsATransportError(): void
    {
        $api    = $this->connected();
        $result = $api->send_command('artists', ['malformed' => 1]);

        self::assertNull($result, 'a body that cannot be decoded at all must not be handed back as a result');

        $error = $api->last_error();
        self::assertNotNull($error);
        self::assertSame('transport', $error['type']);
        self::assertStringContainsString('Unreadable xml response', $error['message']);
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

    public function testOlderJsonErrorShapeIsUnderstood(): void
    {
        $api = $this->connected(['api_format' => 'json', 'server_version' => 4]);
        $api->send_command('video');

        $error = $api->last_error();
        self::assertNotNull($error);
        self::assertSame(405, $error['code']);
        self::assertSame('Invalid Request', $error['message']);
    }

    public function testOneMoreThanMaxRedirectsFails(): void
    {
        $api    = $this->connected(['url_validator' => static fn(): bool => true]);
        $result = $api->send_command('artists', ['redirect_count' => 11]);

        self::assertNull($result);

        $error = $api->last_error();
        self::assertNotNull($error);
        self::assertSame('transport', $error['type']);
        self::assertSame('Too many redirects', $error['message']);
    }

    public function testRedirectIsFollowedNativelyWithoutAUrlValidator(): void
    {
        $host   = self::$server->host();
        $api    = $this->connected();
        $result = $api->send_command('artists', ['redirect_to' => 'http://' . $host . '/?action=artists']);

        self::assertInstanceOf(SimpleXMLElement::class, $result);
        self::assertSame('Tester', (string) $result->artist->name);
        self::assertNull($api->last_error());
    }

    public function testRelativeRedirectWithoutALeadingSlashIsResolvedAgainstTheRequestHost(): void
    {
        $api    = $this->connected(['url_validator' => static fn(): bool => true]);
        $result = $api->send_command('artists', ['redirect_to' => 'elsewhere?action=artists']);

        self::assertInstanceOf(SimpleXMLElement::class, $result);
        self::assertSame('Tester', (string) $result->artist->name);
        self::assertNull($api->last_error());
    }

    /**
     * file_get_contents concatenates one status line per hop it follows natively, so this proves the last one
     * wins rather than the 302 that got the client there.
     */
    public function testResponseStatusReflectsTheFinalHopOfANativeRedirectNotTheRedirectItself(): void
    {
        $host   = self::$server->host();
        $api    = $this->connected();
        $result = $api->send_command('catalogs', ['redirect_to' => 'http://' . $host . '/?action=catalogs']);

        self::assertInstanceOf(SimpleXMLElement::class, $result);

        $error = $api->last_error();
        self::assertNotNull($error);
        self::assertSame(400, $error['http']);
    }

    public function testSendCommandSendsAuthViaHeaderNotUrl(): void
    {
        $result = $this->connected()->send_command('genres');

        self::assertInstanceOf(SimpleXMLElement::class, $result);
        self::assertSame('SESSIONTOKEN', (string) $result->http_auth, 'the session token should arrive as the auth header');
        self::assertSame('', (string) $result->query_auth, 'the session token should not appear in the url');
    }

    public function testSongTagsEmptyResultSupportsCatalogRemotesFeatureDetection(): void
    {
        $api    = $this->connected();
        $result = $api->send_command('song_tags', ['filter' => 1]);

        self::assertInstanceOf(SimpleXMLElement::class, $result);
        self::assertNull($api->last_error());
        self::assertCount(0, $result->children());
    }

    public function testSuccessfulCommandReportsNoError(): void
    {
        $api    = $this->connected();
        $result = $api->send_command('artists');

        self::assertInstanceOf(SimpleXMLElement::class, $result);
        self::assertSame('Tester', (string) $result->artist->name);
        self::assertNull($api->last_error());
    }

    /**
     * The stub always redirects to itself, so a validator that approves everything still has to give up eventually.
     */
    public function testTooManyRedirectsIsReportedAsATransportError(): void
    {
        $api    = $this->connected(['url_validator' => static fn(): bool => true]);
        $result = $api->send_command('artists', ['redirect_to' => 'loop']);

        self::assertNull($result);

        $error = $api->last_error();
        self::assertNotNull($error);
        self::assertSame('transport', $error['type']);
        self::assertSame('Too many redirects', $error['message']);
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
     * A redirect that still lands on the configured server is exactly what a legitimate api response looks like.
     */
    public function testUrlValidatorAllowsARedirectToAnApprovedHost(): void
    {
        $host = self::$server->host();
        $api  = $this->connected(['url_validator' => static fn(string $url): bool => self::hostAndPort($url) === $host]);

        $result = $api->send_command('artists', ['redirect_to' => 'http://' . $host . '/?action=artists']);

        self::assertInstanceOf(SimpleXMLElement::class, $result);
        self::assertSame('Tester', (string) $result->artist->name);
        self::assertNull($api->last_error());
    }

    /**
     * Only the initial connect is checked without this: a server that starts out approved could still redirect
     * a later command anywhere once it is trusted, which is the bypass this library must not reintroduce.
     */
    public function testUrlValidatorRefusesARedirectToAnUnapprovedHost(): void
    {
        $host = self::$server->host();
        $api  = $this->connected(['url_validator' => static fn(string $url): bool => self::hostAndPort($url) === $host]);

        $result = $api->send_command('artists', ['redirect_to' => 'http://192.0.2.1/evil']);

        self::assertNull($result);

        $error = $api->last_error();
        self::assertNotNull($error);
        self::assertSame('transport', $error['type']);
        self::assertSame('Destination refused by url_validator', $error['message']);
    }

    public function testUrlValidatorRefusesTheHandshake(): void
    {
        $api = $this->connected(['url_validator' => static fn(): bool => false]);

        self::assertSame('ERROR', $api->state());

        $error = $api->last_error();
        self::assertNotNull($error);
        self::assertSame('transport', $error['type']);
        self::assertSame('Destination refused by url_validator', $error['message']);
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
