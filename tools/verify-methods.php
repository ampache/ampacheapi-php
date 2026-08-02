#!/usr/bin/env php
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

/**
 * verify-methods
 *
 * Checks this library's METHOD_MIN_VERSION / METHOD_REMOVED_IN maps against a
 * live Ampache server, so we find out when the server has added or dropped a
 * method before a caller does.
 *
 * For every method the maps know about, at every requested api version, this
 * asks the server for the method with no parameters and reads the error code
 * the dispatcher hands back:
 *
 *   405 / 4705 (Invalid Request)  the version does not serve this method
 *   4706       (Deprecated)       the version has dropped this method
 *   anything else, or a result    the version serves it
 *
 * Those three codes are emitted by Ampache's ApiHandler before it ever reaches
 * a method handler, so "served" is established without the method doing its
 * work. Calls are made with no parameters, so anything that needs a `filter` or
 * an `id` answers 4710 Bad Request and stops there.
 *
 * WHAT IT WILL NOT CONCLUDE
 *
 * A version whose api_enable_N preference is off is rolled up to the next one
 * the server does serve, which would otherwise read as every difference between
 * the two being a map error. The handshake's reported version and a canary
 * method from a later version both get checked first, and the version is
 * reported unverified instead.
 *
 * api3 and api4 answer 405 for a missing parameter as well as for an unknown
 * method, so a refusal there is reported inconclusive. Only the other direction,
 * the server serving something the maps reject, is unambiguous on those.
 *
 * SAFETY
 *
 * This still authenticates as a real user against a real server, and a handful
 * of methods need no parameters and would therefore run. Those are skipped by
 * name (see SKIP_METHODS) and reported as skipped rather than silently dropped.
 * Run it as a NON-ADMIN user: admin-only methods then answer 4742 Failed Access
 * Check, which still proves the method exists but runs none of it.
 *
 * SCOPE
 *
 * This checks the names the maps declare and nothing else. The maps are the
 * library's contract: what it is willing to send is decided here, by hand, not
 * learned from whatever a server happens to answer. Picking up a new method is a
 * deliberate one line change, taken from Ampache's CHANGELOG-API.md. The job
 * here is to confirm that contract is accurate, never to widen it.
 *
 * Usage:
 *   php tools/verify-methods.php [options]
 *
 *   --host=URL        server, with scheme     (env AMPACHE_HOST)
 *   --user=NAME       username                (env AMPACHE_USER)
 *   --password=PASS   password, plain or sha256 hashed (env AMPACHE_PASSWORD)
 *   --versions=6,8    api versions to check   (env AMPACHE_API_VERSIONS, default 6,8)
 *   --format=xml      xml or json             (env AMPACHE_API_FORMAT, default xml)
 *   --timeout=15      per request seconds     (env AMPACHE_TIMEOUT, default 15)
 *   --verbose         list every method, not just the mismatches
 *
 * Exits 1 if the server disagrees with the maps, 0 if they match.
 */

use AmpacheApi\AmpacheApi;

require dirname(__DIR__) . '/vendor/autoload.php';

/** Methods that need no parameters and would do real work, so we never call them */
const SKIP_METHODS = [
    'goodbye' => 'would end the session this probe is using',
    'system_update' => 'would run a server update',
    'register' => 'would create a user account',
    'lost_password' => 'would send mail',
    'stream' => 'returns binary audio',
    'download' => 'returns binary audio',
    'get_art' => 'returns a binary image',
];

/** Dispatcher codes for "this version does not serve that method" */
const CODE_NOT_SERVED = [405, 4705];

/** Dispatcher code for "this version has dropped that method" */
const CODE_REMOVED = 4706;

/** api3 and api4 answer 405 for a missing parameter as well as an unknown method, so a refusal there proves nothing */
const AMBIGUOUS_REFUSAL_VERSIONS = [3, 4];

/**
 * option
 *
 * Reads --name=value from the command line, falling back to the environment.
 */
function option(string $name, string $environmentName, ?string $default = null): ?string
{
    foreach (array_slice($_SERVER['argv'], 1) as $argument) {
        if (strpos($argument, '--' . $name . '=') === 0) {
            return substr($argument, strlen($name) + 3);
        }
    }

    $environment = getenv($environmentName);

    return ($environment === false || $environment === '')
        ? $default
        : $environment;
}

/**
 * flag
 */
function flag(string $name): bool
{
    return in_array('--' . $name, array_slice($_SERVER['argv'], 1), true);
}

/**
 * error_code
 *
 * Pulls the api error code out of a raw response, or null when the response
 * was not an error at all.
 */
function error_code(string $body, string $format): ?int
{
    if ($format === 'json') {
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['error']) || !is_array($data['error'])) {
            return null;
        }

        // api5+ uses errorCode, api4 uses code
        $error = $data['error'];

        return isset($error['errorCode'])
            ? (int) $error['errorCode']
            : (isset($error['code']) ? (int) $error['code'] : null);
    }

    $xml = @simplexml_load_string($body);
    if (!$xml instanceof SimpleXMLElement || !isset($xml->error)) {
        return null;
    }

    $attributes = $xml->error->attributes();

    return isset($attributes['errorCode'])
        ? (int) $attributes['errorCode']
        : (isset($attributes['code']) ? (int) $attributes['code'] : null);
}

/**
 * command_url
 *
 * Builds a bare command url, with limit=1 to keep list responses small.
 *
 * The version has to travel with every call: ping is a public action, and a public action carrying no version rewrites the session.
 */
function command_url(string $endpoint, string $method, string $auth, string $username, string $handshakeVersion): string
{
    return $endpoint . '?action=' . urlencode($method)
        . '&auth=' . urlencode($auth)
        . '&username=' . urlencode($username)
        . '&version=' . urlencode($handshakeVersion)
        . '&limit=1';
}

/**
 * canary_for
 *
 * Picks a method belonging to a later api version than the one being checked, so serving it proves the server rolled the version up.
 * @param string[] $methods
 * @param array<string, int> $minimums
 */
function canary_for(int $version, array $methods, array $minimums): ?string
{
    $best    = null;
    $highest = $version;
    foreach ($methods as $method) {
        if (isset(SKIP_METHODS[$method])) {
            continue;
        }

        $minimum = $minimums[$method] ?? 0;
        if ($minimum > $highest) {
            $highest = $minimum;
            $best    = $method;
        }
    }

    return $best;
}

/**
 * probe
 *
 * Asks the server for a command with no parameters and classifies the answer
 * as 'served', 'not served', 'removed' or 'unreachable'.
 */
function probe(string $url, int $timeout, string $format): string
{
    $context = stream_context_create([
        'http' => ['timeout' => $timeout, 'ignore_errors' => true],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return 'unreachable';
    }

    $code = error_code($body, $format);
    if ($code === null) {
        // a real result, so the server clearly serves it
        return 'served';
    }
    if (in_array($code, CODE_NOT_SERVED, true)) {
        return 'not served';
    }

    return ($code === CODE_REMOVED)
        ? 'removed'
        : 'served';
}

// ---------------------------------------------------------------- entry point

$host     = option('host', 'AMPACHE_HOST');
$username = option('user', 'AMPACHE_USER');
$password = option('password', 'AMPACHE_PASSWORD');
$format   = option('format', 'AMPACHE_API_FORMAT', 'xml');
$timeout  = (int) option('timeout', 'AMPACHE_TIMEOUT', '15');
$verbose  = flag('verbose');
$versions = array_map('intval', explode(',', (string) option('versions', 'AMPACHE_API_VERSIONS', '6,8')));

if (!$host || !$username || !$password) {
    fwrite(STDERR, "Set --host, --user and --password (or AMPACHE_HOST, AMPACHE_USER, AMPACHE_PASSWORD).\n");
    fwrite(STDERR, "Options are documented at the top of " . basename(__FILE__) . ".\n");
    exit(2);
}
if (!in_array($format, ['xml', 'json'], true)) {
    fwrite(STDERR, "--format must be xml or json\n");
    exit(2);
}

$secure   = (strpos($host, 'https://') === 0);
$bare     = (string) preg_replace('#^https?://#', '', $host);
$endpoint = ($secure ? 'https://' : 'http://') . rtrim($bare, '/') . '/server/' . $format . '.server.php';

// the maps are private, which is right for the library and fine to read here
$maps = (new ReflectionClass(AmpacheApi::class))->getConstants();

/** @var array<string, int> $minimums */
$minimums = $maps['METHOD_MIN_VERSION'];

/** @var array<int, string> $handshakeVersions */
$handshakeVersions = $maps['HANDSHAKE_VERSIONS'];

$methods = array_map('strval', array_keys($minimums));
sort($methods);

printf("Ampache method map check\n  server  %s\n  format  %s\n  methods %d\n\n", $endpoint, $format, count($methods));

$failures   = 0;
$skipped    = [];
$unverified = [];

foreach ($versions as $version) {
    $api = new AmpacheApi([
        'username' => $username,
        'password' => $password,
        'server' => $host,
        'debug' => false,
        'api_secure' => $secure,
        'api_format' => $format,
        'server_version' => $version,
    ]);

    if ($api->state() !== 'CONNECTED') {
        printf("api %d: handshake FAILED, skipping (state %s)\n\n", $version, $api->state());
        $failures++;
        continue;
    }

    $handshake = $api->info();
    $auth      = ($format === 'json')
        ? (is_array($handshake) && isset($handshake['auth']) ? (string) $handshake['auth'] : '')
        : (($handshake instanceof SimpleXMLElement && !empty($handshake->auth)) ? (string) $handshake->auth : '');

    if ($auth === '') {
        printf("api %d: connected but no auth token in the handshake, skipping\n\n", $version);
        $failures++;
        continue;
    }

    /**
     * The handshake reports the version the server settled on, which is not
     * always the one asked for: an api_enable_N preference that is off makes
     * ApiHandler roll the request up to the next version it does serve. Probing
     * on regardless would report every difference between the two versions as a
     * map disagreement, so this is reported as unverified instead.
     */
    $answered = ($format === 'json')
        ? (int) substr((string) ($handshake['api'] ?? ''), 0, 1)
        : (int) substr(($handshake instanceof SimpleXMLElement) ? (string) $handshake->api : '', 0, 1);

    if ($answered > 0 && $answered !== $version) {
        printf("api %d: server answered as api %d, so this version is not enabled here; not verified\n\n", $version, $answered);
        $unverified[] = sprintf('api %d (handshake answered as api %d)', $version, $answered);
        continue;
    }

    /**
     * The handshake can be accepted at the requested version while commands are
     * still served by a later one, because ApiHandler re-resolves the version
     * per request and rolls it up past every api_enable_N that is switched off.
     * Asking for a method this version does not have is the only way to tell.
     */
    $handshakeVersion = (string) ($handshakeVersions[$version] ?? '');
    $canary           = canary_for($version, $methods, $minimums);
    if ($canary !== null) {
        $canaryResult = probe(command_url($endpoint, $canary, $auth, $username, $handshakeVersion), $timeout, $format);
        if ($canaryResult === 'served') {
            printf("api %d: server served '%s', which belongs to a later version, so it is not honouring api %d; not verified\n\n", $version, $canary, $version);
            $unverified[] = sprintf("api %d (server served '%s', a later version's method)", $version, $canary);
            continue;
        }
    }

    printf("api %d\n", $version);

    $checked      = 0;
    $mismatch     = 0;
    $skipCount    = 0;
    $inconclusive = 0;

    foreach ($methods as $method) {
        if (isset(SKIP_METHODS[$method])) {
            $skipCount++;
            $skipped[$method] = SKIP_METHODS[$method];
            continue;
        }

        $expected = $api->validate_command($method);
        $result   = probe(command_url($endpoint, $method, $auth, $username, $handshakeVersion), $timeout, $format);
        $checked++;

        if ($result === 'unreachable') {
            printf("  %-28s UNREACHABLE\n", $method);
            $mismatch++;
            continue;
        }

        $served = ($result === 'served');
        if ($served === $expected) {
            if ($verbose) {
                printf("  %-28s ok (%s)\n", $method, $result);
            }
            continue;
        }

        if (
            $expected
            && $result === 'not served'
            && in_array($version, AMBIGUOUS_REFUSAL_VERSIONS, true)
        ) {
            $inconclusive++;
            printf("  %-28s inconclusive: api%d reuses 405 for a missing parameter\n", $method, $version);
            continue;
        }

        $mismatch++;
        printf(
            "  %-28s MISMATCH: library says %s, server says %s\n",
            $method,
            $expected ? 'valid' : 'invalid',
            $result
        );
    }

    printf("  checked %d, skipped %d, inconclusive %d, mismatched %d\n\n", $checked, $skipCount, $inconclusive, $mismatch);
    $failures += $mismatch;
}

if ($skipped !== []) {
    printf("Not called, so not verified:\n");
    foreach ($skipped as $method => $reason) {
        printf("  %-28s %s\n", $method, $reason);
    }
    printf("\n");
}

if ($unverified !== []) {
    printf("Versions this server would not serve:\n");
    foreach ($unverified as $note) {
        printf("  %s\n", $note);
    }
    printf("\n");
}

if ($failures > 0) {
    printf("FAILED: %d disagreement(s) between the method maps and the server.\n", $failures);
    exit(1);
}

printf("OK: the method maps match the server.\n");
exit(0);
