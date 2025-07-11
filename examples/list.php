<?php

use AmpacheApi\AmpacheApi;

require dirname(__DIR__) . '/vendor/autoload.php';

// your own username and password are required to use Ampache server
$username = 'demo';
$password = hash('sha256', 'demodemo');
$server   = 'develop.ampache.dev';

$config  = [
    'username' => $username,
    'password' => $password,
    'server' => $server,
    'debug' => true,
    'debug_callback' => null,
    'api_version' => 6,
    'api_format' => 'json',
];

echo "Connecting to Ampache API at $server with user $username...\n";

try {
    echo "JSON connection...\n";
    $ampache = new AmpacheApi($config);
    if ($ampache->state() != 'CONNECTED') {
        echo "Ampache API client failed to connect.\n";
        exit;
    }

    print_r($ampache->send_command('songs', ['limit' => 1, 'offset' => 0]));

} catch (Exception $exception) {
    die($exception->getMessage());
}

try {
    echo "XML connection...\n";
    $config['api_format'] = 'xml';

    $ampache = new AmpacheApi($config);
    if ($ampache->state() != 'CONNECTED') {
        echo "Ampache API client failed to connect.\n";
        exit;
    }

    print_r($ampache->send_command('songs', ['limit' => 1, 'offset' => 0]));

} catch (Exception $exception) {
    die($exception->getMessage());
}