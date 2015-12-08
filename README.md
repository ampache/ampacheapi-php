# ampacheapi-php
Ampache API PHP Library.

## License
Ampache API PHP Library is free software; you can redistribute it and/or modify it under the terms of the GNU Affero General Public License v3 (AGPLv3) as published by the Free Software Foundation.

## Get Started

### Initialize
```php
$ampache = new AmpacheApi(array(
   'server' => 'localhost', // Server address, without http/https prefix
   'username' => 'user1', // Username
   'password' => 'test', // Password
   'api_secure' => 'false' // Set to true to use https
));

if ($ampache->state() != 'CONNECTED') {
  echo "Ampache API client failed to connected.\n";
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
$step = 500; // Request per 500
$start = 0;

echo "Artists: <br />\n";
while ($total > $start) {
  $artists = $ampache->send_command('artists', array('offset' => $start, 'limit' => $step));
  foreach ($artists as $artist) {
    echo "\t" . $artist['name'] . "\n"
  }
}
```
