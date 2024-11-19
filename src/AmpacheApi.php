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
    private const LIB_VERSION      = '2.0.0-develop';
    private const API_VERSION      = '6.1.0';
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
        'search_songs',
        'search',
        'share_create',
        'share_delete',
        'share_edit',
        'share',
        'shares',
        'song_delete',
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
    private $server;
    private $username;
    private $password;
    private $api_secure;

    // Handshake variables
    /** @var string|SimpleXMLElement|null */
    private $handshake;
    private int $handshake_time; // Used to figure out how stale our data is
    private string $handshake_version;

    // Response variables
    private int $server_version = 6; // the version of API responses the client expects
    private string $api_format  = 'xml'; // the version of API responses the client expects

    // Constructed variables
    private $_debug_callback = null;
    private $_debug_output   = false;
    private $api_auth;
    private $api_state = 'UNCONFIGURED';
    private $api_url;

    /**
     * Constructor
     *
     * If enough information is provided then we will attempt to connect right
     * away, otherwise we will simply return an object that can be reconfigured
     * and manually connected.
     */
    public function __construct($config = array())
    {
        // See if we are setting debug first
        if (isset($config['debug'])) {
            $this->_debug_output = $config['debug'];
        }

        if (isset($config['debug_callback'])) {
            $this->_debug_callback = $config['debug_callback'];
        }

        // If we got something, then configure!
        if (is_array($config) && count($config)) {
            $this->configure($config);
        }

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
    private function _debug($source, $message)
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
     */
    public function connect(): bool
    {
        // Set up the handshake
        $time       = time();
        $key        = $this->password; // New ampache versions save this password encrypted
        $passphrase = hash('sha256', $time . $key);

        $this->_debug('CONNECT', "Using " . $this->username . " / " . $passphrase);

        $options = array(
            'timestamp' => $time,
            'auth' => $this->password,
            'version' => $this->server_version,
            'user' => $this->username
        );

        $results = $this->send_command('handshake', $options);
        if (!$results || empty($results->auth)) {
            // try using unencrypted password from database
            $key        = hash('sha256', $this->password);
            $passphrase = hash('sha256', $time . $key);
            $options    = array(
                'timestamp' => $time,
                'auth' => $passphrase,
                'version' => $this->server_version,
                'user' => $this->username
            );
            $this->_debug('CONNECT', "Using " . $this->username . " / " . $passphrase);
            $results = $this->send_command('handshake', $options);
            if (!$results || empty($results->auth)) {
                $this->set_state('error');

                return false;
            }
        }

        $this->api_auth = $results->auth;
        $this->set_state('connected');
        // Define when we pulled this, it is not wine, it does not get better with age
        $this->handshake_time = time();
        $this->handshake      = $results;

        return true;
    }

    /**
     * configure
     *
     * This function takes an array of elements and configures the AmpacheApi
     * object. It doesn't really do anything fancy, but it's a separate function
     * so it can be called both from the constructor and directly.
     */
    public function configure($config = array()): bool
    {
        //$this->_debug('CONFIGURE', 'Checking passed config options');

        if (!is_array($config)) {
            trigger_error('AmpacheApi::configure received a non-array value');

            return false;
        }

        if (isset($config['username'])) {
            $this->username = $config['username'];
        }
        if (isset($config['password'])) {
            $this->password = $config['password'];
        }
        if (isset($config['server_version'])) {
            $this->server_version = (int)substr($config['server_version'], 0, 1);
        }
        if (isset($config['api_format']) && in_array($config['api_format'], array('xml', 'json'))) {
            $this->api_format = $config['api_format'];
        }

        // set the correct handshake version or fallback to 3 for invalid versions
        switch ($this->server_version) {
            case 3:
                $this->handshake_version = '390001';
                break;
            case 4:
                $this->handshake_version = '443000';
                break;
            case 5:
                $this->handshake_version = '5.5.6';
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

        if (isset($config['server'])) {
            // Replace any http:// in the URL with ''
            $config['server'] = str_replace($protocol, '', $config['server']);
            $this->server     = htmlentities($config['server'], ENT_QUOTES, 'UTF-8');
        }

        $this->api_url = $protocol . $this->server . '/server/' . $this->api_format . '.server.php';

        // See if we have enough to authenticate, if so change the state
        if (!empty($this->username) && !empty($this->server)) {
            $this->set_state('ready');
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
    public function set_state($state)
    {
        // Very simple for now, maybe we'll do something more with this later
        $this->api_state = strtoupper($state);
    }

    /**
     * state
     *
     * This returns the state of the API.
     */
    public function state()
    {
        return $this->api_state;
    }

    /**
     * info
     *
     * Returns the information gathered by the handshake.
     * Not raw so we can format it if we want?
     * @return string|SimpleXMLElement|null
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
     * @return string|SimpleXMLElement|null
     * @throws Exception
     */
    public function send_command(string $command, ?array $options = array())
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

        foreach ($options as $key => $value) {
            $key = trim($key);
            if (!$key) {
                // Nonfatal, don't need to throw an exception
                trigger_error('AmpacheApi::send_command unable to append empty variable to command');
                continue;
            }
            $url .= '&' . urlencode($key) . '=' . urlencode($value);
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

        switch ($this->api_format) {
            case 'json':
                $result = json_decode($data);
                if (!$result) {
                    return null;
                }

                return $result;
            case 'xml':
            default:
                $result = simplexml_load_string($data);
                if (!$result) {
                    return null;
                }

                return $result;
        }
    }

    /**
     * validate_command
     *
     * This takes the specified command and checks it against the known
     * commands for the current version of Ampache. If no version is known yet
     * it should return FALSE for everything except ping and handshake.
     */
    public function validate_command($command): bool
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

