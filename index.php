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

if (!defined('LOCAL_PLAYLIST_IS_ADMIN_MODE')) {
	define('LOCAL_PLAYLIST_IS_ADMIN_MODE', 0);
}

require __DIR__.'/inc.php';
require_once($CFG->dirroot.'/cohort/lib.php');

global $USER, $CFG, $DB, $OUTPUT, $PAGE;

require_login();

$PAGE->set_url('/local/playlist/index.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_playlist'));
$PAGE->set_heading(get_string('pluginname', 'local_playlist'));

$type = optional_param('type', '', PARAM_TEXT);

$PAGE->requires->jquery();
$PAGE->requires->js('/local/playlist/javascript/owl.carousel.min.js');
$PAGE->requires->css('/local/playlist/styles/owl.carousel.min.css');
$PAGE->requires->css('/local/playlist/styles/owl.theme.default.min.css');
$PAGE->requires->css('/local/playlist/css/playlist.css');

if (empty(get_accessable_learningspace())) {
	throw new \moodle_exception('nopermissiontoviewpage');
}

$default_learningspace = get_accessable_learningspace()[0]['id'];
$PAGE->requires->js_call_amd('local_playlist/homecontent', 'init', [$default_learningspace]);

if (local_playlist_is_creator()) $PAGE->requires->js_call_amd('local_playlist/mycontent', 'init');

echo $OUTPUT->header();

//echo $OUTPUT->heading(get_string('pluginname', 'local_playlist'));

function get_content($type = '') {
	global $DB, $USER;

	$where = '';
	$params = [];

	if ($type == 'review') {
		$where .= "AND (item.reviewer_id=? AND item.online<>".LOCAL_PLAYLIST_ITEM_STATE_NEW.")";
		$params[] = $USER->id;
	} else {
		$where .= "AND (item.created_by = ?)";
		$params[] = $USER->id;
	}

	$items = $DB->get_records_sql("
		SELECT item.*
		FROM {local_playlist_item} AS item
		WHERE 1=1
		$where
		".local_playlist_limit_item_to_category_where(local_playlist_course_settings::root_category_id())."

		ORDER BY GREATEST(time_created,time_modified) DESC
	", $params);

	return $items;
}

function get_categories() {
	global $DB;

	$mgr = new local_playlist_category_manager(true);

	$categories = $mgr->getChildren(0);

	$list_categories= [];
	foreach ($categories as $c) {
		$list_categories[] = [
			'id' => $c->id,
			'name' => $c->name
		];
	}

	return $list_categories;
}

echo $OUTPUT->render_from_template('local_playlist/index', [
	'content' => get_content(),
	//'categories' => get_categories(),
	'contentTypes' => get_content_types(),
	'learningSpaces' => get_accessable_learningspace(),
	'isCreator' => local_playlist_is_creator(), //LOCAL_PLAYLIST_IS_ADMIN_MODE
	'isSiteadmin' => is_siteadmin(),
	'isHomePage' => true,
]);

echo $OUTPUT->footer();
