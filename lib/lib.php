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

require __DIR__.'/config.php';
require __DIR__.'/common.php';

use \local_playlist\globals as g;

/**
 * local playlist new moodle url
 * @return moodle_url
 */
function local_playlist_new_moodle_url() {
	global $CFG;

	$moodlepath = preg_replace('!^[^/]+//[^/]+!', '', $CFG->wwwroot);

	return new moodle_url(str_replace($moodlepath, '', $_SERVER['REQUEST_URI']));
}

function local_playlist_is_reviewer() {
	return (bool)get_user_preferences('local_playlist_is_reviewer');
}

/**
 * is creator?
 * @return boolean
 */
function local_playlist_is_creator() {
	global $DB, $USER;
    $learningspaces = $DB->get_records('local_learningspace', ['published' => 1]);

    foreach ($learningspaces as $key => $learningspace) {
        $arr_owner_ids = explode(',', $learningspace->owner_ids);
        if (in_array($USER->id, $arr_owner_ids)) {
            return true;
        }
    }
	return local_playlist_is_admin() || has_capability('local/playlist:creator', context_system::instance());
}

/**
 * is admin?
 * @return boolean
 */
function local_playlist_is_admin() {
	return has_capability('local/playlist:admin', context_system::instance());
}

function local_playlist_require_cap($cap, $user = null) {
	// all capabilities require use
	if (!has_capability('local/playlist:use', context_system::instance(), $user)) {
		if (!g::$USER->id) {
			// not logged in and no guest
			// -> forward to login form
			require_login();
		} else {
			throw new require_login_exception(local_playlist_get_string('notallowed'));
		}
	}

	switch ($cap) {
		case LOCAL_PLAYLIST_CAP_USE:
			// already checked
			return;
		case LOCAL_PLAYLIST_CAP_MANAGE_CONTENT:
		case LOCAL_PLAYLIST_CAP_MANAGE_CATS:
			if (!local_playlist_is_creator()) {
				throw new local_playlist_permission_exception('no creator');
			}

			return;
		case LOCAL_PLAYLIST_CAP_MANAGE_REVIEWERS:
		case LOCAL_PLAYLIST_CAP_COURSE_SETTINGS:
			if (!local_playlist_is_admin()) {
				throw new local_playlist_permission_exception('no admin');
			}

			return;
	}

	require_capability('local/playlist:'.$cap, context_system::instance(), $user);
}

function local_playlist_has_cap($cap, $user = null) {
	try {
		local_playlist_require_cap($cap, $user);

		return true;
	} catch (local_playlist_permission_exception $e) {
		return false;
	} catch (\require_login_exception $e) {
		return false;
	} catch (\required_capability_exception $e) {
		return false;
	}
}

/**
 * local playlist require open
 * @return nothing
 */
function local_playlist_require_view_item($item_or_id) {
	local_playlist_require_cap(LOCAL_PLAYLIST_CAP_USE);

	if (is_object($item_or_id)) {
		$item = $item_or_id;
	} else {
		$item = g::$DB->get_record('local_playlist_item', array('id' => $item_or_id));
	}

	if (!$item) {
		throw new moodle_exception('item not found');
	}

	if ($item->created_by == g::$USER->id || $item->reviewer_id == g::$USER->id) {
		// creator and reviewer can view it
		return true;
	}

	if ($item->online > 0) {
		// all online items can be viewed
		return true;
	}

	if (local_playlist_has_cap(LOCAL_PLAYLIST_CAP_MANAGE_CONTENT)) {
		// admin can view
		return true;
	}

	throw new local_playlist_permission_exception('not allowed');
}

class local_playlist_permission_exception extends local_playlist\moodle_exception {
}

/**
 * local playlist require can edit item
 * @param stdClass $item
 */
function local_playlist_require_can_edit_item(stdClass $item) {
	if (local_playlist_has_cap(LOCAL_PLAYLIST_CAP_MANAGE_CONTENT)) {
		return true;
	}

	if (local_playlist_is_reviewer() && $item->reviewer_id == g::$USER->id && $item->online != LOCAL_PLAYLIST_ITEM_STATE_NEW) {
		return true;
	}

	// Item creator can edit when not freigegeben
	if ($item->created_by == g::$USER->id && $item->online == LOCAL_PLAYLIST_ITEM_STATE_NEW) {
		return true;
	}

	throw new local_playlist_permission_exception(local_playlist_get_string('noedit'));
}

/**
 * can edit item ?
 * @param stdClass $item
 * @return boolean
 */
function local_playlist_can_edit_item(stdClass $item) {
	try {
		local_playlist_require_can_edit_item($item);

		return true;
	} catch (local_playlist_permission_exception $e) {
		return false;
	}
}


/**
 * wrote own function, so eclipse knows which type the output renderer is
 * @return \local_playlist_renderer
 */
function local_playlist_get_renderer($init = true) {
	if ($init) {
		local_playlist_init_page();
	}

	static $renderer = null;
	if ($renderer) {
		return $renderer;
	}

	return $renderer = g::$PAGE->get_renderer('local_playlist');
}

function local_playlist_init_page() {
	static $init = true;
	if (!$init) {
		return;
	}
	$init = false;

	require_login(optional_param('courseid', g::$SITE->id, PARAM_INT));
	// g::$PAGE->set_course(g::$SITE);

	if (!g::$PAGE->has_set_url()) {
		g::$PAGE->set_url(local_playlist_new_moodle_url());
	}
}

function local_playlist_get_url_for_file(stored_file $file) {
	return moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
		$file->get_itemid(), $file->get_filepath(), $file->get_filename());
}

/**
 * print jwplayer
 * @param array $options
 * @return nothing
 */
function local_playlist_print_jwplayer($options) {
    $options = array_merge(array(
		// 'primary' => "flash",
		'autostart' => false,
		// 'image' => 'https://www.e-cco-ibd.eu/pluginfile.php/145/local_html/content/MASTER_ECCO_logo_rechts_26_08_2010%20jpg.jpg'
	), $options);

    if (isset($options['file']) && preg_match('!^rtmp://.*cco-ibd.*:(.*)$!i', $options['file'], $matches)) {
        // add hls stream

        $rtmp = $options['file'];
        unset($options['file']);
        $options['playlist'] = array(
            array(
                'sources' => array(
                    array('file' => 'http://video.ecco-ibd.eu/'.$matches[1]),
                    array('file' => 'http://video.ecco-ibd.eu:1935/vod/mp4:'.$matches[1].'/playlist.m3u8'),
                    array('file' => $rtmp),
                    // array('file' => 'http://video.ecco-ibd.eu:1935/vod/mp4:'.str_replace('.mp4', '.m4v', $matches[1]).'/playlist.m3u8'),
                    // array('file' => 'http://video.ecco-ibd.eu:1935/vod/mp4:'.strtolower('ECCO2014_SP_S7_ELouis').'.m4v/playlist.m3u8'),
                    // array('file' => 'http://video.ecco-ibd.eu:1935/vod/mp4:ecco2012_7.m4v/playlist.m3u8'),
                )
            )
        );
    }

    if (strpos($_SERVER['HTTP_HOST'], 'ecco-ibd')) {
    	$player = '//content.jwplatform.com/libraries/xKafWURJ.js';
	} else {
		$player = 'jwplayer/jwplayer.js';
		$options['flashplayer'] = "jwplayer/player.swf";
	}
    //

	?>
    <script type="application/javascript" src="<?=$player?>"></script>
	<div class="video-container" id='player_2834'></div>
	<script type='text/javascript'>

		// allow fullscreen in iframes, you have to add allowFullScreen to the iframe
        if (window.frameElement) {
            window.frameElement.setAttribute('allowFullScreen', 'allowFullScreen');
        }

		var options = <?php echo json_encode($options); ?>;
		if (options.width == 'auto') options.width = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
		if (options.height == 'auto') options.height = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight;

        var p;
        var onPlay = function(){};
        var pauseVideo = false;
		if (!options.autostart) {
            // start and just load first frame
			options.autostart = true;
			options.mute = true;
            pauseVideo = true; // we want to pause it when loading

            onPlay = function(){
                if (pauseVideo) {
                    this.setMute(false);
                    this.pause();
                }
                window.setTimeout(function(){
                    // onplay fires twice?!?
                    // use setTimeout to overcome that
                    pauseVideo = false;
                }, 500);
			};
		}

        p = jwplayer('player_2834').setup(options);
        p.on('displayClick', function(){
            // user clicked the video -> don't pause video again
            pauseVideo = false;
        });
        p.on('play', onPlay);
		p.on('error', function(message){
			// $('#player_2834').replace('x');
			// confirm('Sorry, this file could not be played')console.log('ecco', message);
		});
	</script>
	<?php
}

/**
 * playlist category manager
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  gtn gmbh <office@gtn-solutions.com>
 */
class local_playlist_category_manager {
	/**
	 * @var $categories - categories
	 */
	private $categories = null;
	/**
	 * @var $categoriesbyparent - categories by parent
	 */
	private $categoriesbyparent = null;

	function __construct($showOfflineToo, $limitToCategoryId = null) {
		if ($this->categories !== null) {
			// Already loaded.
			return;
		}

		$this->createdefaultcategories();

		/*
		$fields = [];
		$join = [];
		$where = [];
		$params = [];
		*/

		$this->categories = g::$DB->get_records_sql("
        	SELECT category.*
        	FROM {local_playlist_category} category
        	WHERE 1=1
        	".($showOfflineToo ? '' : "
	            AND category.online > 0
			")."
			ORDER BY name
		");

		// sort naturally (for numbers)
		uasort($this->categories, function($a, $b) {
			return strnatcmp($a->name, $b->name);
		});

		$this->categoriesbyparent = array();

		$item_category_ids = iterator_to_array(g::$DB->get_recordset_sql("
        	SELECT item.id AS item_id, ic.category_id
        	FROM {local_playlist_item} item
        	JOIN {local_playlist_item_category} ic ON item.id=ic.item_id
        	WHERE 1=1
        	".($showOfflineToo ? '' : "
    	        AND item.online > 0
				AND (item.online_from=0 OR item.online_from IS NULL OR item.online_from <= ".time().")
				AND (item.online_to=0 OR item.online_to IS NULL OR item.online_to >= ".time().")
			")."
			".local_playlist_limit_item_to_category_where($limitToCategoryId)."
		"), false);

		// init
		foreach ($this->categories as $cat) {
			$cat->self_inc_all_sub_ids = [$cat->id => $cat->id];
			$cat->cnt_inc_subs = [];
			$cat->item_ids = [];
			$cat->item_ids_inc_subs = [];
			$cat->cnt = 0;
			$cat->level = 0;
		}

		// add items for counting
		foreach ($item_category_ids as $item_category) {
			if (!isset($this->categories[$item_category->category_id])) {
				continue;
			}

			$this->categories[$item_category->category_id]->item_ids[$item_category->item_id] = $item_category->item_id;
			$this->categories[$item_category->category_id]->item_ids_inc_subs[$item_category->item_id] = $item_category->item_id;
		}

		foreach ($this->categories as $cat) {

			$this->categoriesbyparent[$cat->parent_id][$cat->id] = $cat;
			$catLeaf = $cat;

			// find parents
			while ($cat->parent_id && isset($this->categories[$cat->parent_id])) {
				// has parent
				$parentCat = $this->categories[$cat->parent_id];
				$catLeaf->level++;
				$parentCat->self_inc_all_sub_ids += $cat->self_inc_all_sub_ids;
				$parentCat->item_ids_inc_subs += $cat->item_ids_inc_subs;

				$cat = $parentCat;
			}
		}

		if ($limitToCategoryId) {
			$this->categoriesbyparent[0] = $this->categoriesbyparent[$limitToCategoryId];
		}

		// count unique ids
		foreach ($this->categories as $cat) {
			$cat->cnt_inc_subs = count($cat->item_ids_inc_subs);
		}
	}

	/**
	 * get category
	 * @param integer $categoryid
	 * @return category
	 */
	public function getcategory($categoryid) {
		return isset($this->categories[$categoryid]) ? $this->categories[$categoryid] : null;
	}

	public function getChildren($categoryid) {
		return @$this->categoriesbyparent[$categoryid];
	}

	/**
	 * get category parent id
	 * @param integer $categoryid
	 * @return array of category
	 */
	public function getcategoryparentids($categoryid) {
		$parents = array();
		for ($i = 0; $i < 100; $i++) {
			$c = $this->getcategory($categoryid);
			if ($c) {
				$parents[] = $c->id;
				$categoryid = $c->parent_id;
			} else {
				break;
			}
		}

		return $parents;
	}

	/**
	 * walk tree
	 * @param \Closure $functionbefore
	 * @param \Closure $functionafter
	 * @return string item
	 */
	public function walktree($functionbefore, $functionafter = null) {
		return $this->walktreeitem($functionbefore, $functionafter);
	}

	/**
	 * walk tree item
	 * @param \Closure $functionbefore
	 * @param \Closure $functionafter
	 * @param integer $level
	 * @param integer $parent
	 * @return output
	 */
	private function walktreeitem($functionbefore, $functionafter, $level = 0, $parent = 0) {
		if (empty($this->categoriesbyparent[$parent])) {
			return;
		}

		$output = '';
		foreach ($this->categoriesbyparent[$parent] as $cat) {
			if ($functionbefore) {
				$output .= $functionbefore($cat);
			}

			$suboutput = $this->walktreeitem($functionbefore, $functionafter, $level + 1, $cat->id);

			if ($functionafter) {
				$output .= $functionafter($cat, $suboutput);
			}
		}

		return $output;
	}

	/**
	 * create default categories
	 * @return nothing
	 */
	public function createdefaultcategories() {
		global $DB;

		if ($DB->get_records('local_playlist_category', null, '', 'id', 0, 1)) {
			return;
		}

		$DB->execute("INSERT INTO {local_playlist_category} (id, parent_id, name, online) VALUES
 			(".LOCAL_PLAYLIST_CATEGORY_TAGS.", 0, 'Tags', 1)");
		/*
		$DB->execute("INSERT INTO {local_playlist_category} (id, parent_id, name, online) VALUES
			(".LOCAL_PLAYLIST_CATEGORY_SCHULSTUFE.", 0, 'Schulstufe', 1)");
		$DB->execute("INSERT INTO {local_playlist_category} (id, parent_id, name, online) VALUES
			(".LOCAL_PLAYLIST_CATEGORY_SCHULFORM.", 0, 'Schulform', 1)");
		*/

		$DB->execute("ALTER TABLE {local_playlist_category} AUTO_INCREMENT=1001");
	}
}

function local_playlist_get_reviewers() {
	return g::$DB->get_records_sql("
		SELECT u.*
		FROM {user} u
		JOIN {user_preferences} p ON u.id=p.userid AND p.name='local_playlist_is_reviewer'
		WHERE p.value
		ORDER BY lastname, firstname
	");
}

function local_playlist_handle_item_delete($type) {
	$id = required_param('id', PARAM_INT);
	require_sesskey();

	$item = g::$DB->get_record('local_playlist_item', array('id' => $id));
	local_playlist_require_can_edit_item($item);

	g::$DB->delete_records('local_playlist_item', array('id' => $id));
	g::$DB->delete_records('local_playlist_item_category', array("item_id" => $id));

	if ($back = optional_param('back', '', PARAM_LOCALURL)) {
		redirect(new moodle_url($back));
	} elseif ($type == 'mine') {
		redirect(new moodle_url('mine.php', ['courseid' => g::$COURSE->id]));
	} else {
		redirect(new moodle_url('admin.php', ['courseid' => g::$COURSE->id]));
	}

	exit;
}

function local_playlist_handle_item_edit($type, $show) {
	global $CFG, $USER;

	if ($show == 'delete') {
		local_playlist_handle_item_delete($type);
	}

	if ($show == 'change_state') {
		$id = required_param('id', PARAM_INT);
		$state = required_param('state', PARAM_INT);
		require_sesskey();

		$item = g::$DB->get_record('local_playlist_item', array('id' => $id));
		local_playlist_require_can_edit_item($item);

		/*
		if ($item->created_by == g::$USER->id && $item->online == LOCAL_PLAYLIST_ITEM_STATE_NEW && $state == LOCAL_PLAYLIST_ITEM_STATE_IN_REVIEW) {
			// ok
		} elseif ($item->online == 0 || $item->online == LOCAL_PLAYLIST_ITEM_STATE_IN_REVIEW && $state == LOCAL_PLAYLIST_ITEM_STATE_NEW) {
			// ok
		} else {
			throw new moodle_exception('not allowed');
		}
		*/

		// send email to reviewer
		if ($state == LOCAL_PLAYLIST_ITEM_STATE_IN_REVIEW) {
			$reviewer = g::$DB->get_record('user', ['id' => $item->reviewer_id]);
			$creator = g::$USER;

			if ($reviewer) {
				$message = local_playlist_trans('de:'.join('<br />', [
						'Liebe/r '.fullname($reviewer).',',
						'',
						'Im Fallarchiv der PH-OÖ wurde von '.fullname($creator).' ('.$creator->email.') ein Fall eingetragen.',
						''.fullname($creator).' bittet Sie den Fall zu Reviewen. Bitte sehen sie den Fall durch und',
						'- geben Sie den Fall gegebenfalls frei',
						'- oder verbessern Sie den Fall',
						'- oder geben Sie den Fall zurück an den Autor zur erneuten Bearbeitung',
						'',
						'<a href="'.g::$CFG->wwwroot.'/local/playlist/detail.php?itemid='.$item->id.'&type=mine">Klicken Sie hier um den Fall zu reviewen.</a>',
						'',
						'Vielen Dank',
						'',
						'Das ist eine automatisch generierte E-Mail, bitte nicht Antworten.',
					]));

				$eventdata = new \core\message\message();
				$eventdata->component = 'local_playlist'; // Your plugin's name
				$eventdata->name = 'item_status_changed';
				$eventdata->component = 'local_playlist';
				$eventdata->userfrom = $creator;
				$eventdata->userto = $reviewer;
				$eventdata->subject = local_playlist_trans('de:PH - Kasuistik Reviewanfrage');
				$eventdata->fullmessage = $message;
				$eventdata->fullmessageformat = FORMAT_HTML;
				$eventdata->fullmessagehtml = $message;
				$eventdata->smallmessage = '';
				$eventdata->notification = 1;
				@message_send($eventdata);
			}
		}

		// send email to creator
		if ($state == LOCAL_playlist_ITEM_STATE_NEW) {
			$reviewer = g::$USER;
			$creator = g::$DB->get_record('user', ['id' => $item->created_by]);

			if ($creator) {
				$message = local_playlist_trans('de:'.join('<br />', [
						'Liebe/r '.fullname($creator).',',
						'',
						'Im Fallarchiv der PH-OÖ wurde Ihnen ein Fall zur Überarbeitung übergeben. Bitte überarbeiten Sie den Fall und geben in erneut zum Review frei.',
						'',
						'<a href="'.g::$CFG->wwwroot.'/local/playlist/detail.php?itemid='.$item->id.'&type=mine">Klicken Sie hier um den Fall zu überarbeiten.</a>',
						'',
						'Vielen Dank',
						'',
						'Das ist eine automatisch generierte E-Mail, bitte nicht Antworten.',
					]));

				$eventdata = new \core\message\message();
				$eventdata->name = 'item_status_changed';
				$eventdata->component = 'local_playlist';
				$eventdata->userfrom = $reviewer;
				$eventdata->userto = $creator;
				$eventdata->subject = local_playlist_trans('de:PH - Kasuistik Reviewfeedback');
				$eventdata->fullmessageformat = FORMAT_HTML;
				$eventdata->fullmessagehtml = $message;
				$eventdata->smallmessage = '';
				$eventdata->notification = 1;
				message_send($eventdata);
			}
		}

		g::$DB->update_record('local_playlist_item', [
			'id' => $item->id,
			'online' => $state,
		]);

		if ($type == 'mine') {
			redirect(new moodle_url('mine.php', ['courseid' => g::$COURSE->id]));
		} else {
			redirect(new moodle_url('admin.php', ['courseid' => g::$COURSE->id]));
		}

		exit;
	}

	require_once($CFG->libdir.'/formslib.php');

	$categoryid = optional_param('category_id', '', PARAM_INT);
	$textfieldoptions = array('trusttext' => true, 'subdirs' => true, 'maxfiles' => 99, 'context' => context_system::instance());
	$fileoptions = array('subdirs' => false, 'maxfiles' => 5);

	if ($show == 'add') {
		$id = 0;
		$item = new StdClass;
		$item->online = 1;

		// local_playlist_require_creator();
	} else {
		$id = required_param('id', PARAM_INT);
		$item = g::$DB->get_record('local_playlist_item', array('id' => $id));

		local_playlist_require_can_edit_item($item);

		if ($item->online_to > 10000000000) {
			// bei den lateinern ist ein fiktiv hohes online_to drinnen
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

	/**
	 * Items edit form
	 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
	 * @copyright  gtn gmbh <office@gtn-solutions.com>
	 */
	class item_edit_form extends moodleform {

		/**
		 * Definition
		 * @return nothing
		 */
		public function definition() {
			$mform =& $this->_form;

			$mform->addElement('text', 'name', local_playlist_get_string('name'), 'size="100"');
			$mform->setType('name', PARAM_TEXT);
			$mform->addRule('name', 'Name required', 'required', null, 'server');

			if (local_playlist_course_settings::use_review()) {
				$values = array_map('fullname', local_playlist_get_reviewers());
				$values = ['' => ''] + $values;
				$mform->addElement('select', 'reviewer_id', local_playlist_trans(['de:Reviewer', 'en:Reviewer']), $values);
				$mform->addRule('reviewer_id', get_string('requiredelement', 'form'), 'required');

				$values = [
					'' => '',
					'real' => 'real',
					'fiktiv' => 'fiktiv',
				];
				$mform->addElement('select', 'real_fiktiv', local_playlist_trans('de:Typ'), $values);
			}

			if (!local_playlist_course_settings::alternative_wording()) {
				$mform->addElement('text', 'source', local_playlist_get_string('source'), 'size="100"');
				$mform->setType('source', PARAM_TEXT);
			}

			/*
			$values = g::$DB->get_records_sql_menu("
				SELECT c.id, c.name
				FROM {local_playlist_category} c
				WHERE parent_id=".LOCAL_PLAYLIST_CATEGORY_SCHULSTUFE."
			   ");
			$mform->addElement('select', 'schulstufeid', local_playlist_trans('de:Schulstufe'), $values);
			$mform->addRule('schulstufeid', get_string('requiredelement', 'form'), 'required');

			$values = g::$DB->get_records_sql_menu("
				SELECT c.id, c.name
				FROM {local_playlist_category} c
				WHERE parent_id=".LOCAL_PLAYLIST_CATEGORY_SCHULFORM."
			   ");
			$mform->addElement('select', 'schulformid', local_playlist_trans('de:Schulform'), $values);
			$mform->addRule('schulformid', get_string('requiredelement', 'form'), 'required');
			*/

			$mform->addElement('text', 'authors', local_playlist_get_string('authors'), 'size="100"');
			$mform->setType('authors', PARAM_TEXT);

			$to_year = date('Y') + 1;

			$values = range(2010,$to_year);
			$values = ['' => ''] + array_combine($values, $values);
			$mform->addElement('select', 'year', local_playlist_get_string('year', 'form'), $values);
			$mform->setType('year', PARAM_INT);

			$mform->addElement('editor', 'abstract_editor', local_playlist_get_string('abstract'), 'rows="10" cols="50" style="width: 95%"');
			$mform->setType('abstract', PARAM_RAW);

			$mform->addElement('header', 'contentheader', local_playlist_get_string('content'));
			$mform->setExpanded('contentheader');

			$mform->addElement('text', 'link', local_playlist_get_string('link'), 'size="100"');
			$mform->setType('link', PARAM_TEXT);

			$mform->addElement('editor', 'content_editor', local_playlist_get_string('content'), 'rows="20" cols="50" style="width: 95%"');
			$mform->setType('content', PARAM_RAW);

			$mform->addElement('filemanager', 'file_filemanager', local_playlist_get_string('files'), null, $this->_customdata['fileoptions']);

			$mform->addElement('filemanager', 'preview_image_filemanager', local_playlist_get_string('previmg'), null,
				$this->_customdata['fileoptions']);

			$mform->addElement('header', 'categoriesheader', local_playlist_get_string('categories'));
			$mform->setExpanded('categoriesheader');

			$mform->addElement('static', 'categories', local_playlist_get_string('groups'), $this->get_categories());

			if ($this->_customdata['type'] != 'mine') {
				$mform->addElement('header', 'onlineheader', local_playlist_get_string('onlineset'));

				$mform->addElement('advcheckbox', 'online', local_playlist_get_string('online'));

				$mform->addElement('date_selector', 'online_from', local_playlist_get_string('onlinefrom'), array(
					'startyear' => 2014,
					'stopyear' => date('Y') + 10,
					'optional' => true,
				));
				$mform->addElement('date_selector', 'online_to', local_playlist_get_string('onlineto'), array(
					'startyear' => 2014,
					'stopyear' => date('Y') + 10,
					'optional' => true,
				));
			} elseif (local_playlist_is_reviewer()) {
				// $mform->addElement('advcheckbox', 'online', local_playlist_get_string('online'));

				$radioarray = array();
				$radioarray[] = $mform->createElement('radio', 'online', '', local_playlist_trans(['de:in Review', 'en:in review']), LOCAL_PLAYLIST_ITEM_STATE_IN_REVIEW);
				$radioarray[] = $mform->createElement('radio', 'online', '', local_playlist_get_string('offline'), 0);
				$radioarray[] = $mform->createElement('radio', 'online', '', local_playlist_get_string('online'), 1);
				$mform->addGroup($radioarray, 'online', local_playlist_get_string("status"), array(' '), false);
			}

			$radioarray = array();
			$radioarray[] = $mform->createElement('radio', 'allow_comments', '', local_playlist_trans(['de:Alle Benutzer/innen', 'en:All users']), '');
			$radioarray[] = $mform->createElement('radio', 'allow_comments', '', local_playlist_trans(['de:Lehrende und Redaktionsteam', 'en:Teachers and Reviewers']), 'teachers_and_reviewers');
			$radioarray[] = $mform->createElement('radio', 'allow_comments', '', local_playlist_trans(['de:Redaktionsteam', 'en:Reviewers']), 'reviewers');
			$radioarray[] = $mform->createElement('radio', 'allow_comments', '', local_playlist_trans(['de:Keine Kommentare erlauben', 'en:No one (Disable comments)']), 'none');
			$mform->addGroup($radioarray, 'allow_comments', local_playlist_trans(['de:Kommentare erlauben von', 'en:Allow comments from']), array(' '), false);

			$this->add_action_buttons();
		}

		/**
		 * Get categories
		 * @return checkbox
		 */
		public function get_categories() {
			$mgr = new local_playlist_category_manager(true, local_playlist_course_settings::root_category_id());

			return $mgr->walktree(null, function($cat, $suboutput) {
				return '<div style="padding-left: '.(20 * $cat->level).'px;">'.
				'<input type="checkbox" name="categories[]" value="'.$cat->id.'" '.
				(in_array($cat->id, $this->_customdata['itemCategories']) ? 'checked ' : '').'/>'.
				($cat->level == 0 ? '<b>'.$cat->name.'</b>' : $cat->name).
				'</div>'.$suboutput;
			});
		}
	}

	$itemcategories = g::$DB->get_records_sql_menu("SELECT category.id, category.id AS val
    FROM {local_playlist_category} category
    LEFT JOIN {local_playlist_item_category} ic ON category.id=ic.category_id
    WHERE ic.item_id=?", array($id));

	if (!$itemcategories && $categoryid) {
		// at least one category
		$itemcategories[$categoryid] = $categoryid;
	}

	$itemeditform = new item_edit_form($_SERVER['REQUEST_URI'], [
		'itemCategories' => $itemcategories,
		'fileoptions' => $fileoptions,
		'type' => $type,
	]);

	if ($itemeditform->is_cancelled()) {
		if ($back = optional_param('back', '', PARAM_LOCALURL)) {
			redirect(new moodle_url($back));
		} else {
			redirect(new moodle_url('admin.php', ['courseid' => g::$COURSE->id]));
		}
	} else {
		if ($fromform = $itemeditform->get_data()) {
			// Edit/add.

			if ($type == 'mine' && empty($item->id)) {
				// normal user items should be offline first
				$fromform->online = LOCAL_PLAYLIST_ITEM_STATE_NEW;
			}

			if (!empty($item->id)) {
				$fromform->id = $item->id;
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
			g::$DB->delete_records('local_playlist_item_category', array("item_id" => $fromform->id));
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
				redirect(new moodle_url('mine.php', ['courseid' => g::$COURSE->id]));
			} else {
				redirect(new moodle_url('admin.php', ['courseid' => g::$COURSE->id]));
			}
			exit;

		} else {
			// Display form.

			$output = local_playlist_get_renderer();

			echo $output->header(defined('LOCAL_PLAYLIST_IS_ADMIN_MODE') && LOCAL_PLAYLIST_IS_ADMIN_MODE ? 'tab_manage_content' : null);

			$itemeditform->set_data($item);
			$itemeditform->display();

			echo $output->footer();
		}
	}
}

function local_playlist_format_url($url) {
	if (!preg_match('!^.*://!', $url)) {
		$url = 'http://'.$url;
	}

	return $url;
}

function local_playlist_get_fachsprachliches_lexikon_id() {
	return g::$DB->get_field('glossary', 'id', ['course' => g::$COURSE->id, 'name' => 'Fachsprachliches Lexikon']);
}

function local_playlist_get_fachsprachliches_lexikon_items() {
	$glossaryid = local_playlist_get_fachsprachliches_lexikon_id();

	return g::$DB->get_records_sql("
		SELECT concept, definition
		FROM {glossary_entries}
		WHERE glossaryid = ?
		ORDER BY concept
	", [$glossaryid]);

	return $records;
}

/**
 * @method static int root_category_id()
 * @method static bool alternative_wording()
 * @method static bool use_review()
 * @method static bool use_terms_of_service()
 * @method static bool allow_comments()
 * @method static bool allow_rating()
 * @property int root_category_id
 * @property bool alternative_wording
 * @property bool use_review
 * @property bool use_terms_of_service
 * @property bool allow_comments
 * @property bool allow_rating
 */
class local_playlist_course_settings {

	static protected $courses = [];

	protected $courseid;
	protected $settings;

	function __construct($courseid) {
		$this->courseid = $courseid;

		$settings = get_config('local_playlist', "course[$courseid]");
		if ($settings) {
			$settings = json_decode($settings);
		}

		if (!$settings) {
			$this->settings = (object)[];
		} else {
			$this->settings = (object)$settings;
		}
	}

	static function get_course($courseid = null) {
		if ($courseid === null) {
			$courseid = g::$COURSE->id;
		}

		if (isset(static::$courses[$courseid])) {
			return static::$courses[$courseid];
		} else {
			return static::$courses[$courseid] = new static($courseid);
		}
	}

	static function __callStatic($name, $arguments) {
		$settings = static::get_course();

		return $settings->$name;
	}

	function __get($name) {
		//if (in_array($name, ['root_category_id'])) {
		if ($name == 'allow_rating') {
			$name = 'allow_comments';
		}

		return @$this->settings->$name;
		//} else {
		//	throw new moodle_exception("function $name not found");
		//}
	}

	function __set($name, $value) {
		$this->settings->$name = $value;
	}

	function save() {
		$settings = json_encode($this->settings);
		set_config("course[{$this->courseid}]", $settings, 'local_playlist');
	}
}

function local_playlist_limit_item_to_category_where($category_id) {
	if (!$category_id) {
		return '';
	} else {
		return " AND item.id IN (
			SELECT item_id FROM {local_playlist_item_category}
			WHERE category_id=".(int)$category_id."
		)";
	}
}

function local_playlist_get_comments($itemid) {
    global $DB, $USER, $OUTPUT, $PAGE;

    $PAGE->set_context(context_system::instance());
    if (!$itemid) return false;

    // Fetch comments and their reactions from the database
    $comments = $DB->get_records_sql('
        SELECT c.id, c.reply_id, c.itemid, c.userid, c.text, c.time_created, c.time_modified, c.isprivate,
               r.id as reaction_id, r.commentid, r.userid as reaction_userid, r.reaction
        FROM {local_playlist_item_comments} c
        LEFT JOIN {local_playlist_item_reactions} r ON c.id = r.commentid
        WHERE c.itemid = ?
        ORDER BY c.reply_id, c.time_created ASC
    ', array($itemid));

    // Initialize an empty array to hold the comments and replies
    $grouped_comments = [];

    foreach ($comments as $comment) {
        // Check if the user has a profile picture
        $commenter = $DB->get_record('user', ['id' => $comment->userid]);
        $has_picture = !empty($commenter->picture) && $commenter->picture != 0;
        $user_picture = $has_picture ? 
                        $OUTPUT->user_picture($commenter, ['class' => 'userpicture defaultuserpic']) : 
                        $OUTPUT->pix_icon('u/f2', 'User Icon', 'core', ['class' => 'userpicture defaultuserpic']);

        // Prepare comment data
        $comment_data = [
            'id' => $comment->id,
            'itemid' => $comment->itemid,
            'userid' => $comment->userid,
            'user_fullname' => fullname($commenter),
            'user_picture' => $user_picture,
            'text' => $comment->text,
            'time_created' => userdate($comment->time_created),
            'time_modified' => userdate($comment->time_modified),
            'canedit' => $comment->userid == $USER->id,
            'replies' => [],
            'reactions' => [
                'likes' => 0,
                'dislikes' => 0,
                'user_reaction' => null,
                'is_liked' => false,
                'is_disliked' => false
            ]
        ];

        // Add reaction data to the comment
        if (!empty($comment->reaction_id)) {
            if ($comment->reaction == 1) {
                $comment_data['reactions']['likes']++;
            } elseif ($comment->reaction == -1) {
                $comment_data['reactions']['dislikes']++;
            }

            if ($comment->reaction_userid == $USER->id) {
                $comment_data['reactions']['user_reaction'] = $comment->reaction;
                $comment_data['reactions']['is_liked'] = ($comment->reaction == 1);
                $comment_data['reactions']['is_disliked'] = ($comment->reaction == -1);
            }
        }

        // Determine if it's a reply or a parent comment
        if ($comment->reply_id == 0) {
            // It's a parent comment
            $grouped_comments[$comment->id] = $comment_data;
        } else {
            // It's a reply, add it to the parent comment's replies
            if (isset($grouped_comments[$comment->reply_id])) {
                $grouped_comments[$comment->reply_id]['replies'][] = $comment_data;
            }
        }
    }

    // Return the grouped comments with replies and reactions
    return array_values($grouped_comments);
}

function local_playlist_calculate_rating($itemid) {
    global $DB;

    if (!$itemid) return false;

    // Fetch ratings for the specific item
    $ratings = $DB->get_records("local_playlist_item_ratings", ['itemid' => $itemid], 'time_created ASC');

    if (!$ratings) return [
        'average' => 0,
		'itemid' => $itemid,
        'stars' => generate_star_list(0)
    ];

    $total_rating = 0;
    $rating_count = 0;

    // Calculate total rating and count
    foreach ($ratings as $rating) {
        $total_rating += $rating->rating;
        $rating_count++;
    }

    // Calculate the average rating
    $average_rating = 0;
    if ($rating_count > 0) {
        $average_rating = $total_rating / $rating_count;
    }

    // Format the average rating to the nearest 0.5
    $average_rating = round($average_rating * 2) / 2;

    return [
        'average' => $average_rating,
		'itemid' => $itemid,
        'stars' => generate_star_list($average_rating)
    ];
}

function generate_star_list($average_rating) {
    $stars = [];
    for ($i = 1; $i <= 5; $i++) {  // Corrected loop
        if ($i <= floor($average_rating)) {
            $stars[] = ['class' => 'fa-star', 'data-val' => $i];
        } elseif ($i == ceil($average_rating) && $average_rating % 1 != 0) {
            $stars[] = ['class' => 'fa-star-half-o', 'data-val' => $i];
        } else {
            $stars[] = ['class' => 'fa-star-o', 'data-val' => $i];
        }
    }
    return $stars;
}

function local_playlist_set_views($itemid){
	global $DB, $USER;
	if (!$itemid) return;

	$cur_user_view = $DB->get_record('local_playlist_item_views', ['itemid' => $itemid, 'userid' => $USER->id]);

	if ($cur_user_view) {
		return;
	} else {
		$view = new StdClass;
		$view->itemid = $itemid;
		$view->userid = $USER->id;
		$view->time_created = time();
		$view->time_modified = 0;
		$DB->insert_record('local_playlist_item_views', $view);
	}
}

function local_playlist_get_views($itemid){
	global $DB;

	if (!$itemid) return 0;
	// Fetch the view count for the specific item ID
	$sql = "SELECT COUNT(*) AS view_count
			FROM {local_playlist_item_views}
			WHERE itemid = :itemid";
	$params = array('itemid' => $itemid);
	$view_count = $DB->get_field_sql($sql, $params);
	
	return $view_count;
}

function local_playlist_convert_duration($duration_number, $duration_timeunit) {

	// Convert the duration into a readable format
	$duration_in_seconds = $duration_number * $duration_timeunit;
	$hours = floor($duration_in_seconds / 3600);
	$minutes = floor(($duration_in_seconds % 3600) / 60);
	$days = floor($duration_in_seconds / 86400);
	$weeks = floor($duration_in_seconds / 604800);

	// Format the duration based on the selected unit
	switch ($duration_timeunit) {
		case 1:
			$formatted_duration = $duration_number . ' seconds';
			break;
		case 60:
			$formatted_duration = $minutes . ' minutes';
			break;
		case 3600:
			$formatted_duration = $hours . ' hours';
			break;
		case 86400:
			$formatted_duration = $days . ' days';
			break;
		case 604800:
			$formatted_duration = $weeks . ' weeks';
			break;
		default:
			$formatted_duration = $duration_number . ' units';
			break;
	}

	return $formatted_duration;
}

function get_youtube_embed_url($url) {
    // Validate input
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null; // Return null if the URL is invalid
    }

    $parsed_url = parse_url($url);

    // Ensure 'host' is set in the parsed URL
    if (!isset($parsed_url['host'])) {
        return null;
    }

    // Handle short YouTube links (youtu.be)
    if (strpos($parsed_url['host'], 'youtu.be') !== false) {
        $video_id = trim($parsed_url['path'], '/');
        return "https://www.youtube.com/embed/{$video_id}";
    }

    // Handle standard YouTube links (youtube.com)
    if (strpos($parsed_url['host'], 'youtube.com') !== false) {
        parse_str($parsed_url['query'], $query_params);

        if (!empty($query_params['v'])) {
            $video_id = $query_params['v'];

            // Check for playlist
            if (!empty($query_params['list'])) {
                $playlist_id = $query_params['list'];
                return "https://www.youtube.com/embed/{$video_id}?list={$playlist_id}";
            }

            return "https://www.youtube.com/embed/{$video_id}";
        }
    }

    // Return original URL for unsupported cases
    return $url;
}

function get_accessable_learningspace(){
    global $DB, $USER;
	$learningspaces = $DB->get_records('local_learningspace', ['published' => 1], 'is_default DESC', '*');
    $filtered_learningspaces = [];

    foreach ($learningspaces as $key => $learningspace) {
        // Skip processing if the user is the creator
        if (local_playlist_is_creator()) {
            $filtered_learningspaces[] = [
                'first_letter' => strtoupper(substr($learningspace->name, 0, 1)),
                'id' => $learningspace->id,
                'name' => $learningspace->name
            ];
            continue;
        }

        // Check if the user is a specific user or owner
        $arr_user_ids = explode(',', $learningspace->user_ids);
        $arr_owner_ids = explode(',', $learningspace->owner_ids);
        if (in_array($USER->id, $arr_user_ids) || in_array($USER->id, $arr_owner_ids)) {
            $filtered_learningspaces[] = [
                'first_letter' => strtoupper(substr($learningspace->name, 0, 1)),
                'id' => $learningspace->id,
                'name' => $learningspace->name
            ];
            continue;
        }

        // Check if the user is a member of any cohort
        $arr_cohort_ids = explode(',', $learningspace->cohort_ids);
        foreach ($arr_cohort_ids as $cohort) {
            if (cohort_is_member($cohort, $USER->id)) {
                $filtered_learningspaces[] = [
                    'first_letter' => strtoupper(substr($learningspace->name, 0, 1)),
                    'id' => $learningspace->id,
                    'name' => $learningspace->name
                ];
                break;
            }
        }
    }

    return $filtered_learningspaces;
}

function get_accessable_learningspace_list() {
    global $DB, $USER;

    $sql = "SELECT id, name, owner_ids FROM {local_learningspace} WHERE published = :published ORDER BY name ASC";
    $params = ['published' => 1];
    $learningspaces = $DB->get_records_sql($sql, $params);

    $filtered_learningspaces = [];

    foreach ($learningspaces as $learningspace) {
        if (local_playlist_is_creator()) {
            $filtered_learningspaces[$learningspace->id] = $learningspace->name;
            continue;
        }

        $arr_owner_ids = explode(',', $learningspace->owner_ids);
        if (in_array($USER->id, $arr_owner_ids)) {
            $filtered_learningspaces[$learningspace->id] = $learningspace->name;
            continue;
        }
    }

    return $filtered_learningspaces;
}

function check_eligible_access_item($learningspace_ids) {
	global $CFG, $DB, $USER;
	require_once($CFG->dirroot.'/cohort/lib.php');

	if (local_playlist_is_creator()) return true;

	$arr_learningspace_ids = explode(',', $learningspace_ids);
	foreach($arr_learningspace_ids as $learningspace_id) {
		$learningspace = $DB->get_record('local_learningspace', ['id' => $learningspace_id]);
	
		// Check if the user is a specific user or owner
		$arr_user_ids = explode(',', $learningspace->user_ids);
		$arr_owner_ids = explode(',', $learningspace->owner_ids);
		if (in_array($USER->id, $arr_user_ids) || in_array($USER->id, $arr_owner_ids)) {
			return true;
		}
	
		// Check if the user is a member of any cohort
		$arr_cohort_ids = explode(',', $learningspace->cohort_ids);
		foreach ($arr_cohort_ids as $cohort) {
			if (cohort_is_member($cohort, $USER->id)) return true;
		}
	}
	return false;
}

function get_content_types() {

	$contentTypes[] = [
		'type'=>'course',
		'name' => 'Course',
	];
	$contentTypes[] = [
		'type'=>'article',
		'name' => 'Article',
	];
	$contentTypes[] = [
		'type'=>'document',
		'name' => 'Document',
	];
	$contentTypes[] = [
		'type'=>'playlist',
		'name' => 'playlist',
	];
	$contentTypes[] = [
		'type'=>'video',
		'name' => 'Video',
	];

	return $contentTypes;
}