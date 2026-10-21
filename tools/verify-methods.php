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
require __DIR__ . '/VerifyMethods.php';

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

    $outcome = check_version($api, $version, $endpoint, $format, $username, $timeout, $methods, $minimums, $handshakeVersions);

    if ($outcome['outcome'] === 'handshake_failed') {
        printf("api %d: handshake FAILED, skipping (state %s)\n\n", $version, $outcome['detail']);
        $failures++;
        continue;
    }
    if ($outcome['outcome'] === 'no_auth') {
        printf("api %d: connected but no auth token in the handshake, skipping\n\n", $version);
        $failures++;
        continue;
    }
    if ($outcome['outcome'] === 'version_not_honoured' && !$outcome['canary']) {
        printf("api %d: server answered as api %s, so this version is not enabled here; not verified\n\n", $version, $outcome['detail']);
        $unverified[] = sprintf('api %d (handshake answered as api %s)', $version, $outcome['detail']);
        continue;
    }
    if ($outcome['outcome'] === 'version_not_honoured') {
        printf("api %d: server served '%s', which belongs to a later version, so it is not honouring api %d; not verified\n\n", $version, $outcome['detail'], $version);
        $unverified[] = sprintf("api %d (server served '%s', a later version's method)", $version, $outcome['detail']);
        continue;
    }

    printf("api %d\n", $version);

    foreach ($outcome['skippedMethods'] as $method => $reason) {
        $skipped[$method] = $reason;
    }

    foreach ($outcome['events'] as $event) {
        switch ($event['type']) {
            case 'unreachable':
                printf("  %-28s UNREACHABLE\n", $event['method']);
                break;
            case 'ok':
                if ($verbose) {
                    printf("  %-28s ok (%s)\n", $event['method'], $event['result']);
                }
                break;
            case 'inconclusive':
                printf("  %-28s inconclusive: api%d reuses 405 for a missing parameter\n", $event['method'], $version);
                break;
            default:
                printf(
                    "  %-28s MISMATCH: library says %s, server says %s\n",
                    $event['method'],
                    $event['expected'] ? 'valid' : 'invalid',
                    $event['result']
                );
        }
    }

    printf("  checked %d, skipped %d, inconclusive %d, mismatched %d\n\n", $outcome['checked'], $outcome['skipped'], $outcome['inconclusive'], $outcome['mismatched']);
    $failures += $outcome['mismatched'];
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
