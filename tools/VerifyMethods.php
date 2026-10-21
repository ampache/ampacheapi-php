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
 * The pure helpers behind tools/verify-methods.php, split out so they can be
 * unit tested: this file has no entry-point code of its own, unlike the CLI
 * script that requires it.
 */

use AmpacheApi\AmpacheApi;

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

/**
 * check_version
 *
 * Runs every check tools/verify-methods.php makes for one api version against an already-constructed client,
 * doing no output of its own so the caller can print it and so a test can assert on the result directly.
 * @param string[] $methods
 * @param array<string, int> $minimums
 * @param array<int, string> $handshakeVersions
 * @return array<string, mixed>
 */
function check_version(AmpacheApi $api, int $version, string $endpoint, string $format, string $username, int $timeout, array $methods, array $minimums, array $handshakeVersions): array
{
    if ($api->state() !== 'CONNECTED') {
        return ['outcome' => 'handshake_failed', 'detail' => $api->state()];
    }

    $handshake = $api->info();
    $auth      = ($format === 'json')
        ? (is_array($handshake) && isset($handshake['auth']) ? (string) $handshake['auth'] : '')
        : (($handshake instanceof SimpleXMLElement && !empty($handshake->auth)) ? (string) $handshake->auth : '');

    if ($auth === '') {
        return ['outcome' => 'no_auth'];
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
        return ['outcome' => 'version_not_honoured', 'detail' => (string) $answered, 'canary' => false];
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
            return ['outcome' => 'version_not_honoured', 'detail' => $canary, 'canary' => true];
        }
    }

    $checked        = 0;
    $mismatch       = 0;
    $skipCount      = 0;
    $inconclusive   = 0;
    $events         = [];
    $skippedMethods = [];

    foreach ($methods as $method) {
        if (isset(SKIP_METHODS[$method])) {
            $skipCount++;
            $skippedMethods[$method] = SKIP_METHODS[$method];
            continue;
        }

        $expected = $api->validate_command($method);
        $result   = probe(command_url($endpoint, $method, $auth, $username, $handshakeVersion), $timeout, $format);
        $checked++;

        if ($result === 'unreachable') {
            $mismatch++;
            $events[] = ['method' => $method, 'type' => 'unreachable'];
            continue;
        }

        $served = ($result === 'served');
        if ($served === $expected) {
            $events[] = ['method' => $method, 'type' => 'ok', 'result' => $result];
            continue;
        }

        if (
            $expected
            && $result === 'not served'
            && in_array($version, AMBIGUOUS_REFUSAL_VERSIONS, true)
        ) {
            $inconclusive++;
            $events[] = ['method' => $method, 'type' => 'inconclusive'];
            continue;
        }

        $mismatch++;
        $events[] = ['method' => $method, 'type' => 'mismatch', 'expected' => $expected, 'result' => $result];
    }

    return [
        'outcome' => 'checked',
        'events' => $events,
        'checked' => $checked,
        'skipped' => $skipCount,
        'inconclusive' => $inconclusive,
        'mismatched' => $mismatch,
        'skippedMethods' => $skippedMethods,
    ];
}
