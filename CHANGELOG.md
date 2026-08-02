# Changelog

## AmpacheApi 2.0.0

**NOTE** `send_command()` no longer normalises responses; it hands back what the server sent, so callers written against 1.x need updating.
This version supports Ampache 6.2.0 and newer.

### Added (2.0.0)

* Support for API version 8, alongside 3, 4, 5 and 6
* `get_command_url()` builds a signed command url without sending it, for handing `stream` and `download` urls straight to a player
* `last_error()` returns the api error from the last `send_command()` as `code`, `message`, `action`, `type` and `http`
* Config options
  * `api_format` selects `xml` or `json` responses
  * `server_version` selects the api version to talk to, defaulting to 6
  * `timeout` sets the per-request timeout in seconds

### Changed (2.0.0)

* `send_command()` returns a `SimpleXMLElement` or a decoded json array as the server sent it, replacing the structure 1.x assembled
* Commands are checked against the api version in use, so a method that version does not serve is refused before the request is sent
* `tag`, `tags`, `tag_albums`, `tag_artists` and `tag_songs` are refused from API5 onwards, and `get_indexes`, `playlist_add_song` and `user_update` from API8, matching the versions that dropped them
* `api_secure` is consulted only when it is passed; otherwise the scheme on `server` selects `https` or `http`, and `https` remains the fallback when neither says
* `__construct()` requires a config array, where 1.x defaulted it to an empty one
* `debug_callback` receives `AmpacheApi\AmpacheApi/<version>` as its first argument in place of the bare `AmpacheApi` 1.x sent

### Removed (2.0.0)

* The public methods 1.x used to assemble its own response structure, made redundant by `send_command()` handing back the decoded response
  * `parse_response()`
  * `get_response()`
  * `XML_create_parser()`
  * `XML_cdata()`
  * `XML_start_element()`
  * `XML_end_element()`

### Fixed (2.0.0)

* Session tokens and handshake passphrases were written to `debug_callback` even with `debug` turned off
* API8 errors and empty results were reported as no response at all, because they carry an http status where API3 to API6 answer 200
* A `ping` moved the session onto the server's default api version, so every later command was answered by a version the client never asked for
* A server address whose scheme disagreed with `api_secure` produced an unusable url
* Passing a non-scalar value as a command option raised a `TypeError`
* A `debug_callback` that cannot be called is now reported when it is set, rather than failing on the first debug message
* A missing `server`, `username` or `password` raised an undefined key warning before the configuration check ran

## AmpacheApi 1.0.3

**NOTE** The 1.x releases shipped without notes, so the 1.x entries here were reconstructed from the git history after the fact.

This release carries no functional change, only a comment recording that the handshake hashes a password it may already have received hashed.
The ambiguity it noted is resolved in 2.0.0, which detects a sha256 password rather than assuming one.

## AmpacheApi 1.0.2

### Added (1.0.2)

* `.php-cs-fixer.php`, so the code style is enforced rather than described

### Changed (1.0.2)

* The handshake hashes the password before combining it with the timestamp, sending `sha256(timestamp . sha256(password))`, so a plain password is what callers pass
* `configure()` reaches the `READY` state on a `username` and a `server`; the password is no longer part of that check
* `connect()` returns `true` on a successful handshake, where it previously returned nothing at all

### Fixed (1.0.2)

* Attributes arriving on a child element that had already been read as text replaced the first character of that text, turning `Some Title` into `Aome Title` alongside an illegal offset warning

## AmpacheApi 1.0.1

### Changed (1.0.1)

* Relicensed from GPLv2 to AGPLv3

### Fixed (1.0.1)

* The class could not be autoloaded, because PSR-4 looks for `AmpacheApi.php` and the file was named `AmpacheApi.lib.php`
* Every `throw` raised a "class not found" fatal in place of the exception it meant to report, because an unqualified `Exception` resolves inside the namespace the 1.0.0 packaging introduced

## AmpacheApi 1.0.0

First packaged release, extracted from Ampache's own tree.

### Added (1.0.0)

* `composer.json`, installable as `ampache/ampacheapi-php` with PSR-4 autoloading from `src/` and a PHP 5.4 floor
* The `AmpacheApi` namespace
* `user`, `users`, `shouts` and `timeline` are read as parent elements, so each one starts its own result entry instead of being folded into the element before it

