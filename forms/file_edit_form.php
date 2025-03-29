<?php
/**
 * Page edit form
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  gtn gmbh <office@gtn-solutions.com>
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot . '/cohort/lib.php');

class file_edit_form extends moodleform {

    /**
     * Definition
     * @return nothing
     */
    public function definition() {
        $mform =& $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', 0);

        $mform->addElement('hidden', 'addtype');
        $mform->setType('addtype', PARAM_TEXT);
        $mform->setDefault('addtype', $this->_customdata['addtype']);

        $mform->addElement('hidden', 'type');
        $mform->setType('type', PARAM_TEXT);

        $mform->addElement('text', 'name', local_playlist_get_string('name'), 'size="100"');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('requiredelement', 'form'), 'required', null, 'client');

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

        /*
        if (!local_playlist_course_settings::alternative_wording()) {
            $mform->addElement('text', 'source', local_playlist_get_string('source'), 'size="100"');
            $mform->setType('source', PARAM_TEXT);
        }

        
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
        

        $mform->addElement('text', 'authors', local_playlist_get_string('authors'), 'size="100"');
        $mform->setType('authors', PARAM_TEXT);

        $to_year = date('Y') + 1;

        $values = range(2010,$to_year);
        $values = ['' => ''] + array_combine($values, $values);
        $mform->addElement('select', 'year', local_playlist_get_string('year', 'form'), $values);
        $mform->setType('year', PARAM_INT);

       
        $mform->addElement('header', 'contentheader', local_playlist_get_string('content'));
        $mform->setExpanded('contentheader');
        

        $mform->addElement('text', 'link', local_playlist_get_string('link'), 'size="100"');
        $mform->setType('link', PARAM_TEXT);

        */

        $mform->addElement('editor', 'abstract_editor', local_playlist_get_string('abstract'), 'rows="5" cols="50" style="width: 95%"');
        $mform->setType('abstract', PARAM_RAW);

        /*
        $mform->addElement('editor', 'content_editor', local_playlist_get_string('content'), 'rows="20" cols="50" style="width: 95%"');
        $mform->setType('content', PARAM_RAW);
        */
        
        $mform->addElement('filemanager', 'file_filemanager', local_playlist_get_string('files'), null, $this->_customdata['fileoptions']);
        $mform->addRule('file_filemanager', get_string('requiredelement', 'form'), 'required', null, 'client');
        
        $mform->addElement('filemanager', 'preview_image_filemanager', local_playlist_get_string('previmg'), null, $this->_customdata['filepreviewoptions']);

        $mform->addElement('tags','tags', get_string('tags'), [
                'itemtype' => 'local_playlist_item',
                'component' => 'local_playlist',
            ]
        );

        // Define an array to hold the grouped elements
        $duration_elements = array();

        // Time input
        $duration_elements[] =& $mform->createElement('text', 'duration_number', '', array('size' => '3'));
        $mform->setType('duration_number', PARAM_INT);
        $mform->setDefault('duration_number', 0);
        //$mform->addRule('duration_number', get_string('required'), 'required', null, 'client');
        //$mform->addRule('duration_number', get_string('numericrequired'), 'numeric', null, 'client');

        // Time Unit select
        $timeunits = array(
            604800 => get_string('weeks'),
            86400 => get_string('days'),
            3600 => get_string('hours'),
            60 => get_string('minutes'),
            1 => get_string('seconds')
        );
        $duration_elements[] =& $mform->createElement('select', 'duration_timeunit', '', $timeunits);
        $mform->setType('duration_timeunit', PARAM_INT);
        $mform->setDefault('duration_timeunit', 60);

        // Add the group of elements to the form
        $mform->addGroup($duration_elements, 'duration_group', get_string('readingtime', 'local_playlist'), ' ', false);


        $mform->addElement('header', 'categoriesheader', local_playlist_get_string('categories'));
        $mform->setExpanded('categoriesheader');

        $select = $mform->addElement('select', 'learningspace_ids', get_string('learningspace', 'local_playlist'), $this->_customdata['learningspaces']);
        $select->setMultiple(true);
        $mform->setType('learningspace_ids', PARAM_SEQUENCE);

        if ($this->_customdata['action'] != 'mine') {
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

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check that end date is not before start date.
        //if (!empty($data['enddate_enable']) && !empty($data['startdate_enable']) && isset($data['enddate']) && isset($data['startdate']) && $data['enddate'] < $data['startdate']) {
        //    $errors['enddate'] = get_string('err_enddatebeforestart', 'local_learningpaths');
        //}

        return $errors;
    }
}