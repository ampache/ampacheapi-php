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
 * A stand-in for the parts of an Ampache server this library talks to.
 *
 * Only the dispatcher behaviour matters here: which http status carries which
 * error, and which body shape each api generation uses. Real command names are
 * used throughout because validate_command() refuses anything else.
 */

$action = $_GET['action'] ?? '';
$json   = (strpos($_SERVER['SCRIPT_NAME'] ?? '', 'json.server.php') !== false);

// answers any command with a redirect instead of its normal response, so a test can drive the client's
// redirect-following without a second stub server to redirect to; 'loop' redirects to the exact same
// request forever, for a test that wants a redirect chain that never resolves
if (isset($_GET['redirect_to'])) {
    $target = ($_GET['redirect_to'] === 'loop')
        ? ($_SERVER['REQUEST_URI'] ?? '')
        : $_GET['redirect_to'];

    http_response_code(302);
    header('Location: ' . $target);

    exit;
}

// redirects exactly redirect_count times before answering normally, so a test can land on the
// MAX_REDIRECTS boundary precisely instead of only proving an unbounded chain fails
if (isset($_GET['redirect_count']) && (int) $_GET['redirect_count'] > 0) {
    $query                     = $_GET;
    $query['redirect_count']   = (int) $_GET['redirect_count'] - 1;
    http_response_code(302);
    header('Location: /?' . http_build_query($query));

    exit;
}

// answers any command with deliberately unparsable output, so a test can drive the decode-failure path
if (isset($_GET['malformed'])) {
    http_response_code(200);
    if ($json) {
        header('Content-Type: application/json');
        echo '{not valid json';

        exit;
    }

    header('Content-Type: text/xml');
    echo 'not valid xml<<<';

    exit;
}

/**
 * Writes an error in the api5+ shape, under the http status api8 would use.
 */
$error = static function (int $http, int $code, string $message, string $type) use ($action, $json): void {
    http_response_code($http);
    if ($json) {
        header('Content-Type: application/json');
        echo json_encode(['error' => ['errorCode' => (string) $code, 'errorAction' => $action, 'errorType' => $type, 'errorMessage' => $message]]);

        return;
    }

    header('Content-Type: text/xml');
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<root>\n\t<error errorCode=\"$code\">\n\t\t<errorAction><![CDATA[$action]]></errorAction>\n\t\t<errorType><![CDATA[$type]]></errorType>\n\t\t<errorMessage><![CDATA[$message]]></errorMessage>\n\t</error>\n</root>\n";
};

// answers any command with a caller-chosen error code, so a probe-style caller can be tested against every code it classifies on
if (isset($_GET['respond_error_code'])) {
    $error((int) ($_GET['respond_error_http'] ?? 200), (int) $_GET['respond_error_code'], 'Probed error', 'system');

    exit;
}

if ($action === 'handshake') {
    // proves a client sees the server's own rejection, not just a url_validator refusal, when credentials are wrong
    if (($_GET['user'] ?? '') === 'baduser') {
        $error(401, 4701, 'Invalid Handshake', 'account');

        exit;
    }

    // echoes back the version the client asked for, so a test can simulate a server rolling the request up to a later one
    $reportedApi = (string) ($_GET['version'] ?? '6.9.1');

    if ($json) {
        header('Content-Type: application/json');
        echo json_encode(['auth' => 'SESSIONTOKEN', 'api' => $reportedApi, 'songs' => 5, 'artists' => 2]);

        exit;
    }

    header('Content-Type: text/xml');
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<root>\n\t<auth>SESSIONTOKEN</auth>\n\t<api>$reportedApi</api>\n\t<songs>5</songs>\n\t<artists>2</artists>\n</root>\n";

    exit;
}

switch ($action) {
    case 'song':
        // api3 to api6 answer 200 and put the error in the body
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['error' => ['errorCode' => '4704', 'errorAction' => 'song', 'errorType' => 'system', 'errorMessage' => 'Not Found']]);

            break;
        }

        header('Content-Type: text/xml');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<root>\n\t<error errorCode=\"4704\">\n\t\t<errorAction><![CDATA[song]]></errorAction>\n\t\t<errorType><![CDATA[system]]></errorType>\n\t\t<errorMessage><![CDATA[Not Found]]></errorMessage>\n\t</error>\n</root>\n";
        break;
    case 'catalogs':
        $error(400, 4710, 'Bad Request: filter', 'system');
        break;
    case 'user_edit':
        $error(403, 4742, 'Require: 100', 'account');
        break;
    case 'videos':
        $error(500, 4702, 'Generic Error', 'system');
        break;
    case 'video':
        // the api3 and api4 shape, where the message is the element body (json: a bare code/message pair)
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['error' => ['code' => '405', 'message' => 'Invalid Request']]);

            break;
        }

        header('Content-Type: text/xml');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<root>\n\t<error code=\"405\"><![CDATA[Invalid Request]]></error>\n</root>\n";
        break;
    case 'song_tags':
        // Catalog_remote feature-detects this: an empty result means the server predates song_tags, so it falls back per-song
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode([]);

            break;
        }

        header('Content-Type: text/xml');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<root>\n</root>\n";
        break;
    case 'songs':
        // api8 answers an empty result with a 404, and in json that body is a falsy []
        http_response_code(404);
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode([], JSON_PRETTY_PRINT);

            break;
        }

        header('Content-Type: text/xml');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<root>\n</root>\n";
        break;
    case 'artists':
        header('Content-Type: text/xml');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<root>\n\t<artist id=\"1\">\n\t\t<name>Tester</name>\n\t</artist>\n</root>\n";
        break;
    case 'genres':
        // reports what carried the session token, so a test can tell a header apart from the query string
        $httpAuth  = $_SERVER['HTTP_AUTH'] ?? '';
        $queryAuth = $_GET['auth'] ?? '';
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['http_auth' => $httpAuth, 'query_auth' => $queryAuth]);

            break;
        }

        header('Content-Type: text/xml');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<root>\n\t<http_auth><![CDATA[$httpAuth]]></http_auth>\n\t<query_auth><![CDATA[$queryAuth]]></query_auth>\n</root>\n";
        break;
    default:
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => 1]);

            break;
        }

        header('Content-Type: text/xml');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<root>\n\t<ok>1</ok>\n</root>\n";
}
