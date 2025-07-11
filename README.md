# ampacheapi-php

Ampache API PHP Library.

## NEWS

Version 2 of this library is underway.

There are major changes to help support multiple purporses as well as both XML and JSON responses.

The library will now return a SimpleXMLElement or json_encode string instead of the frankenstein.

This version of the library is supported from Ampache 6.2.0+

## License

Ampache API PHP Library is free software; you can redistribute it and/or modify it under the terms of the GNU Affero General Public License v3 (AGPLv3) as published by the Free Software Foundation.

## Get Started

### Initialize

When creating an AmpacheApi object config is set using a config array.

* Required
  * username: string - Your API username
  * password: string - sha256 hashed API password
  * server: string - Server address, without http/https prefix
* Optional
  * debug: bool - Enable debug mode which will echo debug messages (default: false)
  * debug_callback: string - server callback debugging function (default: null)
  * api_secure: bool - Set to false to use http (default: true)
  * api_format: string - Set API response version. 3, 4, 5, 6 (default: 6)
  * server_version: int - Set API response format. xml, json (default: json)

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
    'api_version' => 6,
    'api_format' => 'json',
];

$ampache = new AmpacheApi($config);
if ($ampache->state() != 'CONNECTED') {
    echo "Ampache API client failed to connect.\n";
    exit;
}
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