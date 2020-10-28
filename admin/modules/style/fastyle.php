<?php

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

$lang->load('style_themes');
$lang->load('style_templates');

$page->add_breadcrumb_item($lang->fastyle, "index.php?module=style-fastyle");

$tid = $mybb->get_input('tid', MyBB::INPUT_INT);

$templateSets = [];
$templateSets[-1]['title'] = $lang->global_templates;
$templateSets[-1]['sid'] = -1;

$themes = cache_themes();

// Get template sets
$query = $db->simple_select("templatesets", "*", "", ['order_by' => 'title', 'order_dir' => 'ASC']);
while ($template_set = $db->fetch_array($query)) {
    $templateSets[$template_set['sid']] = $template_set;
}

// Restrucure the theme array to something we can "loop-de-loop" with
foreach ($themes as $key => $theme) {

    if ($key == "default") {
        continue;
    }

    $theme_cache[$theme['pid']][$theme['tid']] = $theme;

}

$theme_cache['num_themes'] = count($themes);
unset($themes);

$theme = $theme_cache[$tid];

$sid = (int) $mybb->input['sid'];

// API endpoint
if (isset($mybb->input['api'])) {

    $title = $db->escape_string($mybb->get_input('title'));

    switch (get_extension($mybb->get_input('title'))) {

        case 'js':
        $mode = 'javascripts';
        break;

        case 'css':
        $mode = 'stylesheets';
        break;

        default:
        $mode = 'templates';

    }

    // Get asset
    if ($mybb->input['action'] == 'get') {

        if ($mode == 'templates') {

            $query = $db->simple_select('templates', 'template, tid, dateline',
            'title = \'' . $title . '\' AND (sid = -2 OR sid = ' . $sid . ')',
            ['order_by' => 'sid', 'order_dir' => 'desc', 'limit' => 1]);
            $template = $db->fetch_array($query);

            $content = $template['template'];
            $id = $template['tid'];
            $dateline = $template['dateline'];

        }
        else if ($mode == 'javascripts') {

            $path = MYBB_ROOT . 'jscripts/' . $mybb->get_input('title');

            if (!is_readable($path)) {
                fastyle_message($lang->fastyle_error_could_not_fetch_file, 'error');
            }

            $content = file_get_contents($path);
            $id = true;
            $dateline = filemtime($path);

        }
        else {

            $parent_list = make_parent_theme_list($theme['tid']);
            $parent_list = implode(',', $parent_list);
            if (!$parent_list) {
                $parent_list = 1;
            }

            $query = $db->simple_select("themestylesheets", "*",
            "name='" . $title . "' AND tid IN ({$parent_list})",
            ['order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1]);
            $stylesheet = $db->fetch_array($query);

            $content = $stylesheet['stylesheet'];
            $id = $stylesheet['sid'];
            $dateline = $stylesheet['lastmodified'];

        }

        if ($id) {

            $data = ($dateline) ? ['content' => $content, 'dateline' => $dateline] : ['content' => $content];
            fastyle_message($data);

        }
        else {
            fastyle_message($lang->fastyle_error_resource_not_found, 'error');
        }

    }

    // Revert asset
    if ($mybb->input['action'] == 'revert') {

        if ($mode == 'templates') {

            $query = $db->query("
            SELECT t.*, s.title as set_title
            FROM " . TABLE_PREFIX . "templates t
            LEFT JOIN " . TABLE_PREFIX . "templatesets s ON(s.sid=t.sid)
            WHERE t.title='" . $title . "' AND t.sid > 0 AND t.sid = '" . $sid . "'
            ");
            $template = $db->fetch_array($query);

            // Does the template not exist?
            if (!$template) {
                fastyle_message($lang->error_invalid_template, 'error');
            }

            // Revert the template
            $db->delete_query("templates", "tid='{$template['tid']}'");

            // Log admin action
            log_admin_action($template['tid'], $template['title'], $template['sid'], $template['set_title']);

            // Get the master template id
            $query = $db->simple_select('templates', 'tid,template', "title = '" . $title . "' AND sid = -2");
            $template = $db->fetch_array($query);

            fastyle_message(['message' => $lang->success_template_reverted, 'tid' => $template['tid'], 'content' => $template['template']]);

        }
        else if ($mode == 'javascripts') {

            $mybb->input['content'] = \fetch_remote_file('https://github.com/mybb/mybb/raw/' . urlencode('mybb_' . $mybb->version_code) . DIRECTORY_SEPARATOR . urlencode('jscripts/' . $mybb->get_input('title')));

            if ($mybb->input['content'] != 'Not Found') {

                $mybb->input['action'] = 'edit_javascript';
                $revert = 1;

            }
            else {
                fastyle_message($lang->fastyle_error_resource_not_found);
            }

        }

    }

    // Edit script
    if ($mybb->get_input('action') == 'edit_javascript' && $mode == 'javascripts') {

        $content = $mybb->get_input('content');

        // Remove special characters
        $title = preg_replace('#([^a-zA-Z0-9-_\.\/]+)#i', '', $mybb->get_input('title'));
        if (!$title or $title == ".css") {
            fastyle_message($lang->fastyle_error_characters_not_allowed, 'error');
        }

        $folder = MYBB_ROOT . 'jscripts';

        if (is_writable($folder)) {

            if (file_put_contents($folder . DIRECTORY_SEPARATOR . $title, $content) === false) {
                fastyle_message($lang->fastyle_error_could_not_write_to_file, 'error');
            }

        }
        else {
            fastyle_message($lang->fastyle_error_folder_not_writable, 'error');
        }

        $data = [
            'message' => $lang->sprintf($lang->fastyle_success_updated, $title)
        ];

        if ($revert) {
            $data['content'] = $content;
        }

        fastyle_message($data);

    }

    // Delete asset
    if ($mybb->input['action'] == 'delete') {

        if ($mode == 'stylesheets') {

            if (!$theme['tid']) {
                fastyle_message($lang->error_invalid_theme, 'error');
            }

            $parent_list = make_parent_theme_list($theme['tid']);
            $parent_list = implode(',', $parent_list);
            if (!$parent_list) {
                $parent_list = 1;
            }

            $query = $db->simple_select("themestylesheets", "*", "name='" . $title . "' AND tid IN ({$parent_list})", ['order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1]);
            $stylesheet = $db->fetch_array($query);

            // Does the theme not exist? or are we trying to delete the master?
            if (!$stylesheet['sid'] or $stylesheet['tid'] == 1) {
                fastyle_message($lang->error_invalid_stylesheet, 'error');
            }

            $db->delete_query("themestylesheets", "sid='{$stylesheet['sid']}'", 1);
            @unlink(MYBB_ROOT."cache/themes/theme{$theme['tid']}/{$stylesheet['cachefile']}");

            $filename_min = str_replace('.css', '.min.css', $stylesheet['cachefile']);
            @unlink(MYBB_ROOT."cache/themes/theme{$theme['tid']}/{$filename_min}");

            // Update the CSS file list for this theme
            update_theme_stylesheet_list($theme['tid'], $theme, true);

            // Log admin action
            log_admin_action($stylesheet['sid'], $stylesheet['name'], $theme['tid'], htmlspecialchars_uni($theme['name']));

            fastyle_message($lang->success_stylesheet_deleted);

        }
        else if ($mode == 'javascripts') {

            $folder = MYBB_ROOT . 'jscripts';

            if (is_writable($folder)) {

                $filename = $folder . DIRECTORY_SEPARATOR . $mybb->get_input('title');
                $path = dirname($filename);

                @unlink($filename);

                // Remove the parent directory if it turns out to be empty
                if (is_readable($path)) {

                    if (count(scandir($path)) == 2) {
                        @rmdir($path);
                    }

                }

            }
            else {
                fastyle_message($lang->fastyle_error_dir_not_writable, 'error');
            }

            fastyle_message($lang->sprintf($lang->fastyle_success_deleted, $mybb->get_input('title')));

        }

        $query = $db->query("
        SELECT t.*, s.title as set_title
        FROM " . TABLE_PREFIX . "templates t
        LEFT JOIN " . TABLE_PREFIX . "templatesets s ON(t.sid=s.sid)
        WHERE t.title='" . $title . "' AND t.sid > '-2' AND t.sid = '{$sid}'
        ");
        $template = $db->fetch_array($query);

        // Does the template not exist?
        if (!$template) {
            fastyle_message($lang->error_invalid_template, 'error');
        }

        // Delete the template
        $db->delete_query("templates", "tid='{$template['tid']}'");

        // Log admin action
        log_admin_action($template['tid'], $template['title'], $template['sid'], $template['set_title']);

        fastyle_message($lang->sprintf($lang->fastyle_success_deleted, $mybb->get_input('title')));

    }

    // Delete template group
    /*
    if ($mybb->input['action'] == 'deletegroup') {

    $gid = $mybb->get_input('gid', MyBB::INPUT_INT);
    $query = $db->simple_select("templategroups", "*", "gid='{$gid}'");

    if (!$db->num_rows($query)) {
    fastyle_message($lang->error_missing_template_group, 'error');
}

$template_group = $db->fetch_array($query);

// Delete the group
$db->delete_query("templategroups", "gid = '{$template_group['gid']}'");

// Log admin action
log_admin_action($template_group['gid'], htmlspecialchars_uni($template_group['title']));

fastyle_message($lang->success_template_group_deleted);

}
*/

    // Diff mode
    if ($mybb->input['action'] == 'diff') {

        if ($mode == 'templates') {

            $query = $db->simple_select("templates", "template", "title='".$title."' AND sid='-2'");
            $content = $db->fetch_field($query, 'template');

        }
        else if ($mode == 'javascripts') {
            $content = \fetch_remote_file('https://github.com/mybb/mybb/raw/' . urlencode('mybb_' . $mybb->version_code) . DIRECTORY_SEPARATOR . urlencode('jscripts/' . $mybb->get_input('title')));
        }
        else {

            $query = $db->simple_select('themestylesheets', 'stylesheet', "name='".$title."' AND tid='1'");
            $content = $db->fetch_field($query, 'stylesheet');

        }

        if (!$content) {
            fastyle_message($lang->fastyle_error_resource_not_found, 'error');
        }

        fastyle_message(['content' => $content]);

    }

    // Add template group
    /*
    if ($mybb->input['action'] == 'addgroup') {

    if (!trim($mybb->get_input('title'))) {
    fastyle_message($lang->error_missing_set_title, 'error');
    }

    $gid = $db->insert_query("templatesets", ['title' => $db->escape_string($mybb->get_input('title'))]);

    // Log admin action
    log_admin_action($gid, $mybb->get_input('title'));

    fastyle_message($lang->success_template_set_saved);

    }
    */

    // Add asset
    if ($mybb->input['action'] == 'add') {

        // Stylesheet
        if ($mode == 'stylesheets') {

            // Remove special characters
            $title = preg_replace('#([^a-z0-9-_\.]+)#i', '', $mybb->get_input('title'));
            if (!$title or $title == ".css") {
                fastyle_message($lang->error_missing_stylesheet_name, 'error');
            }

            // Get 30 chars only because we don't want more than that
            $title = my_substr($title, 0, 30);

            // Add Stylesheet
            $insert_array = [
                'name' => $db->escape_string($title),
                'tid' => $tid,
                'attachedto' => '',
                'stylesheet' => $db->escape_string($mybb->input['stylesheet']),
                'cachefile' => $db->escape_string(str_replace('/', '', $title)),
                'lastmodified' => TIME_NOW
            ];

            $sid = $db->insert_query("themestylesheets", $insert_array);

            if (!cache_stylesheet($theme['tid'], str_replace('/', '', $title), $title)) {
                $db->update_query("themestylesheets", ['cachefile' => "css.php?stylesheet={$sid}"], "sid='{$sid}'", 1);
            }

            // Update the CSS file list for this theme
            update_theme_stylesheet_list($theme['tid'], $theme, true);

            // Log admin action
            log_admin_action($sid, $title, $theme['tid'], htmlspecialchars_uni($theme['name']));

            fastyle_message($lang->sprintf($lang->fastyle_success_saved, $title));

        }
        // JavaScript
        else if ($mode == 'javascripts') {

            // Remove special characters
            $title = preg_replace('#([^a-zA-Z0-9-_\.\/]+)#i', '', $mybb->get_input('title'));
            if (!$title or $title == ".css") {
                fastyle_message('The script title contains invalid characters. You can only use letters, numbers and underscore.', 'error');
            }

            $folder = MYBB_ROOT . 'jscripts';

            if (is_writable($folder)) {

                $filename = $folder . DIRECTORY_SEPARATOR . $title;

                if (file_exists($filename)) {
                    fastyle_message($title . ' already exists', 'error');
                }

                $path = dirname($filename);

                if (!is_dir($path)) {
                    @mkdir($path);
                }

                if (file_put_contents($filename, '', FILE_APPEND) === false) {
                    fastyle_message($lang->fastyle_error_could_not_create_file, 'error');
                }

            }
            else {
                fastyle_message($lang->fastyle_error_dir_not_writable, 'error');
            }

            fastyle_message($lang->sprintf($lang->fastyle_success_saved, $title));

        }

        // Template
        if (empty($mybb->get_input('title'))) {
            $errors[] = $lang->error_missing_set_title;
        }
        else {

            $query = $db->simple_select("templates", "COUNT(tid) as count", "title='" . $title . "' AND (sid = '-2' OR sid = '{$sid}')");

            if ($db->fetch_field($query, "count") > 0) {
                $errors[] = $lang->error_already_exists;
            }

        }

        if (!isset($templateSets[$sid])) {
            $errors[] = $lang->error_invalid_set;
        }

        // Are we trying to do malicious things in our template?
        if (check_template($mybb->input['template'])) {
            $errors[] = $lang->error_security_problem;
        }

        if ($errors) {
            fastyle_message(implode("\n", $errors), 'error');
        }

        $templateArray = [
            'title' => $title,
            'sid' => $sid,
            'template' => $db->escape_string(rtrim($mybb->input['template'])),
            'version' => $db->escape_string($mybb->version_code),
            'status' => '',
            'dateline' => TIME_NOW
        ];

        $tid = $db->insert_query("templates", $templateArray);

        // Log admin action
        log_admin_action($tid, $mybb->get_input('title'), $sid, $templateSets[$sid]);

        $data = [
            'message' => $lang->sprintf($lang->fastyle_success_template_saved, $templateArray['title']),
            'tid' => $tid
        ];

        fastyle_message($data);

    }

    if ($mybb->input['action'] == 'saveorder') {

        if (!is_array($mybb->input['disporder'])) {
            fastyle_message($lang->error_no_display_order, 'error');
        }

        $file_stylesheets = $theme['stylesheets'];

        $stylesheets = [];
        $inherited_load = [];

        foreach ($file_stylesheets as $file => $action_stylesheet) {

            if ($file == 'inherited' or !is_array($action_stylesheet)) {
                continue;
            }

            foreach ($action_stylesheet as $action => $style) {

                foreach ($style as $stylesheet) {

                    $stylesheets[$stylesheet]['applied_to'][$file][] = $action;

                    if (is_array($file_stylesheets['inherited'][$file."_".$action]) and in_array($stylesheet, array_keys($file_stylesheets['inherited'][$file."_".$action]))) {

                        $stylesheets[$stylesheet]['inherited'] = $file_stylesheets['inherited'][$file."_".$action];

                        foreach ($file_stylesheets['inherited'][$file."_".$action] as $value) {
                            $inherited_load[] = $value;
                        }

                    }

                }

            }

        }

        $inherited_load[] = $tid;
        $inherited_load = array_unique($inherited_load);

        $inherited_themes = [];
        $theme_stylesheets = [];

        if (count($inherited_load) > 0) {

            $query = $db->simple_select("themes", "tid, name", "tid IN (".implode(",", $inherited_load).")");

            while ($inherited_theme = $db->fetch_array($query)) {
                $inherited_themes[$inherited_theme['tid']] = $inherited_theme['name'];
            }

            $query = $db->simple_select("themestylesheets", "*", "", ['order_by' => 'sid DESC, tid', 'order_dir' => 'desc']);
            while ($theme_stylesheet = $db->fetch_array($query)) {

                if (!isset($theme_stylesheets[$theme_stylesheet['name']]) && in_array($theme_stylesheet['tid'], $inherited_load)) {
                    $theme_stylesheets[$theme_stylesheet['name']] = $theme_stylesheet;
                }

                $theme_stylesheets[$theme_stylesheet['sid']] = $theme_stylesheet['name'];

            }

        }

        $mybb->input['disporder'] = array_flip($mybb->input['disporder']);

        $orders = [];

        foreach ($theme_stylesheets as $stylesheet => $properties) {

            if (is_array($properties)) {

                $order = (int) $mybb->input['disporder'][$properties['sid']];

                $orders[$properties['name']] = $order;

            }

        }

        asort($orders, SORT_NUMERIC);

        // Save the orders in the theme properties
        $properties = (array) $theme['properties'];
        $properties['disporder'] = $orders;

        $update_array = [
            "properties" => $db->escape_string(my_serialize($properties))
        ];

        $db->update_query("themes", $update_array, "tid = '{$theme['tid']}'");

        if ($theme['def'] == 1) {
            $cache->update_default_theme();
        }

        unset($theme_cache);

        // Normalize for consistency
        update_theme_stylesheet_list($theme['tid'], false, true);

        fastyle_message($lang->fastyle_success_order_saved);

    }

}
