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

require __DIR__.'/inc.php';
require_once($CFG->dirroot.'/cohort/lib.php');

global $USER, $CFG, $DB, $OUTPUT, $PAGE;

require_login();

$viewtype = optional_param('viewtype', '', PARAM_TEXT);

$PAGE->set_url('/local/playlist/view.php', ['viewtype' => $viewtype]);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_playlist'));
$PAGE->set_heading(get_string('pluginname', 'local_playlist'));

$PAGE->requires->css('/local/playlist/css/playlist.css');

if (empty(get_accessable_learningspace())) {
	throw new \moodle_exception('nopermissiontoviewpage');
}
if ($viewtype == 'all') {
	$PAGE->requires->js_call_amd('local_playlist/viewall', 'init');
} else {
	$PAGE->requires->js_call_amd('local_playlist/viewmine', 'init');
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_playlist/view', [
	'contentTypes' => get_content_types(),
	'learningSpaces' => get_accessable_learningspace(),
	'isCreator' => local_playlist_is_creator(), //LOCAL_PLAYLIST_IS_ADMIN_MODE
	'isSiteadmin' => is_siteadmin(),
	'isHomePage' => false,
]);

echo $OUTPUT->footer();
