<?php

declare(strict_types=0);

/**
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPLv3)
 * Copyright 2001 - 2015 Ampache.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace AmpacheApi;

use Exception;
use SimpleXMLElement;

class AmpacheApi
{
    private const LIB_VERSION = '2.0.0-develop';

    /**
     * The api versions this library can talk to.
     *
     * Ampache has no version 7; it is rejected by the server rather than served.
     */
    private const API_VERSIONS = [3, 4, 5, 6, 8];

    private const DEFAULT_VERSION = 6;

    /**
     * The version string sent with the handshake for each api version.
     *
     * Ampache resolves the api version from the first character of this string.
     */
    private const HANDSHAKE_VERSIONS = [
        3 => '390001',
        4 => '443000',
        5 => '5.5.6',
        6 => '6.9.1',
        8 => '8.0.0',
    ];

    /**
     * The api version each method was introduced in.
     *
     * A method is valid from this version onwards unless it also appears in
     * METHOD_REMOVED_IN. Mirrors Api3, Api4, Api5, Api6 and Api METHOD_LIST on
     * the server; REST-only path aliases are deliberately left out because this
     * library speaks the RPC endpoint.
     */
    private const METHOD_MIN_VERSION = [
        'advanced_search' => 3,
        'album' => 3,
        'album_disk' => 8,
        'album_disk_songs' => 8,
        'album_disks' => 8,
        'album_songs' => 3,
        'albums' => 3,
        'artist' => 3,
        'artist_albums' => 3,
        'artist_songs' => 3,
        'artists' => 3,
        'bookmark' => 6,
        'bookmark_create' => 5,
        'bookmark_delete' => 5,
        'bookmark_edit' => 5,
        'bookmarks' => 5,
        'browse' => 6,
        'catalog' => 4,
        'catalog_action' => 4,
        'catalog_add' => 6,
        'catalog_create' => 6,
        'catalog_delete' => 6,
        'catalog_file' => 4,
        'catalog_folder' => 6,
        'catalogs' => 4,
        'collection' => 8,
        'collection_add' => 8,
        'collection_create' => 8,
        'collection_delete' => 8,
        'collection_edit' => 8,
        'collection_items' => 8,
        'collection_remove' => 8,
        'collections' => 8,
        'deleted_podcast_episodes' => 5,
        'deleted_songs' => 5,
        'deleted_videos' => 5,
        'democratic' => 3,
        'download' => 4,
        'flag' => 4,
        'folders' => 8,
        'followers' => 3,
        'following' => 3,
        'friends_timeline' => 3,
        'genre' => 3,
        'genre_albums' => 3,
        'genre_artists' => 3,
        'genre_songs' => 3,
        'genres' => 3,
        'get_art' => 4,
        'get_bookmark' => 5,
        'get_external_metadata' => 6,
        'get_indexes' => 4,
        'get_lyrics' => 6,
        'get_similar' => 4,
        'goodbye' => 4,
        'handshake' => 3,
        'index' => 6,
        'label' => 5,
        'label_artists' => 5,
        'labels' => 5,
        'last_shouts' => 3,
        'license' => 4,
        'license_songs' => 4,
        'licenses' => 4,
        'list' => 6,
        'live_stream' => 5,
        'live_stream_create' => 6,
        'live_stream_delete' => 6,
        'live_stream_edit' => 6,
        'live_streams' => 5,
        'localplay' => 3,
        'localplay_songs' => 5,
        'lost_password' => 6,
        'now_playing' => 6,
        'ping' => 3,
        'player' => 6,
        'playlist' => 3,
        'playlist_add' => 6,
        'playlist_add_song' => 3,
        'playlist_create' => 3,
        'playlist_delete' => 3,
        'playlist_edit' => 4,
        'playlist_generate' => 4,
        'playlist_hash' => 6,
        'playlist_remove' => 8,
        'playlist_remove_song' => 3,
        'playlist_songs' => 3,
        'playlists' => 3,
        'podcast' => 4,
        'podcast_create' => 4,
        'podcast_delete' => 4,
        'podcast_edit' => 4,
        'podcast_episode' => 4,
        'podcast_episode_delete' => 4,
        'podcast_episodes' => 4,
        'podcast_update' => 6,
        'podcasts' => 4,
        'preference_create' => 5,
        'preference_delete' => 5,
        'preference_edit' => 5,
        'random' => 8,
        'rate' => 3,
        'record_play' => 4,
        'register' => 6,
        'scrobble' => 4,
        'search' => 3,
        'search_group' => 6,
        'search_rules' => 6,
        'search_songs' => 3,
        'share' => 4,
        'share_create' => 4,
        'share_delete' => 4,
        'share_edit' => 4,
        'shares' => 4,
        'smartlist' => 6,
        'smartlist_delete' => 6,
        'smartlist_songs' => 6,
        'smartlists' => 6,
        'song' => 3,
        'song_delete' => 5,
        'song_tags' => 6,
        'songs' => 3,
        'sonic_match' => 8,
        'stats' => 3,
        'stream' => 4,
        'system_preference' => 5,
        'system_preferences' => 5,
        'system_update' => 5,
        'tag' => 3,
        'tag_albums' => 3,
        'tag_artists' => 3,
        'tag_songs' => 3,
        'tags' => 3,
        'timeline' => 3,
        'toggle_follow' => 3,
        'update_art' => 4,
        'update_artist_info' => 4,
        'update_from_tags' => 4,
        'update_podcast' => 4,
        'url_to_song' => 3,
        'user' => 3,
        'user_create' => 4,
        'user_delete' => 4,
        'user_edit' => 5,
        'user_playlists' => 6,
        'user_preference' => 5,
        'user_preferences' => 5,
        'user_smartlists' => 6,
        'user_update' => 4,
        'users' => 4,
        'video' => 3,
        'videos' => 3,
    ];

    /**
     * The api version that dropped each method.
     *
     * The server still routes these but answers with a "Deprecated" error, so
     * they are rejected here before the request is made.
     */
    private const METHOD_REMOVED_IN = [
        'get_indexes' => 8,
        'playlist_add_song' => 8,
        'tag' => 5,
        'tag_albums' => 5,
        'tag_artists' => 5,
        'tag_songs' => 5,
        'tags' => 5,
        'user_update' => 8,
    ];

    // General Settings
    private string $username;

    private string $password;

    private bool $api_secure = true;

    // Handshake variables

    /** @var array<string, mixed>|SimpleXMLElement|null */
    private $handshake;

    private string $handshake_version;

    // Response variables
    private int $server_version = self::DEFAULT_VERSION; // the version of API responses the client expects

    private string $api_format = 'xml'; // the version of API responses the client expects

    // Constructed variables
    private bool $_debug_output = false;

    /** @property callable|null $_debug_callback */
    private $_debug_callback = null;

    private ?string $api_auth = null;

    private string $api_state = 'UNCONFIGURED';

    private string $api_url;

    /**
     * Constructor
     *
     * If enough information is provided then we will attempt to connect right
     * away, otherwise we will simply return an object that can be reconfigured
     * and manually connected.
     * @param array{
     *   username: string,
     *   password: string,
     *   server: string,
     *   debug?: ?bool,
     *   debug_callback?: string,
     *   api_secure?: bool,
     *   api_format?: string,
     *   server_version?: int|string
     * } $config
     * @throws Exception
     */
    public function __construct(array $config)
    {
        // See if we are setting debug first
        if (isset($config['debug'])) {
            $this->_debug_output = (bool)$config['debug'];
        }

        if (isset($config['debug_callback'])) {
            $this->_debug_callback = $config['debug_callback'];
        }

        $this->configure($config);

        // If we've been READY'd then go ahead and attempt to connect
        if ($this->state() == 'READY') {
            $this->connect();
        }
    }

    /**
     * _debug
     *
     * Make debugging all nice and pretty.
     */
    private function _debug(string $source, string $message): void
    {
        if ($this->_debug_output) {
            echo "$source :: $message\n";
        }

        if (!is_null($this->_debug_callback)) {
            call_user_func($this->_debug_callback, (self::class . '/' . self::LIB_VERSION), "$source :: $message", 5);
        }
    }

    /**
     * connect
     *
     * This attempts to connect to the Ampache instance.
     * @throws Exception
     */
    public function connect(): bool
    {
        // Set up the handshake
        $time = time();

        // Check that your key is encrypted with sha256 and hash it if not.
        $key  = (preg_match('/^[a-f0-9]{64}$/i', $this->password) === 1)
            ? $this->password
            : hash('sha256', $this->password);

        $passphrase = hash('sha256', $time . $key);

        $this->_debug('CONNECT', "Using " . $this->username . " / " . $passphrase);

        $options = [
            'timestamp' => $time,
            'auth' => $passphrase,
            'version' => $this->handshake_version,
            'user' => $this->username
        ];

        $results = $this->send_command('handshake', $options);

        $auth = null;
        if ($results) {
            switch ($this->api_format) {
                case 'json':
                    $auth = (is_array($results) && isset($results["auth"]))
                        ? $results["auth"]
                        : null;
                    break;
                case 'xml':
                default:
                    $auth = ($results instanceof SimpleXMLElement && !empty($results->auth))
                        ? (string)$results->auth
                        : null;
            }
        }

        // update on successful authentication
        if ($auth) {
            $this->set_state('CONNECTED');
            $this->api_auth  = $auth;
            $this->handshake = $results;

            return true;
        }

        $this->set_state('ERROR');

        return false;
    }

    /**
     * configure
     *
     * This function takes an array of elements and configures the AmpacheApi
     * object. It doesn't really do anything fancy, but it's a separate function
     * so it can be called both from the constructor and directly.
     * @param array{
     *   username: string,
     *   password: string,
     *   server: string,
     *   debug?: ?bool,
     *   debug_callback?: string,
     *   api_secure?: bool,
     *   api_format?: string,
     *   server_version?: int|string
     * } $config
     */
    public function configure(array $config): bool
    {
        //$this->_debug('CONFIGURE', 'Checking passed config options');

        if (!$config['server'] || !$config['username'] || !$config['password']) {
            trigger_error('AmpacheApi::configure received invalid data, unable to configure');

            return false;
        }

        $this->username = $config['username'];
        $this->password = $config['password'];

        if (isset($config['server_version'])) {
            // Ampache resolves the api version from the first character as well
            $version = (int)substr((string)$config['server_version'], 0, 1);
            if (!in_array($version, self::API_VERSIONS)) {
                $this->_debug('CONFIGURE', 'Unknown api version ' . $version . ', falling back to ' . self::DEFAULT_VERSION);
                $version = self::DEFAULT_VERSION;
            }

            $this->server_version = $version;
        }
        if (isset($config['api_format']) && in_array($config['api_format'], ['xml', 'json'])) {
            $this->api_format = $config['api_format'];
        }

        // set the handshake version string for the api version we're talking to
        $this->handshake_version = self::HANDSHAKE_VERSIONS[$this->server_version];

        if (isset($config['api_secure'])) {
            // This should be a boolean response
            $this->api_secure = (bool)$config['api_secure'];
        }
        $protocol = $this->api_secure
            ? 'https://'
            : 'http://';

        // Replace any http:// in the URL with ''
        $config['server'] = str_replace($protocol, '', $config['server']);
        $server           = htmlentities($config['server'], ENT_QUOTES, 'UTF-8');
        $this->api_url    = $protocol . $server . '/server/' . $this->api_format . '.server.php';

        // See if we have enough to authenticate, if so change the state
        if (!empty($this->username)) {
            $this->set_state('READY');
        }

        return true;
    }

    /**
     * set_state
     *
     * This sets the current state of the API, it is used mostly internally but
     * the state can be accessed externally so it could be used to check and see
     * where the API is at, at this moment
     */
    public function set_state(string $state): void
    {
        // Very simple for now, maybe we'll do something more with this later
        $this->api_state = strtoupper($state);
    }

    /**
     * state
     *
     * This returns the state of the API.
     */
    public function state(): string
    {
        return $this->api_state;
    }

    /**
     * info
     *
     * Returns the information gathered by the handshake.
     * Not raw so we can format it if we want?
     * @return array<string, mixed>|SimpleXMLElement|null
     * @throws Exception
     */
    public function info()
    {
        if ($this->state() != 'CONNECTED') {
            throw new Exception('AmpacheApi::info API in non-ready state, unable to return info');
        }

        return $this->handshake;
    }

    /**
     * get_command_url
     *
     * This builds and returns the command URL for the specified command and
     * options.
     * @param array<string, mixed> $options
     * @return string
     * @throws Exception
     */
    public function get_command_url(string $command, ?array $options = []): string
    {
        $command = trim($command);
        if (!$command) {
            throw new Exception('AmpacheApi::send_command no command specified');
        }
        if (!$this->validate_command($command)) {
            throw new Exception('AmpacheApi::send_command Invalid/Unknown command ' . $command . ' issued');
        }

        $url = $this->api_url . '?action=' . urlencode($command);

        if (is_array($options) && !empty($options)) {
            foreach ($options as $key => $value) {
                $key = trim($key);
                if (!$key) {
                    // Nonfatal, don't need to throw an exception
                    trigger_error('AmpacheApi::send_command unable to append empty variable to command');
                    continue;
                }
                $url .= '&' . urlencode($key) . '=' . urlencode($value);
            }
        }

        // If auth is set then we append it so callers don't have to.
        if ($this->api_auth) {
            $url .= '&auth=' . urlencode($this->api_auth) . '&username=' . urlencode($this->username);
        }

        return $url;
    }

    /**
     * send_command
     *
     * This sends an API command with options to the currently connected
     * host.
     * @param array<string, mixed> $options
     * @return array<string, mixed>|SimpleXMLElement|null
     * @throws Exception
     */
    public function send_command(string $command, ?array $options = [])
    {
        $this->_debug('SEND COMMAND', $command . ' ' . json_encode($options));

        if ($this->state() != 'READY' && $this->state() != 'CONNECTED') {
            throw new Exception('AmpacheApi::send_command API in non-ready state, unable to send');
        }
        $command = trim($command);
        if (!$command) {
            throw new Exception('AmpacheApi::send_command no command specified');
        }
        if (!$this->validate_command($command)) {
            throw new Exception('AmpacheApi::send_command Invalid/Unknown command ' . $command . ' issued');
        }

        $url = $this->get_command_url($command, $options);

        $this->_debug('COMMAND URL', $url);

        $data = file_get_contents($url);
        if (!$data) {
            return null;
        }

        $result = null;
        switch ($this->api_format) {
            case 'json':
                $result = json_decode($data, true);
                break;
            case 'xml':
                $result = simplexml_load_string($data);
        }

        if (!$result) {
            $this->_debug('EMPTY RESPONSE', $command);

            return null;
        }

        return $result;
    }

    /**
     * validate_command
     *
     * This takes the specified command and checks that the configured version
     * of Ampache serves it; unknown commands and commands that have been added
     * or dropped in another version are rejected before we hit the network.
     */
    public function validate_command(string $command): bool
    {
        $minimum = self::METHOD_MIN_VERSION[$command] ?? null;
        if (
            $minimum === null ||
            $this->server_version < $minimum
        ) {
            return false;
        }

        $removed = self::METHOD_REMOVED_IN[$command] ?? null;

        return (
            $removed === null ||
            $this->server_version < $removed
        );
    }
}
