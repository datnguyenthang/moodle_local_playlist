<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * tool supporter external services.
 *
 * @package    local_playlist
 * @copyright  2019 Benedikt Schneider, Klara Saary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'local_playlist_get_allcontents' => [
        'classname'   => 'local_playlist_external',
        'methodname'  => 'get_allcontents',
        'classpath'   => 'local/playlist/classes/externallib.php',
        'description' => 'Get all content in playlist',
        'type'        => 'read',
        'ajax'        => true,
    ],

    'local_playlist_get_mycontents' => [
        'classname'   => 'local_playlist_external',
        'methodname'  => 'get_mycontents',
        'classpath'   => 'local/playlist/classes/externallib.php',
        'description' => 'Get my content in playlist',
        'type'        => 'read',
        'ajax'        => true,
    ],

    'local_playlist_set_rating' => [
        'classname'   => 'local_playlist_external',
        'methodname'  => 'set_rating',
        'classpath'   => 'local/playlist/classes/externallib.php',
        'description' => 'Set rating of item',
        'type'        => 'write',
        'ajax'        => true,
    ],

    'local_playlist_set_comment' => [
        'classname'   => 'local_playlist_external',
        'methodname'  => 'set_comment',
        'classpath'   => 'local/playlist/classes/externallib.php',
        'description' => 'Set comment of item',
        'type'        => 'write',
        'ajax'        => true,
    ],

    'local_playlist_delete_comment' => [
        'classname'   => 'local_playlist_external',
        'methodname'  => 'delete_comment',
        'classpath'   => 'local/playlist/classes/externallib.php',
        'description' => 'Delete comment of item',
        'type'        => 'write',
        'ajax'        => true,
    ],

    'local_playlist_set_reaction' => [
        'classname'   => 'local_playlist_external',
        'methodname'  => 'set_reaction',
        'classpath'   => 'local/playlist/classes/externallib.php',
        'description' => 'Set reaction of item',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
