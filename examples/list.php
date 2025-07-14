<?php

use AmpacheApi\AmpacheApi;

require dirname(__DIR__) . '/vendor/autoload.php';

// your own username and password are required to use Ampache server
$username = 'demo';
$password = 'demodemo';
$hash     = hash('sha256', $password);
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

    // connect with plain text password first
    $ampache = new AmpacheApi($config);
    if ($ampache->state() != 'CONNECTED') {
        echo "Ampache API client failed to connect using a plain text password.\n";
        exit;
    }

    $config['password'] = $hash;
    // connect with hashed password to emulate stored database password
    $ampache = new AmpacheApi($config);

    if ($ampache->state() != 'CONNECTED') {
        echo "Ampache API client failed to connect using a sha256 hash password.\n";
        exit;
    }
    $config['password'] = $password;

    print_r($ampache->send_command('songs', ['limit' => 1, 'offset' => 0]));

} catch (Exception $exception) {
    die($exception->getMessage());
}

try {
    echo "XML connection...\n";
    $config['api_format'] = 'xml';

    // connect with plain text password first
    $ampache = new AmpacheApi($config);
    if ($ampache->state() != 'CONNECTED') {
        echo "Ampache API client failed to connect using a plain text password.\n";
        exit;
    }

    $config['password'] = $hash;
    // connect with hashed password to emulate stored database password
    $ampache = new AmpacheApi($config);
    if ($ampache->state() != 'CONNECTED') {
        echo "Ampache API client failed to connect using a sha256 hash password.\n";
        exit;
    }
    $config['password'] = $password;

    print_r($ampache->send_command('songs', ['limit' => 1, 'offset' => 0]));

} catch (Exception $exception) {
    die($exception->getMessage());
}

echo "COMPLETED\n";
