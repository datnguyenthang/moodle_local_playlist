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

const LOCAL_PLAYLIST_CATEGORY_TAGS = 1;

const LOCAL_PLAYLIST_CAP_USE = 'use';
const LOCAL_PLAYLIST_CAP_MANAGE_REVIEWERS = 'manage_reviewers';
const LOCAL_PLAYLIST_CAP_MANAGE_CONTENT = 'manage_content';
const LOCAL_PLAYLIST_CAP_MANAGE_CATS = 'manage_cats';
const LOCAL_PLAYLIST_CAP_COURSE_SETTINGS = 'course_settings';

const LOCAL_PLAYLIST_ITEM_STATE_NEW = -2;
const LOCAL_PLAYLIST_ITEM_STATE_IN_REVIEW = -1;

const LOCAL_PLAYLIST_ITEM_TYPE_COURSE = 0;
const LOCAL_PLAYLIST_ITEM_TYPE_LINK = 1;
const LOCAL_PLAYLIST_ITEM_TYPE_VIDEO = 2;
const LOCAL_PLAYLIST_ITEM_TYPE_FILE = 3;
const LOCAL_PLAYLIST_ITEM_TYPE_PAGE = 4;


function local_playlist_get_string($identifier, $component = null, $a = null) {
	$manager = get_string_manager();

	if ($component === null)
		$component = 'local_playlist';

	if (local_playlist_course_settings::alternative_wording() && $manager->string_exists('alt_'.$identifier, $component))
		return $manager->get_string('alt_'.$identifier, $component, $a);

	if ($manager->string_exists($identifier, $component))
		return $manager->get_string($identifier, $component, $a);

	return $manager->get_string($identifier, '', $a);
}
