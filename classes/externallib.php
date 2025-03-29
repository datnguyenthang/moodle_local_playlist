<?php
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->dirroot . "/local/playlist/inc.php");

class local_playlist_external extends external_api {

    // Define the function parameters.
    public static function get_allcontents_parameters() {
        return new external_function_parameters(
            array(
                'search' => new external_value(PARAM_TEXT, 'search string', VALUE_DEFAULT, ''),
                'learningspace' => new external_value(PARAM_INT, 'learningspace number', VALUE_DEFAULT, 0),
                'type' => new external_value(PARAM_TEXT, 'Type of content 1:Link - 2:Video - 3:File - 4:Page - 5:Course', VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Number of courses per page', VALUE_DEFAULT, 12),
                'page' => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 1),
            )
        );
    }

    public static function get_allcontents($search = '', $learningspace = 0, $type = 0, $limit = 12, $page = 1) {
        global $DB, $CFG, $USER;
    
        $params = self::validate_parameters(self::get_allcontents_parameters(), array(
            'search' => $search,
            'learningspace' => $learningspace,
            'type' => $type,
            'limit' => $limit,
            'page' => $page,
        ));
    
        $offset = ($params['page'] - 1) * $params['limit'];
    
        // Build SQL conditions and parameters array
        $conditions = [];
        $sqlparams = [];
    
        $conditions[] = 'pi.online > 0';
    
        if (!empty($params['type'])) {
            // Split the comma-separated string into an array.
            $types = explode(',', $params['type']);
            list($insql, $sqlparams) = $DB->get_in_or_equal($types, SQL_PARAMS_NAMED, 'type');
            $conditions[] = 'pi.type ' . $insql;
        }
        if ($params['learningspace'] != 0) { // specific learning space
            $conditions[] = 'FIND_IN_SET(:learningspace, pi.learningspace_ids) > 0';
            $sqlparams['learningspace'] = $params['learningspace'];
        } else if ($params['learningspace'] == -1) { // all learning space
            //TODO search all learning space that user belong to
            //$conditions[] = 'FIND_IN_SET(:learningspace, pi.learningspace_ids) > 0';
            //$sqlparams['learningspace'] = $params['learningspace'];
        }
    
        if ($params['search']) {
            $searchConditions = [
                'pi.name LIKE :searchname',
                'pi.content LIKE :searchcontent',
                'pi.abstract LIKE :searchabstract'
            ];
            
            // Prepare SQL parameters for the main search fields
            $sqlparams['searchname'] = '%' . $params['search'] . '%';
            $sqlparams['searchcontent'] = '%' . $params['search'] . '%';
            $sqlparams['searchabstract'] = '%' . $params['search'] . '%';
        
            // Directly query the tag table for matching tags
            $tagSearch = '%' . $params['search'] . '%';
            $tagIds = $DB->get_fieldset_select('tag', 'id', 'rawname LIKE ?', array($tagSearch));
        
            if (!empty($tagIds)) {
                list($inSql, $tagSqlParams) = $DB->get_in_or_equal($tagIds, SQL_PARAMS_NAMED, 'tag');
                $searchConditions[] = "pi.id IN (SELECT ti.itemid FROM {tag_instance} ti WHERE ti.component = 'local_playlist' AND ti.tagid $inSql)";
                $sqlparams = array_merge($sqlparams, $tagSqlParams);
            }
        
            $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        }
        
    
        $sqlwhere = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Get total contents.
        $sql = "SELECT COUNT(DISTINCT pi.id)
            FROM {local_playlist_item} pi
            LEFT JOIN {tag_instance} ti ON ti.itemid = pi.id AND ti.component = 'local_playlist'
            LEFT JOIN {tag} t ON t.id = ti.tagid
            $sqlwhere";
        $totalcontents = $DB->count_records_sql($sql, $sqlparams);
    
        // Get the contents for this page.
        $sql = "SELECT DISTINCT pi.id, pi.name, pi.type, pi.link, pi.source, pi.file, pi.authors, pi.year, pi.content, pi.online, 
                pi.online_from, pi.online_to, pi.time_created, pi.time_modified, pi.modified_by, pi.link_title, 
                pi.created_by, pi.reviewer_id, pi.abstract, pi.allow_comments, pi.real_fiktiv, pi.duration_number, 
                pi.duration_timeunit
            FROM {local_playlist_item} pi
            LEFT JOIN {tag_instance} ti ON ti.itemid = pi.id AND ti.component = 'local_playlist'
            LEFT JOIN {tag} t ON t.id = ti.tagid
            $sqlwhere 
            ORDER BY pi.id ASC";
        $contents = $DB->get_records_sql($sql, $sqlparams, $offset, $params['limit']);
    
        $contentsData = [];
        foreach ($contents as $content) {
            $created_by = $DB->get_record('user', array('id' => $content->created_by));
            $fs = get_file_storage();
            $areafiles = $fs->get_area_files(context_system::instance()->id,
                'local_playlist',
                'preview_image',
                $content->id,
                'itemid',
                false);
            $previewimage = reset($areafiles);
            $previewimageurl = '';
            if ($previewimage) {
                $previewimageurl = local_playlist_get_url_for_file($previewimage)->out(false);
            }
    
            if ($content->type == LOCAL_PLAYLIST_ITEM_TYPE_COURSE) {
                $detail_link = new moodle_url('/course/view.php', array('id' => $content->id));
            } else {
                $detail_link = new moodle_url('/local/playlist/detail.php', array('itemid' => $content->id));
            }
    
            $is_video = $is_page = $is_file = $is_link = $is_course = false;
    
            switch ($content->type) {
                case LOCAL_PLAYLIST_ITEM_TYPE_VIDEO:
                    $is_video = true;
                    break;
                case LOCAL_PLAYLIST_ITEM_TYPE_PAGE:
                    $is_page = true;
                    break;
                case LOCAL_PLAYLIST_ITEM_TYPE_FILE:
                    $is_file = true;
                    break;
                case LOCAL_PLAYLIST_ITEM_TYPE_LINK:
                    $is_link = true;
                    break;
                case LOCAL_PLAYLIST_ITEM_TYPE_COURSE:
                    $is_course = true;
                    break;
            }
    
            // Get view count and rating of item
            $viewcount = local_playlist_get_views($content->id);
            $rating = local_playlist_calculate_rating($content->id);

            $contentsData[] = [
                'id' => $content->id,
                'name' => $content->name,
                'type' => $content->type,
                'link' => $content->link,
                'source' => $content->source,
                'file' => $content->file,
                'authors' => $content->authors,
                'year' => $content->year,
                'content' => $content->content,
                'online' => $content->online,
                'online_from' => $content->online_from,
                'online_to' => $content->online_to,
                'time_created' => date('d/m/Y', $content->time_created),
                'time_modified' => date('d/m/Y', $content->time_modified),
                'modified_by' => $content->modified_by,
                'link_title' => $content->link_title,
                'created_by' => fullname($created_by),
                'reviewer_id' => $content->reviewer_id,
                'abstract' => $content->abstract,
                'allow_comments' => $content->allow_comments,
                'real_fiktiv' => $content->real_fiktiv,
                'reading_time' => local_playlist_convert_duration($content->duration_number, $content->duration_timeunit),
                'tags' => isset($content->tags) ? $content->tags : '',
                'previewimageurl' => $previewimageurl,
                'detail_link' => $detail_link->out(false),
                'is_video' => $is_video,
                'is_page' => $is_page,
                'is_file' => $is_file,
                'is_link' => $is_link,
                'is_course' => $is_course,
                'is_creator' => $USER->id == $content->created_by,
                'viewcount' => $viewcount,
                'rating' => array(
                    'average' => $rating['average'],
                    'itemid' => $content->id,
                    'stars' => $rating['stars']
                )
            ];
        }
    
        return array(
            'contents' => $contentsData,
            'has_slide' => $totalcontents > 5,
        );
    }

    public static function get_allcontents_returns() {
        return new external_single_structure(
            array(
                'contents' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Content id'),
                            'name' => new external_value(PARAM_TEXT, 'Content name'),
                            'type' => new external_value(PARAM_INT, 'Content type', VALUE_OPTIONAL, 0),
                            'link' => new external_value(PARAM_TEXT, 'Content link', VALUE_OPTIONAL, ''),
                            'source' => new external_value(PARAM_TEXT, 'Content source', VALUE_OPTIONAL, ''),
                            'file' => new external_value(PARAM_TEXT, 'Content file', VALUE_OPTIONAL, ''),
                            'authors' => new external_value(PARAM_TEXT, 'Content authors', VALUE_OPTIONAL, ''),
                            'year' => new external_value(PARAM_INT, 'Content year', VALUE_OPTIONAL, 0),
                            'content' => new external_value(PARAM_RAW, 'Content content', VALUE_OPTIONAL, ''),
                            'online_from' => new external_value(PARAM_INT, 'Content online from', VALUE_OPTIONAL, 0),
                            'online_to' => new external_value(PARAM_INT, 'Content online to', VALUE_OPTIONAL, 0),
                            'time_created' => new external_value(PARAM_TEXT, 'Content time created', VALUE_OPTIONAL, 0),
                            'time_modified' => new external_value(PARAM_TEXT, 'Content time modified', VALUE_OPTIONAL, 0),
                            'modified_by' => new external_value(PARAM_INT, 'Content modified by', VALUE_OPTIONAL, 0),
                            'online' => new external_value(PARAM_INT, 'Content online', VALUE_OPTIONAL, 0),
                            'link_title' => new external_value(PARAM_TEXT, 'Content link title', VALUE_OPTIONAL, ''),
                            'created_by' => new external_value(PARAM_TEXT, 'Content created by', VALUE_OPTIONAL, 0),
                            'reviewer_id' => new external_value(PARAM_INT, 'Content reviewer id', VALUE_OPTIONAL, 0),
                            'abstract' => new external_value(PARAM_RAW, 'Content abstract', VALUE_OPTIONAL, ''),
                            'allow_comments' => new external_value(PARAM_TEXT, 'Content allow comments', VALUE_OPTIONAL, ''),
                            'real_fiktiv' => new external_value(PARAM_TEXT, 'Content real fiktiv', VALUE_OPTIONAL, ''),
                            'reading_time' => new external_value(PARAM_TEXT, 'Content reading time', VALUE_OPTIONAL, 0),
                            'tags' => new external_value(PARAM_TEXT, 'Content tags', VALUE_OPTIONAL, ''),
                            'previewimageurl' => new external_value(PARAM_TEXT, 'Content preview image', VALUE_OPTIONAL, ''),
                            'detail_link' => new external_value(PARAM_TEXT, 'Content detail link', VALUE_OPTIONAL, ''),
                            'is_video' => new external_value(PARAM_BOOL, 'Is video', VALUE_OPTIONAL, false),
                            'is_page' => new external_value(PARAM_BOOL, 'Is page', VALUE_OPTIONAL, false),
                            'is_file' => new external_value(PARAM_BOOL, 'Is file', VALUE_OPTIONAL, false),
                            'is_link' => new external_value(PARAM_BOOL, 'Is link', VALUE_OPTIONAL, false),
                            'is_course' => new external_value(PARAM_BOOL, 'Is course', VALUE_OPTIONAL, false),
                            'is_creator' => new external_value(PARAM_BOOL, 'Is creator', VALUE_OPTIONAL, false),
                            'viewcount' => new external_value(PARAM_INT, 'Content viewcount', VALUE_OPTIONAL, 0),
                            'rating' => new external_single_structure(
                                array(
                                    'average' => new external_value(PARAM_FLOAT, 'Average rating'),
                                    'itemid' => new external_value(PARAM_INT, 'Item ID of rating'),
                                    'stars' => new external_multiple_structure(
                                        new external_single_structure(
                                            array(
                                                'class' => new external_value(PARAM_TEXT, 'Star class'),
                                                'data-val' => new external_value(PARAM_INT, 'Data value for star', VALUE_OPTIONAL, 0)
                                            )
                                        )
                                    )
                                )
                            )
                        )
                    )
                ),
                'has_slide' => new external_value(PARAM_BOOL, 'Total contents')
            )
        );
    }

    // Define the function parameters.
    public static function get_mycontents_parameters() {
        return new external_function_parameters(
            array(
                'search' => new external_value(PARAM_TEXT, 'search string', VALUE_DEFAULT, ''),
                'learningspace' => new external_value(PARAM_INT, 'learningspace number', VALUE_DEFAULT, 0),
                'type' => new external_value(PARAM_TEXT, 'Type of content 1:Link - 2:Video - 3:File - 4:Page - 5:Course', VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Number of courses per page', VALUE_DEFAULT, 12),
                'page' => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 1),
            )
        );
    }

    public static function get_mycontents($search = '', $learningspace = 0, $type = 0, $limit = 12, $page = 1) {
        global $DB, $CFG, $USER;
    
        $params = self::validate_parameters(self::get_allcontents_parameters(), array(
            'search' => $search,
            'learningspace' => $learningspace,
            'type' => $type,
            'limit' => $limit,
            'page' => $page,
        ));
    
        $offset = ($params['page'] - 1) * $params['limit'];
    
        // Build SQL conditions and parameters array
        $conditions = [];
        $sqlparams = [];
    
        //$conditions[] = 'pi.online > 0';
        $conditions[] = 'pi.created_by = ' . $USER->id. '';
    
        if (!empty($params['type'])) {
            // Split the comma-separated string into an array.
            $types = explode(',', $params['type']);
            list($insql, $sqlparams) = $DB->get_in_or_equal($types, SQL_PARAMS_NAMED, 'type');
            $conditions[] = 'pi.type ' . $insql;
        }
        if ($params['learningspace'] != 0) { // specific learning space
            $conditions[] = 'FIND_IN_SET(:learningspace, pi.learningspace_ids) > 0';
            $sqlparams['learningspace'] = $params['learningspace'];
        } else if ($params['learningspace'] == -1) { // all learning space
            //TODO search all learning space that user belong to
            //$conditions[] = 'FIND_IN_SET(:learningspace, pi.learningspace_ids) > 0';
            //$sqlparams['learningspace'] = $params['learningspace'];
        }
    
        if ($params['search']) {
            $searchConditions = [
                'pi.name LIKE :searchname',
                'pi.content LIKE :searchcontent',
                'pi.abstract LIKE :searchabstract'
            ];
            
            // Prepare SQL parameters for the main search fields
            $sqlparams['searchname'] = '%' . $params['search'] . '%';
            $sqlparams['searchcontent'] = '%' . $params['search'] . '%';
            $sqlparams['searchabstract'] = '%' . $params['search'] . '%';
        
            // Directly query the tag table for matching tags
            $tagSearch = '%' . $params['search'] . '%';
            $tagIds = $DB->get_fieldset_select('tag', 'id', 'rawname LIKE ?', array($tagSearch));
        
            if (!empty($tagIds)) {
                list($inSql, $tagSqlParams) = $DB->get_in_or_equal($tagIds, SQL_PARAMS_NAMED, 'tag');
                $searchConditions[] = "pi.id IN (SELECT ti.itemid FROM {tag_instance} ti WHERE ti.component = 'local_playlist' AND ti.tagid $inSql)";
                $sqlparams = array_merge($sqlparams, $tagSqlParams);
            }
        
            $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        }
        
    
        $sqlwhere = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Get total contents.
        $sql = "SELECT COUNT(DISTINCT pi.id)
            FROM {local_playlist_item} pi
            LEFT JOIN {tag_instance} ti ON ti.itemid = pi.id AND ti.component = 'local_playlist'
            LEFT JOIN {tag} t ON t.id = ti.tagid
            $sqlwhere";
        $totalcontents = $DB->count_records_sql($sql, $sqlparams);
    
        // Get the contents for this page.
        $sql = "SELECT DISTINCT pi.id, pi.name, pi.type, pi.link, pi.source, pi.file, pi.authors, pi.year, pi.content, pi.online, 
                pi.online_from, pi.online_to, pi.time_created, pi.time_modified, pi.modified_by, pi.link_title, 
                pi.created_by, pi.reviewer_id, pi.abstract, pi.allow_comments, pi.real_fiktiv, pi.duration_number, 
                pi.duration_timeunit
            FROM {local_playlist_item} pi
            LEFT JOIN {tag_instance} ti ON ti.itemid = pi.id AND ti.component = 'local_playlist'
            LEFT JOIN {tag} t ON t.id = ti.tagid
            $sqlwhere 
            ORDER BY pi.id ASC";
        $contents = $DB->get_records_sql($sql, $sqlparams, $offset, $params['limit']);
    
        $contentsData = [];
        foreach ($contents as $content) {
            $created_by = $DB->get_record('user', array('id' => $content->created_by));
            $fs = get_file_storage();
            $areafiles = $fs->get_area_files(context_system::instance()->id,
                'local_playlist',
                'preview_image',
                $content->id,
                'itemid',
                false);
            $previewimage = reset($areafiles);
            $previewimageurl = '';
            if ($previewimage) {
                $previewimageurl = local_playlist_get_url_for_file($previewimage)->out(false);
            }
    
            if ($content->type == LOCAL_PLAYLIST_ITEM_TYPE_COURSE) {
                $detail_link = new moodle_url('/course/view.php', array('id' => $content->id));
            } else {
                $detail_link = new moodle_url('/local/playlist/detail.php', array('itemid' => $content->id));
            }
    
            $is_video = $is_page = $is_file = $is_link = $is_course = false;
    
            switch ($content->type) {
                case LOCAL_PLAYLIST_ITEM_TYPE_VIDEO:
                    $is_video = true;
                    break;
                case LOCAL_PLAYLIST_ITEM_TYPE_PAGE:
                    $is_page = true;
                    break;
                case LOCAL_PLAYLIST_ITEM_TYPE_FILE:
                    $is_file = true;
                    break;
                case LOCAL_PLAYLIST_ITEM_TYPE_LINK:
                    $is_link = true;
                    break;
                case LOCAL_PLAYLIST_ITEM_TYPE_COURSE:
                    $is_course = true;
                    break;
            }
    
            // Get view count and rating of item
            $viewcount = local_playlist_get_views($content->id);
            $rating = local_playlist_calculate_rating($content->id);

            $contentsData[] = [
                'id' => $content->id,
                'name' => $content->name,
                'type' => $content->type,
                'link' => $content->link,
                'source' => $content->source,
                'file' => $content->file,
                'authors' => $content->authors,
                'year' => $content->year,
                'content' => $content->content,
                'online' => $content->online,
                'online_from' => $content->online_from,
                'online_to' => $content->online_to,
                'time_created' => date('d/m/Y', $content->time_created),
                'time_modified' => date('d/m/Y', $content->time_modified),
                'modified_by' => $content->modified_by,
                'link_title' => $content->link_title,
                'created_by' => fullname($created_by),
                'reviewer_id' => $content->reviewer_id,
                'abstract' => $content->abstract,
                'allow_comments' => $content->allow_comments,
                'real_fiktiv' => $content->real_fiktiv,
                'reading_time' => local_playlist_convert_duration($content->duration_number, $content->duration_timeunit),
                'tags' => isset($content->tags) ? $content->tags : '',
                'previewimageurl' => $previewimageurl,
                'detail_link' => $detail_link->out(false),
                'is_video' => $is_video,
                'is_page' => $is_page,
                'is_file' => $is_file,
                'is_link' => $is_link,
                'is_course' => $is_course,
                'is_creator' => $USER->id == $content->created_by,
                'viewcount' => $viewcount,
                'rating' => array(
                    'average' => $rating['average'],
                    'itemid' => $content->id,
                    'stars' => $rating['stars']
                )
            ];
        }
    
        return array(
            'contents' => $contentsData,
            'has_slide' => $totalcontents > 5,
        );
    }

    public static function get_mycontents_returns() {
        return new external_single_structure(
            array(
                'contents' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Content id'),
                            'name' => new external_value(PARAM_TEXT, 'Content name'),
                            'type' => new external_value(PARAM_INT, 'Content type', VALUE_OPTIONAL, 0),
                            'link' => new external_value(PARAM_TEXT, 'Content link', VALUE_OPTIONAL, ''),
                            'source' => new external_value(PARAM_TEXT, 'Content source', VALUE_OPTIONAL, ''),
                            'file' => new external_value(PARAM_TEXT, 'Content file', VALUE_OPTIONAL, ''),
                            'authors' => new external_value(PARAM_TEXT, 'Content authors', VALUE_OPTIONAL, ''),
                            'year' => new external_value(PARAM_INT, 'Content year', VALUE_OPTIONAL, 0),
                            'content' => new external_value(PARAM_RAW, 'Content content', VALUE_OPTIONAL, ''),
                            'online_from' => new external_value(PARAM_INT, 'Content online from', VALUE_OPTIONAL, 0),
                            'online_to' => new external_value(PARAM_INT, 'Content online to', VALUE_OPTIONAL, 0),
                            'time_created' => new external_value(PARAM_TEXT, 'Content time created', VALUE_OPTIONAL, 0),
                            'time_modified' => new external_value(PARAM_TEXT, 'Content time modified', VALUE_OPTIONAL, 0),
                            'modified_by' => new external_value(PARAM_INT, 'Content modified by', VALUE_OPTIONAL, 0),
                            'online' => new external_value(PARAM_INT, 'Content online', VALUE_OPTIONAL, 0),
                            'link_title' => new external_value(PARAM_TEXT, 'Content link title', VALUE_OPTIONAL, ''),
                            'created_by' => new external_value(PARAM_TEXT, 'Content created by', VALUE_OPTIONAL, 0),
                            'reviewer_id' => new external_value(PARAM_INT, 'Content reviewer id', VALUE_OPTIONAL, 0),
                            'abstract' => new external_value(PARAM_RAW, 'Content abstract', VALUE_OPTIONAL, ''),
                            'allow_comments' => new external_value(PARAM_TEXT, 'Content allow comments', VALUE_OPTIONAL, ''),
                            'real_fiktiv' => new external_value(PARAM_TEXT, 'Content real fiktiv', VALUE_OPTIONAL, ''),
                            'reading_time' => new external_value(PARAM_TEXT, 'Content reading time', VALUE_OPTIONAL, 0),
                            'tags' => new external_value(PARAM_TEXT, 'Content tags', VALUE_OPTIONAL, ''),
                            'previewimageurl' => new external_value(PARAM_TEXT, 'Content preview image', VALUE_OPTIONAL, ''),
                            'detail_link' => new external_value(PARAM_TEXT, 'Content detail link', VALUE_OPTIONAL, ''),
                            'is_video' => new external_value(PARAM_BOOL, 'Is video', VALUE_OPTIONAL, false),
                            'is_page' => new external_value(PARAM_BOOL, 'Is page', VALUE_OPTIONAL, false),
                            'is_file' => new external_value(PARAM_BOOL, 'Is file', VALUE_OPTIONAL, false),
                            'is_link' => new external_value(PARAM_BOOL, 'Is link', VALUE_OPTIONAL, false),
                            'is_course' => new external_value(PARAM_BOOL, 'Is course', VALUE_OPTIONAL, false),
                            'is_creator' => new external_value(PARAM_BOOL, 'Is creator', VALUE_OPTIONAL, false),
                            'viewcount' => new external_value(PARAM_INT, 'Content viewcount', VALUE_OPTIONAL, 0),
                            'rating' => new external_single_structure(
                                array(
                                    'average' => new external_value(PARAM_FLOAT, 'Average rating'),
                                    'itemid' => new external_value(PARAM_INT, 'Item ID of rating'),
                                    'stars' => new external_multiple_structure(
                                        new external_single_structure(
                                            array(
                                                'class' => new external_value(PARAM_TEXT, 'Star class'),
                                                'data-val' => new external_value(PARAM_INT, 'Data value for star', VALUE_OPTIONAL, 0)
                                            )
                                        )
                                    )
                                )
                            )
                        )
                    )
                ),
                'has_slide' => new external_value(PARAM_BOOL, 'Enable slide or not')
            )
        );
    }

    // Define the function parameters.
    public static function set_rating_parameters() {
        return new external_function_parameters(
            array(
                'itemid' => new external_value(PARAM_INT, 'itemid number', VALUE_DEFAULT, 0),
                'rating' => new external_value(PARAM_INT, 'rating number', VALUE_DEFAULT, 0),
            )
        );
    }

    public static function set_rating($itemid = 0, $rating = 0) {
        global $DB, $CFG, $USER;

        $params = self::validate_parameters(self::set_rating_parameters(), array(
            'itemid' => $itemid,
            'rating' => $rating
        ));

        if (!$itemid || !$rating) {
            echo json_encode(['error' => 'Item not found.']);
            exit;
        }

        $cur_user_reaction = $DB->get_record('local_playlist_item_ratings', ['itemid' => $itemid, 'userid' => $USER->id]);

        if ($cur_user_reaction) {
            $cur_user_reaction->rating = $rating;
            $cur_user_reaction->time_modified = time();
            
            $DB->update_record('local_playlist_item_ratings', $cur_user_reaction);
        } else {
            $reaction = new StdClass;
            $reaction->itemid = $itemid;
            $reaction->rating = $rating;
            $reaction->userid = $USER->id;
            $reaction->time_created = time();
            $reaction->time_modified = 0;
            $DB->insert_record('local_playlist_item_ratings', $reaction);
        }

        $rating_data = local_playlist_calculate_rating($itemid);

        return array(
            'rating' => array(
                'average' => $rating_data['average'],
                'itemid' => $itemid,
                'stars' => $rating_data['stars']
            )
        );
    }

    public static function set_rating_returns() {
        return new external_single_structure(
            array(
                'rating' => new external_single_structure(
                    array(
                        'average' => new external_value(PARAM_FLOAT, 'Average rating'),
                        'itemid' => new external_value(PARAM_INT, 'Item ID of rating'),
                        'stars' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'class' => new external_value(PARAM_TEXT, 'Star class'),
                                    'data-val' => new external_value(PARAM_INT, 'Data value for star', VALUE_OPTIONAL, 0)
                                )
                            )
                        )
                    )
                )
            )
        );
    }

    // Define the function parameters.
    public static function set_comment_parameters() {
        return new external_function_parameters(
            array(
                'itemid' => new external_value(PARAM_INT, 'id of item ', VALUE_DEFAULT, 0),
                'replyid' => new external_value(PARAM_INT, 'reply id', VALUE_DEFAULT, null),
                'commentid' => new external_value(PARAM_INT, 'comment id ', VALUE_DEFAULT, null),
                'text' => new external_value(PARAM_TEXT, 'comment string', VALUE_DEFAULT, '')
            )
        );
    }

    public static function set_comment($itemid, $replyid, $commentid, $text) {
        global $DB, $CFG, $USER;

        $params = self::validate_parameters(self::set_comment_parameters(), array(
            'itemid' => $itemid,
            'replyid' => $replyid,
            'commentid' => $commentid,
            'text' => $text,
        ));

        if (!$itemid || $text == '') {
            echo json_encode(['error' => 'Item not found or no comment text.']);
            exit;
        }

        $status = 0;

        $cur_user_comments = $DB->get_record('local_playlist_item_comments', ['id' => $commentid]);

        if ($cur_user_comments) {
            $cur_user_comments->text = $text;
            $cur_user_comments->time_modified = time();
            
            $status = $DB->update_record('local_playlist_item_comments', $cur_user_comments);
        } else {
            $comment = new StdClass;
            $comment->itemid = $itemid;
            $comment->text = $text;
            $comment->userid = $USER->id;
            $comment->reply_id = $replyid;
            $comment->time_created = time();
            $comment->time_modified = 0;
            $status = $DB->insert_record('local_playlist_item_comments', $comment);
        }

        $comments = local_playlist_get_comments($itemid);

        return array(
            'comments' => $comments
        );
    }

    public static function set_comment_returns() {
        return new external_single_structure(
            array(
                'comments' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Comment ID'),
                            'itemid' => new external_value(PARAM_INT, 'Item ID'),
                            'userid' => new external_value(PARAM_INT, 'User ID'),
                            'user_fullname' => new external_value(PARAM_TEXT, 'Full name of user'),
                            'user_picture' => new external_value(PARAM_RAW, 'User picture'),
                            'text' => new external_value(PARAM_TEXT, 'Comment text'),
                            'time_created' => new external_value(PARAM_TEXT, 'Time created'),
                            'time_modified' => new external_value(PARAM_TEXT, 'Time modified'),
                            'canedit' => new external_value(PARAM_BOOL, 'Can edit'),
                            'reactions' => new external_single_structure(
                                array(
                                    'likes' => new external_value(PARAM_INT, 'Number of likes'),
                                    'dislikes' => new external_value(PARAM_INT, 'Number of dislikes'),
                                    'user_reaction' => new external_value(PARAM_INT, 'Current user reaction'),
                                    'is_liked' => new external_value(PARAM_BOOL, 'User liked the comment'),
                                    'is_disliked' => new external_value(PARAM_BOOL, 'User disliked the comment')
                                )
                            ),
                            'replies' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'id' => new external_value(PARAM_INT, 'Reply ID'),
                                        'itemid' => new external_value(PARAM_INT, 'Item ID'),
                                        'userid' => new external_value(PARAM_INT, 'User ID'),
                                        'user_fullname' => new external_value(PARAM_TEXT, 'Full name of user'),
                                        'user_picture' => new external_value(PARAM_RAW, 'User picture'),
                                        'text' => new external_value(PARAM_TEXT, 'Reply text'),
                                        'time_created' => new external_value(PARAM_TEXT, 'Time created'),
                                        'time_modified' => new external_value(PARAM_TEXT, 'Time modified'),
                                        'canedit' => new external_value(PARAM_BOOL, 'Can edit'),
                                        'reactions' => new external_single_structure(
                                            array(
                                                'likes' => new external_value(PARAM_INT, 'Number of likes'),
                                                'dislikes' => new external_value(PARAM_INT, 'Number of dislikes'),
                                                'user_reaction' => new external_value(PARAM_INT, 'Current user reaction'),
                                                'is_liked' => new external_value(PARAM_BOOL, 'User liked the comment'),
                                                'is_disliked' => new external_value(PARAM_BOOL, 'User disliked the comment')
                                            )
                                        )
                                    )
                                ),
                                'Replies'
                            )
                        )
                    )
                )
            )
        );
    }

    // Define the function parameters.
    public static function set_reaction_parameters() {
        return new external_function_parameters(
            array(
                'commentid' => new external_value(PARAM_INT, 'commentid id ', VALUE_DEFAULT, 0),
                'reaction' => new external_value(PARAM_INT, 'reaction of item ', VALUE_DEFAULT, 0)
            )
        );
    }

    public static function set_reaction($commentid, $reaction) {
        global $DB, $USER;
    
        $params = self::validate_parameters(self::set_reaction_parameters(), array(
            'commentid' => $commentid,
            'reaction' => $reaction,
        ));
    
        if (!$commentid) {
            return array('error' => 'Item not found.');
        }
    
        // Get the current user's reaction to the comment
        $cur_user_reaction = $DB->get_record('local_playlist_item_reactions', ['commentid' => $commentid, 'userid' => $USER->id]);
    
        if ($cur_user_reaction) {
            $cur_user_reaction->reaction = $reaction;
            $cur_user_reaction->time_modified = time();
            $DB->update_record('local_playlist_item_reactions', $cur_user_reaction);
        } else {
            $new_reaction = new StdClass;
            $new_reaction->commentid = $commentid;
            $new_reaction->reaction = $reaction;
            $new_reaction->userid = $USER->id;
            $new_reaction->time_created = time();
            $new_reaction->time_modified = 0;
            $DB->insert_record('local_playlist_item_reactions', $new_reaction);
        }
    
        // Recalculate the reaction counts
        $likes = $DB->count_records('local_playlist_item_reactions', ['commentid' => $commentid, 'reaction' => 1]);
        $dislikes = $DB->count_records('local_playlist_item_reactions', ['commentid' => $commentid, 'reaction' => -1]);
    
        // Determine the current user's reaction
        $user_reaction = $reaction;
        $is_liked = ($reaction == 1);
        $is_disliked = ($reaction == -1);
    
        return array(
            'id' => $commentid,
            'likes' => $likes,
            'dislikes' => $dislikes,
            'user_reaction' => $user_reaction,
            'is_liked' => $is_liked,
            'is_disliked' => $is_disliked
        );
    }

    public static function set_reaction_returns() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'Comment ID'),
                'likes' => new external_value(PARAM_INT, 'Number of likes'),
                'dislikes' => new external_value(PARAM_INT, 'Number of dislikes'),
                'user_reaction' => new external_value(PARAM_INT, 'Current user reaction'),
                'is_liked' => new external_value(PARAM_BOOL, 'User liked the comment'),
                'is_disliked' => new external_value(PARAM_BOOL, 'User disliked the comment')
            )
        );
    }

    public static function delete_comment_parameters() {
        return new external_function_parameters(
            array(
                'commentid' => new external_value(PARAM_INT, 'The ID of the comment to delete')
            )
        );
    }

    public static function delete_comment($commentid) {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::delete_comment_parameters(), array('commentid' => $commentid));

        // Check if the comment exists.
        if (!$comment = $DB->get_record('local_playlist_item_comments', array('id' => $params['commentid']))) {
            throw new invalid_parameter_exception('Invalid comment ID');
        }

        // Delete replies to the comment.
        $replies = $DB->get_records('local_playlist_item_comments', array('reply_id' => $params['commentid']));
        foreach ($replies as $reply) {
            $DB->delete_records('local_playlist_item_reactions', array('commentid' => $reply->id)); // Delete reactions to replies.
            $DB->delete_records('local_playlist_item_comments', array('id' => $reply->id)); // Delete replies.
        }

        // Delete reactions to the comment.
        $DB->delete_records('local_playlist_item_reactions', array('commentid' => $params['commentid']));

        // Delete the comment.
        $DB->delete_records('local_playlist_item_comments', array('id' => $params['commentid']));

        // Retrieve the updated list of comments.
        $comments = local_playlist_get_comments($comment->itemid);

        return array('comments' => $comments);
    }

    public static function delete_comment_returns() {
        return new external_single_structure(
            array(
                'comments' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Comment ID'),
                            'itemid' => new external_value(PARAM_INT, 'Item ID'),
                            'userid' => new external_value(PARAM_INT, 'User ID'),
                            'user_fullname' => new external_value(PARAM_TEXT, 'Full name of user'),
                            'user_picture' => new external_value(PARAM_RAW, 'User picture'),
                            'text' => new external_value(PARAM_TEXT, 'Comment text'),
                            'time_created' => new external_value(PARAM_TEXT, 'Time created'),
                            'time_modified' => new external_value(PARAM_TEXT, 'Time modified'),
                            'canedit' => new external_value(PARAM_BOOL, 'Can edit'),
                            'reactions' => new external_single_structure(
                                array(
                                    'likes' => new external_value(PARAM_INT, 'Number of likes'),
                                    'dislikes' => new external_value(PARAM_INT, 'Number of dislikes'),
                                    'user_reaction' => new external_value(PARAM_INT, 'Current user reaction'),
                                    'is_liked' => new external_value(PARAM_BOOL, 'User liked the comment'),
                                    'is_disliked' => new external_value(PARAM_BOOL, 'User disliked the comment')
                                )
                            ),
                            'replies' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'id' => new external_value(PARAM_INT, 'Reply ID'),
                                        'itemid' => new external_value(PARAM_INT, 'Item ID'),
                                        'userid' => new external_value(PARAM_INT, 'User ID'),
                                        'user_fullname' => new external_value(PARAM_TEXT, 'Full name of user'),
                                        'user_picture' => new external_value(PARAM_RAW, 'User picture'),
                                        'text' => new external_value(PARAM_TEXT, 'Reply text'),
                                        'time_created' => new external_value(PARAM_TEXT, 'Time created'),
                                        'time_modified' => new external_value(PARAM_TEXT, 'Time modified'),
                                        'canedit' => new external_value(PARAM_BOOL, 'Can edit'),
                                        'reactions' => new external_single_structure(
                                            array(
                                                'likes' => new external_value(PARAM_INT, 'Number of likes'),
                                                'dislikes' => new external_value(PARAM_INT, 'Number of dislikes'),
                                                'user_reaction' => new external_value(PARAM_INT, 'Current user reaction'),
                                                'is_liked' => new external_value(PARAM_BOOL, 'User liked the comment'),
                                                'is_disliked' => new external_value(PARAM_BOOL, 'User disliked the comment')
                                            )
                                        )
                                    )
                                ),
                                'Replies'
                            )
                        )
                    )
                )
            )
        );
    }

}  