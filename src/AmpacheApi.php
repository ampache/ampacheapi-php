<?php
/* vim:set softtabstop=4 shiftwidth=4 expandtab: */
/**
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

declare(strict_types=0);

namespace AmpacheApi;

use Exception;

class AmpacheApi
{
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
        'browse',
        'catalog',
        'catalog_action',
        'catalog_add',
        'catalog_delete',
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
        'getart',
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
        'list',
        'live_stream',
        'live_stream_create',
        'live_stream_delete',
        'live_stream_edit',
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
        'register',
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

    // General Settings
    private $server;
    private $username;
    private $password;
    private $api_secure;
    private $api_version = 3;

    // Handshake variables
    private $handshake;
    private $handshake_time; // Used to figure out how stale our data is
    private $handshake_version = '390001';

    // Response variables
    private $api_session;
    private $raw_response;

    // Constructed variables
    private $api_url;
    private $api_state = 'UNCONFIGURED';
    private $api_auth;

    // XML Parser variables
    private $XML_currentTag;
    private $XML_subTag;
    private $XML_parser;
    private $XML_results;
    private $XML_position = 0;

    protected $XML_grabtags = array();
    protected $XML_skiptags = array(
        'root'
    );

    protected $XML_parenttags = array(
        'add',
        'album',
        'albums',
        'api',
        'artist',
        'artists',
        'auth',
        'catalogs',
        'clean',
        'playlist',
        'playlists',
        'result',
        'shouts',
        'song',
        'songs',
        'tag',
        'tags',
        'timeline',
        'update',
        'user',
        'users',
        'version',
        'video',
        'videos'
    );

    private $_debug_callback = null;
    private $_debug_output   = false;

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
    private function _debug($source, $message, $level = 5)
    {
        if ($this->_debug_output) {
            echo "$source :: $message\n";
        }

        if (!is_null($this->_debug_callback)) {
            call_user_func($this->_debug_callback, 'AmpacheApi', "$source :: $message", $level);
        }
    }

    /**
     * connect
     *
     * This attempts to connect to the Ampache instance.
     */
    public function connect(): bool
    {
        $this->_debug('CONNECT', "Using $this->username / $this->password");

        // Set up the handshake
        $results   = array();
        $timestamp = time();

        $key        = hash('sha256', $this->password);
        $passphrase = hash('sha256', $timestamp . $key);

        $options = array(
            'timestamp' => $timestamp,
            'auth' => $passphrase,
            'version' => $this->handshake_version,
            'user' => $this->username
        );

        $data = $this->send_command('handshake', $options);

        foreach ($data as $value) {
            $results = array_merge($results, $value);
        }

        if (!$results['auth']) {
            $this->set_state('error');

            return false;
        }
        $this->api_auth = $results['auth'];
        $this->set_state('connected');
        // Define when we pulled this, it is not wine, it does
        // not get better with age
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
        $this->_debug('CONFIGURE', 'Checking passed config options');

        if (!is_array($config)) {
            trigger_error('AmpacheApi::configure received a non-array value');

            return false;
        }

        // FIXME: Is the scrubbing of these variables actually sane?  I'm pretty sure password at least shouldn't be messed with like that.
        if (isset($config['username'])) {
            $this->username = htmlentities($config['username'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($config['password'])) {
            $this->password = htmlentities($config['password'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($config['api_version'])) {
            $this->api_version = (int)$config['api_version'];
            switch ($this->api_version) {
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
                    $this->handshake_version = '6.0.0';
            }
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

        $this->api_url = $protocol . $this->server . '/server/xml.server.php';

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
     */
    public function send_command($command, $options = array())
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

        $data               = file_get_contents($url);
        $this->raw_response = $data;
        $this->parse_response($data);

        return $this->get_response();
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
        switch ($this->api_version) {
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

    /**
     * parse_response
     *
     * This takes an XML document and dumps it into $this->results. Before
     * it does that it will clean up anything that was there before, so I hope
     * you didn't want any of that.
     */
    public function parse_response($response)
    {
        // Reset the results
        $this->XML_results  = array();
        $this->XML_position = 0;

        $this->XML_create_parser();

        if (!xml_parse($this->XML_parser, $response)) {
            $errorcode =  xml_get_error_code($this->XML_parser);
            throw new Exception('AmpacheApi::parse_response was unable to parse XML document. Error ' . $errorcode . ' line ' . xml_get_current_line_number($this->XML_parser) . ': ' . xml_error_string($errorcode));
        }

        xml_parser_free($this->XML_parser);
        $this->_debug('PARSE RESPONSE', json_encode($this->XML_results));
    }

    /**
     * get_response
     *
     * This returns the last data we parsed.
     */
    public function get_response()
    {
        return $this->XML_results;
    }


    ////////////////////////// XML PARSER FUNCTIONS ////////////////////////////

    /**
     * XML_create_parser
     * This creates the xml parser and sets the options
     */
    public function XML_create_parser()
    {
        $this->XML_parser = xml_parser_create();
        xml_parser_set_option($this->XML_parser, XML_OPTION_CASE_FOLDING, false);
        xml_set_object($this->XML_parser, $this);
        xml_set_element_handler($this->XML_parser, 'XML_start_element', 'XML_end_element');
        xml_set_character_data_handler($this->XML_parser, 'XML_cdata');
    } // XML_create_parser

    /**
     * XML_cdata
     * This is called for the content of the XML tag
     */
    public function XML_cdata($parser, $cdata): bool
    {
        $cdata = trim($cdata);

        if (!$this->XML_currentTag || !$cdata) {
            return false;
        }

        if ($this->XML_subTag) {
            $this->XML_results[$this->XML_position][$this->XML_currentTag][$this->XML_subTag] = $cdata;
        } else {
            $this->XML_results[$this->XML_position][$this->XML_currentTag] = $cdata;
        }

        return true;
    } // XML_cdata

    public function XML_start_element($parser, $tag, $attributes): bool
    {
        // Skip it!
        if (in_array($tag, $this->XML_skiptags)) {
            return false;
        }

        if (!in_array($tag, $this->XML_parenttags) || $this->XML_currentTag) {
            $this->XML_subTag = $tag;
        } else {
            $this->XML_currentTag = $tag;
        }

        if (count($attributes)) {
            if (!$this->XML_subTag) {
                $this->XML_results[$this->XML_position][$this->XML_currentTag]['self'] = $attributes;
            } else {
                if(isset($this->XML_results[$this->XML_position][$this->XML_currentTag][$this->XML_subTag]) && is_array($this->XML_results[$this->XML_position][$this->XML_currentTag][$this->XML_subTag])) {
                    $this->XML_results[$this->XML_position][$this->XML_currentTag][$this->XML_subTag]['self'] = $attributes;
                }
            }
        }

        return true;
    } // start_element

    public function XML_end_element($parser, $tag)
    {
        if ($tag != $this->XML_currentTag) {
            $this->XML_subTag = false;
        } else {
            $this->XML_currentTag = false;
            $this->XML_position++;
        }
    } // end_element
}
