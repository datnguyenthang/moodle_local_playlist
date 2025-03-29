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
require_login();

global $USER, $CFG, $DB, $OUTPUT, $PAGE;

$id = required_param('itemid', PARAM_INT);
$current_link = new \moodle_url('/local/playlist/detail.php', ['itemid' => $id]);

$PAGE->set_url($current_link);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'local_playlist'));
$PAGE->set_heading(get_string('pluginname', 'local_playlist'));



$item = $DB->get_record('local_playlist_item', ['id' => $id]);

if (empty($item)) {
	throw new \moodle_exception('listnoitem');
}

if (!check_eligible_access_item($item->learningspace_ids)) {
	throw new \moodle_exception('nopermissiontoviewpage');
}

local_playlist_set_views($id); // set view to this user
if (!$item) {
	throw new moodle_exception('Content not found');
}

$PAGE->requires->css('/local/playlist/css/playlist.css');
$PAGE->requires->js_call_amd('local_playlist/detail', 'init');
$PAGE->requires->js_call_amd('local_playlist/rating', 'init');
$PAGE->requires->js_call_amd('local_playlist/comment', 'init');
$PAGE->requires->js_call_amd('local_playlist/reaction', 'init');

// convert time to user time
$item->time_created = date("j M Y", $item->time_created);
$item->duration = local_playlist_convert_duration($item->duration_number, $item->duration_timeunit);

//get viewcount, comments, rating of item
$view = local_playlist_get_views($item->id);
$comments = local_playlist_get_comments($item->id);
$rating = local_playlist_calculate_rating($item->id);

//get picture of current user
$has_user_picture = !empty($creator->picture) && $USER->picture != 0; 
$user_picture = $has_user_picture ? 
				$OUTPUT->user_picture($USER, ['class' => 'userpicture defaultuserpic']) : 
				$OUTPUT->pix_icon('u/f2', 'User Icon', 'core', ['class' => 'userpicture defaultuserpic']);
$USER->picture = $user_picture;
$USER->fullname = fullname($USER);

//check is creator or not to show their picture
$creator = $DB->get_record('user', ['id' => $item->created_by]);
$has_creator_picture = !empty($creator->picture) && $creator->picture != 0; 
$creator_picture =  $has_creator_picture ? 
					$OUTPUT->user_picture($creator, ['class' => 'userpicture defaultuserpic']) : 
					$OUTPUT->pix_icon('u/f2', 'User Icon', 'core', ['class' => 'userpicture defaultuserpic']);
$creator->picture = $creator_picture;
$creator->fullname = fullname($creator);

$related_content = [];
////////////////////////////////////////////////////////
$is_page = $is_video = $is_link = $is_file = false;

if ($item->type == LOCAL_PLAYLIST_ITEM_TYPE_LINK) {
    $is_link = true;
} elseif ($item->type == LOCAL_PLAYLIST_ITEM_TYPE_VIDEO) {
    $is_video = true;
} elseif ($item->type == LOCAL_PLAYLIST_ITEM_TYPE_FILE) {
    $is_file = true;
} elseif ($item->type == LOCAL_PLAYLIST_ITEM_TYPE_PAGE) {
    $is_page = true;
}

$fs = get_file_storage();
if ($is_file) {
	$files = $fs->get_area_files(context_system::instance()->id,
		'local_playlist',
		'item_file',
		$item->id,
		'itemid',
		'',
		false);
	
	$item->file = '';
	$num_files = count($files);
	$item->file .= '<table class="table table-striped">';
	$item->file .= '<thead><tr><th>File Name</th></tr></thead>';
	$item->file .= '<tbody>';

	foreach ($files as $file) {
		$item->file .= '<tr><td><a href="'.local_playlist_get_url_for_file($file).'" target="_blank">'.
			$OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file)).' '.$file->get_filename().'</a></td></tr>';
	}

	$item->file .= '</tbody>';
	$item->file .= '</table>';
	$item->file .= '<p>Total number of files: '.$num_files.'</p>';

}

$video_url = '';
$is_external = $is_direct = false;
if ($is_video) {
    $PAGE->requires->js('/local/playlist/js/video.min.js', true);
    $PAGE->requires->css('/local/playlist/styles/video-js.css');
    $PAGE->requires->js_call_amd('local_playlist/video', 'init');
	$is_external = $is_direct = false;

    if (!empty($item->link)) {
        // Link to YouTube, Vimeo, or external source
        $video_url = $item->link;
		$is_external = true;
    } else {
        // Direct file upload
        $video_file = $fs->get_area_files(
            context_system::instance()->id,
            'local_playlist',
            'item_file',
            $item->id,
            'itemid',
            '',
            false
        );

        if (!empty($video_file)) {
			$is_direct = true;
            $video_url = reset($video_file)->get_url();
        }
    }
}

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_playlist/detail', [
	'user' => $USER,
	'creator' => $creator,
	'item' => $item,
	'itemid' => $id,
	'view' => $view, // view count
	'total_comment' => count($comments),
	'comments' => $comments, // comments
	'rating' => $rating, // rating
	'related_contents' => $related_content,
	'isCreator' => local_playlist_is_creator(),
	'is_page' => $is_page,
	'is_video' => $is_video,
	'is_file' => $is_file,
	'is_link' => $is_link,
	//////video//////
	'video_url' => get_youtube_embed_url($video_url),
	'is_external' => $is_external,
	'is_direct' => $is_direct,
	'current_link' => $current_link->out(),
]);

echo $OUTPUT->footer();
