<?php
// This file is part of Playlist Library
//
// (c) 2023 GTN - Global Training Network GmbH <office@gtn-solutions.com>
//
// Playlist Library is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This script is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You can find the GNU General Public License at <http://www.gnu.org/licenses/>.
//
// This copyright notice MUST APPEAR in all copies of the script!

function repository_playlist_dir($file) {
    $sub_path = str_replace(__DIR__, '', $file);

    $playlist_path = dirname(dirname(__DIR__)).'/local/playlist';
    $file_path = $playlist_path.'/lib/repository_plugin'.$sub_path;

    if (!is_dir($playlist_path)) {
        die('Playlist Library not installed');
    }
    if (!is_file($file_path)) {
        die('Repository Plugin and Playlist Library not compatible?!?');
    }

    return $file_path;
}
