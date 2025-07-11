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

$ampache = new AmpacheApi($config);
if ($ampache->state() != 'CONNECTED') {
    echo "Ampache API client failed to connect.\n";
    exit;
}

// Get server stats

$stats = $ampache->info();
echo "Songs: " . $stats['songs'] . "<br />\n";
echo "Albums: " . $stats['albums'] . "<br />\n";
echo "Artists: " . $stats['artists'] . "<br />\n";
echo "Playlists: " . $stats['playlists'] . "<br />\n";
echo "Videos: " . $stats['videos'] . "<br />\n";

// Get all artists

$total = $stats['artists'];
$step  = 500; // Request per 500
$start = 0;

echo "Artists: <br />\n";
while ($total > $start) {
    $artists = $ampache->send_command('artists', ['offset' => $start, 'limit' => $step]);
    foreach ($artists['artist'] as $artist) {
        echo "\t" . $artist['name'] . "\n";
    }
    $start = $start + $step;
}

echo "COMPLETED\n";
