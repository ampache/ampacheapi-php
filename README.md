# ampacheapi-php

Ampache API PHP Library.

## NEWS

Version 2 of this library is underway.

There are major changes to help support multiple purposes as well as both XML and JSON responses.

The library will now return a SimpleXMLElement or a decoded JSON array instead of the frankenstein.

This version of the library is supported from Ampache 6.2.0+ and talks to API versions 3, 4, 5, 6 and 8. (Ampache has no API 7.)

## License

Ampache API PHP Library is free software; you can redistribute it and/or modify it under the terms of the GNU Affero General Public License v3 (AGPLv3) as published by the Free Software Foundation.

## Get Started

### Initialize

When creating an AmpacheApi object config is set using a config array.

* Required
  * username: string - Your API username
  * password: string - Your API password, plain or already sha256 hashed
  * server: string - Server address; an `http://` or `https://` prefix is stripped, and `api_secure` decides which is used
* Optional
  * debug: bool - Enable debug mode which will echo debug messages (default: false)
  * debug_callback: callable - Function that receives debug messages; it is called whether or not `debug` is on (default: null)
  * api_secure: bool - Set to false to use http (default: true)
  * api_format: string - Set API response format. xml, json (default: xml)
  * server_version: int - Set API response version. 3, 4, 5, 6, 8 (default: 6)
  * timeout: int - Per request timeout in seconds (default: PHP's `default_socket_timeout`)

So as an example; this is how the Ampache server would initialize the library.

```php
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
    'server_version' => 6,
    'api_format' => 'json',
];

$ampache = new AmpacheApi($config);
if ($ampache->state() != 'CONNECTED') {
    echo "Ampache API client failed to connect.\n";
    exit;
}
```

An unrecognised `server_version` falls back to 6 rather than leaving the client unable to send anything.

### Handling errors

`send_command()` hands back whatever the server sent, decoded. An API error is a normal response carrying an `error` element, so check `last_error()` to find out whether the call worked.

```php
$songs = $ampache->send_command('songs', ['limit' => 10]);
$error = $ampache->last_error();
if ($error !== null) {
    echo "Ampache said: " . $error['message'] . " (code " . $error['code'] . ")\n";
}
```

`last_error()` returns null after a successful call, or an array of `code`, `message`, `action`, `type` and `http`. A `type` of `transport` means no readable answer arrived at all. API 8 reports errors with a real HTTP status where API 3 to 6 always answer 200, so `http` is 200 on the older versions.

### Building a URL without sending it

`get_command_url()` signs a command the same way `send_command()` does but returns the URL, which is how you hand a `stream` or `download` link to a player.

```php
$url = $ampache->get_command_url('stream', ['filter' => $song_id, 'type' => 'song']);
```

### Get server stats

```php
$stats = $ampache->info();
echo "Songs: " . $stats['songs'] . "<br />\n";
echo "Albums: " . $stats['albums'] . "<br />\n";
echo "Artists: " . $stats['artists'] . "<br />\n";
echo "Playlists: " . $stats['playlists'] . "<br />\n";
echo "Videos: " . $stats['videos'] . "<br />\n";
```

### Get all artists

```php
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
```

Look in the [/examples](https://github.com/ampache/ampacheapi-php/tree/master/examples) folder for more.