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

```php
$ampache = new AmpacheApi(array(
   'username' => 'user1', // Username
   'password' => 'test', // Password
   'server' => 'localhost', // Server address, without http/https prefix
   'debug_callback' => 'debug_event', // server callback function
   'api_secure' => 'false' // Set to true to use https
   'api_version' => 6 // Set API response version. 3, 4, 5, 6 (default: 6)
   'api_format' => 'xml' // Set API response format. xml, json (default: json)
));

if ($ampache->state() != 'CONNECTED') {
  echo "Ampache API client failed to connected.\n";
  exit;
}
```

### Get server stats

```php
$stats = $ampache->info();
echo "Songs: " . $stats->songs . "<br />\n";
echo "Albums: " . $stats->albums . "<br />\n";
echo "Artists: " . $stats->artists . "<br />\n";
echo "Playlists: " . $stats->playlists . "<br />\n";
echo "Videos: " . $stats->videos . "<br />\n";
```

### Get all artists

```php
$total = $stats->artists;
$step = 500; // Request per 500
$start = 0;

echo "Artists: <br />\n";
while ($total > $start) {
  $artists = $ampache->send_command('artists', array('offset' => $start, 'limit' => $step));
  foreach ($artists as $artist) {
    echo "\t" . $artist->name . "\n"
  }
}
```
