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
  * `api_secure` selects `https` or `http`

### Changed (2.0.0)

* `send_command()` returns a `SimpleXMLElement` or a decoded json array as the server sent it, replacing the structure 1.x assembled
* Commands are checked against the api version in use, so a method that version does not serve is refused before the request is sent
* `tag`, `tags`, `tag_albums`, `tag_artists` and `tag_songs` are refused from API5 onwards, and `get_indexes`, `playlist_add_song` and `user_update` from API8, matching the versions that dropped them

### Fixed (2.0.0)

* Session tokens and handshake passphrases were written to `debug_callback` even with `debug` turned off
* API8 errors and empty results were reported as no response at all, because they carry an http status where API3 to API6 answer 200
* A `ping` moved the session onto the server's default api version, so every later command was answered by a version the client never asked for
* A server address whose scheme disagreed with `api_secure` produced an unusable url
* Passing a non-scalar value as a command option raised a `TypeError`
* A `debug_callback` that cannot be called is now reported when it is set, rather than failing on the first debug message
* A missing `server`, `username` or `password` raised an undefined key warning before the configuration check ran
