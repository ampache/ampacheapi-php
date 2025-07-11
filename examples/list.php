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
    'api_format' => 'xml',
];

try {
    $ampache = new AmpacheApi($config);
    if ($ampache->state() != 'CONNECTED') {
        echo "Ampache API client failed to connect.\n";
        exit;
    }

    print_r($ampache->send_command('songs', ['limit' => 10, 'offset' => 0]));

} catch (Exception $exception) {
    die($exception->getMessage());
}