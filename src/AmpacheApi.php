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

    private const API_VERSION = '6.8.1';

    private const API3_VERSION = '390001';

    private const API4_VERSION = '443000';

    private const API5_VERSION = '5.5.6';

    private const API3_METHOD_LIST = [
        'advanced_search',
        'album',
        'albums',
        'album_songs',
        'artist',
        'artist_albums',
        'artists',
        'artist_songs',
        'democratic',
        'followers',
        'following',
        'friends_timeline',
        'genre',
        'genre_albums',
        'genre_artists',
        'genres',
        'genre_songs',
        'handshake',
        'last_shouts',
        'localplay',
        'ping',
        'playlist',
        'playlist_add_song',
        'playlist_create',
        'playlist_delete',
        'playlist_remove_song',
        'playlists',
        'playlist_songs',
        'rate',
        'search_songs',
        'song',
        'songs',
        'stats',
        'tag',
        'tag_albums',
        'tag_artists',
        'tags',
        'tag_songs',
        'timeline',
        'toggle_follow',
        'url_to_song',
        'user',
        'video',
        'videos'
    ];

    private const API4_METHOD_LIST = [
        'advanced_search',
        'album',
        'albums',
        'album_songs',
        'artist',
        'artist_albums',
        'artists',
        'artist_songs',
        'catalog',
        'catalog_action',
        'catalog_file',
        'catalogs',
        'democratic',
        'download',
        'flag',
        'followers',
        'following',
        'friends_timeline',
        'genre',
        'genre_albums',
        'genre_artists',
        'genres',
        'genre_songs',
        'get_art',
        'get_indexes',
        'get_similar',
        'goodbye',
        'handshake',
        'last_shouts',
        'license',
        'licenses',
        'license_songs',
        'localplay',
        'ping',
        'playlist',
        'playlist_add_song',
        'playlist_create',
        'playlist_delete',
        'playlist_edit',
        'playlist_generate',
        'playlist_remove_song',
        'playlists',
        'playlist_songs',
        'podcast',
        'podcast_create',
        'podcast_delete',
        'podcast_edit',
        'podcast_episode',
        'podcast_episode_delete',
        'podcast_episodes',
        'podcasts',
        'rate',
        'record_play',
        'scrobble',
        'search_songs',
        'share',
        'share_create',
        'share_delete',
        'share_edit',
        'shares',
        'song',
        'songs',
        'stats',
        'stream',
        'tag',
        'tag_albums',
        'tag_artists',
        'tags',
        'tag_songs',
        'timeline',
        'toggle_follow',
        'update_art',
        'update_artist_info',
        'update_from_tags',
        'update_podcast',
        'url_to_song',
        'user',
        'user_create',
        'user_delete',
        'users',
        'user_update',
        'video',
        'videos'
    ];

    private const API5_METHOD_LIST = [
        'advanced_search',
        'album',
        'albums',
        'album_songs',
        'artist',
        'artist_albums',
        'artists',
        'artist_songs',
        'bookmark_create',
        'bookmark_delete',
        'bookmark_edit',
        'bookmarks',
        'catalog',
        'catalog_action',
        'catalog_file',
        'catalogs',
        'deleted_podcast_episodes',
        'deleted_songs',
        'deleted_videos',
        'democratic',
        'download',
        'flag',
        'followers',
        'following',
        'friends_timeline',
        'genre',
        'genre_albums',
        'genre_artists',
        'genres',
        'genre_songs',
        'get_art',
        'get_bookmark',
        'get_indexes',
        'get_similar',
        'goodbye',
        'handshake',
        'label',
        'label_artists',
        'labels',
        'last_shouts',
        'license',
        'licenses',
        'license_songs',
        'live_stream',
        'live_streams',
        'localplay',
        'localplay_songs',
        'ping',
        'playlist',
        'playlist_add_song',
        'playlist_create',
        'playlist_delete',
        'playlist_edit',
        'playlist_generate',
        'playlist_remove_song',
        'playlists',
        'playlist_songs',
        'podcast',
        'podcast_create',
        'podcast_delete',
        'podcast_edit',
        'podcast_episode',
        'podcast_episode_delete',
        'podcast_episodes',
        'podcasts',
        'preference_create',
        'preference_delete',
        'preference_edit',
        'rate',
        'record_play',
        'scrobble',
        'search_songs',
        'share',
        'share_create',
        'share_delete',
        'share_edit',
        'shares',
        'song',
        'song_delete',
        'songs',
        'stats',
        'stream',
        'system_preference',
        'system_preferences',
        'system_update',
        'tag',
        'tag_albums',
        'tag_artists',
        'tags',
        'tag_songs',
        'timeline',
        'toggle_follow',
        'update_art',
        'update_artist_info',
        'update_from_tags',
        'update_podcast',
        'url_to_song',
        'user',
        'user_create',
        'user_delete',
        'user_edit',
        'user_preference',
        'user_preferences',
        'users',
        'user_update',
        'video',
        'videos'
    ];

    private const API6_METHOD_LIST = [
        'advanced_search',
        'album_songs',
        'album',
        'albums',
        'artist_albums',
        'artist_songs',
        'artist',
        'artists',
        'bookmark_create',
        'bookmark_delete',
        'bookmark_edit',
        'bookmark',
        'bookmarks',
        'browse',
        'catalog_action',
        'catalog_add',
        'catalog_delete',
        'catalog_file',
        'catalog_folder',
        'catalog',
        'catalogs',
        'deleted_podcast_episodes',
        'deleted_songs',
        'deleted_videos',
        'democratic',
        'download',
        'flag',
        'followers',
        'following',
        'friends_timeline',
        'genre_albums',
        'genre_artists',
        'genre_songs',
        'genre',
        'genres',
        'get_art',
        'get_bookmark',
        'get_external_metadata',
        'get_indexes',
        'get_similar',
        'goodbye',
        'handshake',
        'index',
        'label_artists',
        'label',
        'labels',
        'last_shouts',
        'license_songs',
        'license',
        'licenses',
        'list',
        'live_stream_create',
        'live_stream_delete',
        'live_stream_edit',
        'live_stream',
        'live_streams',
        'localplay_songs',
        'localplay',
        'lost_password',
        'now_playing',
        'ping',
        'player',
        'playlist_add_song',
        'playlist_add',
        'playlist_create',
        'playlist_delete',
        'playlist_edit',
        'playlist_generate',
        'playlist_hash',
        'playlist_remove_song',
        'playlist_songs',
        'playlist',
        'playlists',
        'podcast_create',
        'podcast_delete',
        'podcast_edit',
        'podcast_episode_delete',
        'podcast_episode',
        'podcast_episodes',
        'podcast',
        'podcasts',
        'preference_create',
        'preference_delete',
        'preference_edit',
        'rate',
        'record_play',
        'register',
        'scrobble',
        'search_group',
        'search_rules',
        'search_songs',
        'search',
        'share_create',
        'share_delete',
        'share_edit',
        'share',
        'shares',
        'song_delete',
        'song_tags',
        'song',
        'songs',
        'stats',
        'stream',
        'system_preference',
        'system_preferences',
        'system_update',
        'tag_albums',
        'tag_artists',
        'tag_songs',
        'tag',
        'tags',
        'timeline',
        'toggle_follow',
        'update_art',
        'update_artist_info',
        'update_from_tags',
        'update_podcast',
        'url_to_song',
        'user_create',
        'user_delete',
        'user_edit',
        'user_playlists',
        'user_preference',
        'user_preferences',
        'user_smartlists',
        'user_update',
        'user',
        'users',
        'video',
        'videos',
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
    private int $server_version = 6; // the version of API responses the client expects

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
            $this->server_version = (int)substr((string)$config['server_version'], 0, 1);
        }
        if (isset($config['api_format']) && in_array($config['api_format'], ['xml', 'json'])) {
            $this->api_format = $config['api_format'];
        }

        // set the correct handshake version or fallback to 3 for invalid versions
        switch ($this->server_version) {
            case 3:
                $this->handshake_version = self::API3_VERSION;
                break;
            case 4:
                $this->handshake_version = self::API4_VERSION;
                break;
            case 5:
                $this->handshake_version = self::API5_VERSION;
                break;
            case 6:
            default:
                $this->handshake_version = self::API_VERSION;
        }

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
     * This takes the specified command and checks it against the known
     * commands for the current version of Ampache. If no version is known yet
     * it should return FALSE for everything except ping and handshake.
     */
    public function validate_command(string $command): bool
    {
        switch ($this->server_version) {
            case 3:
                return in_array($command, self::API3_METHOD_LIST);
            case 4:
                return in_array($command, self::API4_METHOD_LIST);
            case 5:
                return in_array($command, self::API5_METHOD_LIST);
            case 6:
                return in_array($command, self::API6_METHOD_LIST);
        }

        return false;
    }
}
