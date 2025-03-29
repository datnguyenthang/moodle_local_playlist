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
require_once($CFG->libdir.'/filelib.php');
use \local_playlist\globals as g;
require_login();
global $PAGE, $USER, $OUTPUT;

$type = optional_param('type', '', PARAM_TEXT);
$id = optional_param('id', 0, PARAM_INT);
$show = optional_param('show', '', PARAM_TEXT);
//$addtype = optional_param('addtype', '', PARAM_TEXT);

$url = new moodle_url('/local/playlist/admin.php', ['id' => $id, 'type' => $type, 'show' => $show]);
$currentUrl = $url->out( false );

$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');

$fileoptions = array('subdirs' => false, 'maxfiles' => 1, 'accepted_types' => 'image');
$textfieldoptions = array('trusttext' => true, 'subdirs' => true, 'maxfiles' => 99, 'context' => context_system::instance());

// Define $item before using it
if ($show == 'add') {
    $id = 0;
    $item = new StdClass;
    $item->online = 1;

    // local_playlist_require_creator();
} else {
    $item = g::$DB->get_record('local_playlist_item', array('id' => $id));//var_dump($item);exit;

    local_playlist_require_can_edit_item($item);

    if ($item->online_to > 10000000000) {
        $item->online_to = 0;
    }

    $item->contentformat = FORMAT_HTML;
    $item = file_prepare_standard_editor($item, 'content', $textfieldoptions, context_system::instance(),
        'local_playlist', 'item_content', $item->id);
    $item->abstractformat = FORMAT_HTML;
    $item = file_prepare_standard_editor($item, 'abstract', $textfieldoptions, context_system::instance(),
        'local_playlist', 'item_abstract', $item->id);
    $item = file_prepare_standard_filemanager($item, 'file', $fileoptions, context_system::instance(),
        'local_playlist', 'item_file', $item->id);
    $item = file_prepare_standard_filemanager($item, 'preview_image', $fileoptions, context_system::instance(),
        'local_playlist', 'preview_image', $item->id);
}

$itemcategories = g::$DB->get_records_sql_menu("SELECT category.id, category.id AS val
FROM {local_playlist_category} category
LEFT JOIN {local_playlist_item_category} ic ON category.id=ic.category_id
WHERE ic.item_id=?", array($id));

$categoryid = optional_param('category_id', '', PARAM_INT);
if (!$itemcategories && $categoryid) {
    // at least one category
    $itemcategories[$categoryid] = $categoryid;
}

$header = '';
$itemType = 0;

switch ($type) {
    case 'link':
        require_once($CFG->dirroot.'/local/playlist/forms/link_edit_form.php');
        $header = get_string('editlink', 'local_playlist');
        $itemType = LOCAL_PLAYLIST_ITEM_TYPE_LINK;
        $itemeditform = new link_edit_form($currentUrl, [
            'itemCategories' => $itemcategories,
            'fileoptions' => $fileoptions,
            'type' => $type,
        ]);
        break;
    case 'video':
        require_once($CFG->dirroot.'/local/playlist/forms/video_edit_form.php');
        $header = get_string('editvideo', 'local_playlist');
        $itemType = LOCAL_PLAYLIST_ITEM_TYPE_VIDEO;
        $itemeditform = new video_edit_form($currentUrl, [
            'itemCategories' => $itemcategories,
            'fileoptions' => $fileoptions,
            'type' => $type,
        ]);
        break;
    case 'file':
        require_once($CFG->dirroot.'/local/playlist/forms/file_edit_form.php');
        $header = get_string('editfile', 'local_playlist');
        $itemType = LOCAL_PLAYLIST_ITEM_TYPE_FILE;
        $itemeditform = new file_edit_form($currentUrl, [
            'itemCategories' => $itemcategories,
            'fileoptions' => $fileoptions,
            'type' => $type,
        ]);
        break;
    default:
        require_once($CFG->dirroot.'/local/playlist/forms/page_edit_form.php');
        $header = get_string('editpage', 'local_playlist');
        $itemType = LOCAL_PLAYLIST_ITEM_TYPE_PAGE;
        $itemeditform = new page_edit_form($currentUrl, [
            'itemCategories' => $itemcategories,
            'fileoptions' => $fileoptions,
            'type' => $type,
        ]);
        break;
}

if ($itemeditform->is_cancelled()) {
    if ($back = optional_param('back', '', PARAM_LOCALURL)) {
        redirect(new moodle_url($back));
    } else {
        redirect(new moodle_url('admin.php', ['courseid' => g::$COURSE->id]));
    }
} else if ($fromform = $itemeditform->get_data()) {
    // Edit/add.
    if ($type == 'mine' && empty($item->id)) {
        // normal user items should be offline first
        $fromform->online = LOCAL_PLAYLIST_ITEM_STATE_NEW;
    }

    $fromform->type = $itemType;

    if (!empty($fromform->id)) {
        $fromform->id = $fromform->id;
        $fromform->modified_by = $USER->id;
        $fromform->time_modified = time();
    } else {
        $fromform->created_by = $USER->id;
        $fromform->time_created = time();
        $fromform->time_modified = 0;
        $fromform->id = g::$DB->insert_record('local_playlist_item', $fromform);
    }

    $fromform->contentformat = FORMAT_HTML;
    $fromform = file_postupdate_standard_editor($fromform,
        'content',
        $textfieldoptions,
        context_system::instance(),
        'local_playlist',
        'item_content',
        $fromform->id);
    $fromform->abstractformat = FORMAT_HTML;
    $fromform = file_postupdate_standard_editor($fromform,
        'abstract',
        $textfieldoptions,
        context_system::instance(),
        'local_playlist',
        'item_content',
        $fromform->id);

    g::$DB->update_record('local_playlist_item', $fromform);

    core_tag_tag::set_item_title(
        'local_playlist',
        'local_playlist_item',
        $fromform->id,
        context_system::instance()->id,
        $fromform->title
    );

    // Save file.
    $fromform = file_postupdate_standard_filemanager($fromform,
        'file',
        $fileoptions,
        context_system::instance(),
        'local_playlist',
        'item_file',
        $fromform->id);
    $fromform = file_postupdate_standard_filemanager($fromform,
        'preview_image',
        $fileoptions,
        context_system::instance(),
        'local_playlist',
        'preview_image',
        $fromform->id);

    // Save categories.
    //g::$DB->delete_records('local_playlist_item_category', array("item_id" => $fromform->id));
    $categories_request = local_playlist\param::optional_array('categories', PARAM_INT);

    if ($root_category_id = local_playlist_course_settings::root_category_id()) {
        // if course has a root category, always add it
        if (!in_array($root_category_id, $categories_request)) {
            $categories_request[$root_category_id] = $root_category_id;
        }
    }

    foreach ($categories_request as $categoryidforinsert) {
        g::$DB->execute('INSERT INTO {local_playlist_item_category} (item_id, category_id) VALUES (?, ?)',
            array($fromform->id, $categoryidforinsert));
    }

    if ($back = optional_param('back', '', PARAM_LOCALURL)) {
        redirect(new moodle_url($back));
    } elseif ($type == 'mine') {
        //redirect(new moodle_url('mine.php', ['courseid' => g::$COURSE->id]));
        redirect($CFG->wwwroot . '/local/playlist/');
    } else {
        //redirect(new moodle_url('admin.php', ['courseid' => g::$COURSE->id]));
        redirect($CFG->wwwroot . '/local/playlist/');
    }
    exit;

} else {
    // Display form.
    echo $OUTPUT->header();

    echo $OUTPUT->heading($header);
    
    $itemeditform->set_data($item);
    $itemeditform->display();

    echo $OUTPUT->footer();
}