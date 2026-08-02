<?php

declare(strict_types=0);

/**
 * A stand-in for the parts of an Ampache server this library talks to.
 *
 * Only the dispatcher behaviour matters here: which http status carries which
 * error, and which body shape each api generation uses. Real command names are
 * used throughout because validate_command() refuses anything else.
 */

$action = $_GET['action'] ?? '';
$json   = (strpos($_SERVER['SCRIPT_NAME'] ?? '', 'json.server.php') !== false);

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

if ($action === 'handshake') {
    if ($json) {
        header('Content-Type: application/json');
        echo json_encode(['auth' => 'SESSIONTOKEN', 'api' => '6.9.1', 'songs' => 5, 'artists' => 2]);

        exit;
    }

    header('Content-Type: text/xml');
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<root>\n\t<auth>SESSIONTOKEN</auth>\n\t<api>6.9.1</api>\n\t<songs>5</songs>\n\t<artists>2</artists>\n</root>\n";

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
        // the api3 and api4 shape, where the message is the element body
        header('Content-Type: text/xml');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<root>\n\t<error code=\"405\"><![CDATA[Invalid Request]]></error>\n</root>\n";
        break;
    case 'songs':
        // api8 answers an empty result with a 404 and an empty root
        http_response_code(404);
        header('Content-Type: text/xml');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<root>\n</root>\n";
        break;
    case 'artists':
        header('Content-Type: text/xml');
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n<root>\n\t<artist id=\"1\">\n\t\t<name>Tester</name>\n\t</artist>\n</root>\n";
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
