<?php

/**
 * An all-in-one utility to improve and speed up stylesheets, settings and templates management.
 *
 * @package FASTyle
 * @author  Shade <shad3-@outlook.com>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 2.3
 */

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined("PLUGINLIBRARY")) {
	define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

function fastyle_info()
{
    fastyle_plugin_edit();

    if (fastyle_is_installed()) {

        global $PL, $mybb;

        $PL or require_once PLUGINLIBRARY;

        if (fastyle_apply_core_edits() !== true) {
            $apply = $PL->url_append('index.php',
                [
                    'module' => 'config-plugins',
                    'fastyle' => 'apply',
                    'my_post_key' => $mybb->post_code,
                ]
            );
            $description = "<br><br>Core edits missing. <a href='{$apply}'>Apply core edits.</a>";
        }
        else {
            $revert = $PL->url_append('index.php',
                [
                    'module' => 'config-plugins',
                    'fastyle' => 'revert',
                    'my_post_key' => $mybb->post_code,
                ]
            );
            $description = "<br><br>Core edits in place. <a href='{$revert}'>Revert core edits.</a>";
        }

    }

	return [
		'name' => 'FASTyle',
		'description' => 'An all-in-one utility to improve and speed up stylesheets, settings and templates management.' . $description,
		'author' => 'Shade',
		'website' => 'https://www.mybboost.com/forum-fastyle',
		'version' => '2.3',
		'codename' => 'fastyle',
		'compatibility' => '18*'
	];
}

function fastyle_is_installed()
{
	global $cache;

	$installed = $cache->read("shade_plugins");
	if ($installed['FASTyle']) {
		return true;
	}
}

function fastyle_install()
{
	global $cache, $mybb;

    fastyle_apply_core_edits(true);

	// Create cache
	$info                         = fastyle_info();
	$shade_plugins                = $cache->read('shade_plugins');
	$shade_plugins[$info['name']] = [
		'title' => $info['name'],
		'version' => $info['version']
	];

	$cache->update('shade_plugins', $shade_plugins);
}

function fastyle_uninstall()
{
	global $cache;

    fastyle_revert_core_edits(true);

	// Delete the plugin from cache
	$info         = fastyle_info();
	$shade_plugins = $cache->read('shade_plugins');
	unset($shade_plugins[$info['name']]);
	$cache->update('shade_plugins', $shade_plugins);
}

function fastyle_plugin_edit()
{
    global $mybb;

    if ($mybb->input['my_post_key'] == $mybb->post_code) {

        if ($mybb->input['fastyle'] == 'apply') {
            if (fastyle_apply_core_edits(true) === true) {
                flash_message('Successfully applied core edits.', 'success');
                admin_redirect('index.php?module=config-plugins');
            }
            else {
                flash_message('There was an error applying core edits.', 'error');
                admin_redirect('index.php?module=config-plugins');
            }

        }

        if ($mybb->input['fastyle'] == 'revert') {

            if (fastyle_revert_core_edits(true) === true) {
                flash_message('Successfully reverted core edits.', 'success');
                admin_redirect('index.php?module=config-plugins');
            }
            else {
                flash_message('There was an error reverting core edits.', 'error');
                admin_redirect('index.php?module=config-plugins');
            }

        }

    }
}

function fastyle_apply_core_edits($apply = false)
{
    global $PL, $mybb;

    $PL or require_once PLUGINLIBRARY;

    $errors = [];

    $edits = [
        [
            'search' => '$page->output_nav_tabs($sub_tabs, \'edit_stylesheets\');',
            'after' => [
                '$plugins->run_hooks("fastyle_themes_hijack");',
                'if (false) {',
            ]
        ],
        [
            'search' => '// Theme Properties table',
            'before' => [
                '}'
            ]
        ],
    ];

    $result = $PL->edit_core('fastyle', $mybb->config['admin_dir'] . '/modules/style/themes.php', $edits, $apply);

    if ($result !== true) {
        $errors[] = $result;
    }

    if (count($errors) >= 1) {
        return $errors;
    }
    else {
        return true;
    }
}

function fastyle_revert_core_edits($apply = false)
{
    global $PL, $mybb;

    $PL or require_once PLUGINLIBRARY;

    return $PL->edit_core('fastyle', $mybb->config['admin_dir'] . '/modules/style/themes.php', [], $apply);
}

// Hooks
if (defined('IN_ADMINCP')) {

	$plugins->add_hook("admin_load", "fastyle_ad");
	$plugins->add_hook("admin_style_templates_edit_template", "fastyle_templates_edit");
	$plugins->add_hook("admin_style_templates_edit_template_commit", "fastyle_templates_edit_commit");
	$plugins->add_hook("admin_style_themes_edit_stylesheet_advanced_commit", "fastyle_themes_edit_advanced_commit");
	$plugins->add_hook("admin_config_settings_change", "fastyle_admin_config_settings_change", 1000);
	$plugins->add_hook("admin_config_settings_change_commit", "fastyle_admin_config_settings_change_commit");

	// Custom module
	$plugins->add_hook("admin_style_action_handler", "fastyle_admin_style_action_handler");

    // Main thing
    $plugins->add_hook("admin_style_themes_edit", "fastyle_load_header");
    $plugins->add_hook("admin_style_templates_set", "fastyle_load_header");
    $plugins->add_hook("admin_style_templates_set", "fastyle_themes_hijack_function");
    $plugins->add_hook("fastyle_themes_hijack", "fastyle_themes_hijack_function");

}

// Advertising
function fastyle_ad()
{
	global $cache, $mybb;

	$plugins = $cache->read('shade_plugins');
	if (!in_array($mybb->user['uid'], (array) $plugins['FASTyle']['ad_shown'])) {

		flash_message('Thank you for using FASTyle! You might also be interested in other great plugins on <a href="https://www.mybboost.com">MyBBoost</a>, where you can also get support for FASTyle itself.<br /><small>This message will not be shown again to you.</small>', 'success');

		$plugins['FASTyle']['ad_shown'][] = $mybb->user['uid'];
		$cache->update('shade_plugins', $plugins);

	}

}

function fastyle_load_header()
{
    // Force CodeMirror for everyone, although it should also work without it
    return $GLOBALS['page']->extra_header .= '
<script type="text/javascript" src="./jscripts/codemirror/lib/codemirror.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/mode/xml/xml.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/mode/javascript/javascript.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/mode/css/css.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/mode/htmlmixed/htmlmixed.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/addon/search/searchcursor.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/addon/fold/foldcode.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/addon/fold/xml-fold.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/addon/fold/foldgutter.js"></script>
<script type="text/javascript" src="./jscripts/FASTyle/codemirror/dialog.js"></script>
<script type="text/javascript" src="./jscripts/FASTyle/codemirror/mark-selection.js"></script>
<script type="text/javascript" src="./jscripts/FASTyle/codemirror/search.js"></script>
<script type="text/javascript" src="./jscripts/FASTyle/codemirror/comment.js"></script>
<script type="text/javascript" src="./jscripts/FASTyle/codemirror/sublime.js"></script>
<script type="text/javascript" src="./jscripts/FASTyle/codemirror/closetag.js"></script>
<script type="text/javascript" src="./jscripts/FASTyle/codemirror/closebrackets.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/diff_match_patch/20121119/diff_match_patch.js"></script>
<script type="text/javascript" src="./jscripts/FASTyle/codemirror/merge.js"></script>
<link rel="stylesheet" type="text/css" href="./jscripts/codemirror/lib/codemirror.css" />
<link rel="stylesheet" type="text/css" href="./jscripts/codemirror/addon/fold/foldgutter.css" />
<link rel="stylesheet" type="text/css" href="./jscripts/FASTyle/codemirror/dialog.css" />
<link rel="stylesheet" type="text/css" href="./jscripts/FASTyle/codemirror/merge.css" />
<link rel="stylesheet" type="text/css" href="./jscripts/FASTyle/codemirror/material.css" />
<script type="text/javascript" src="./jscripts/FASTyle/tipsy/tipsy.js"></script>
<script type="text/javascript" src="./jscripts/FASTyle/sortable/jquery.fn.sortable.js"></script>
<script type="text/javascript" src="./jscripts/FASTyle/swiper/js/swiper.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/simplebar@latest/dist/simplebar.js"></script>
<link rel="stylesheet" type="text/css" href="./jscripts/FASTyle/editor.css" />
<link rel="stylesheet" type="text/css" href="./jscripts/FASTyle/tipsy/tipsy.css" />
<link rel="stylesheet" type="text/css" href="./jscripts/FASTyle/swiper/css/swiper.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simplebar@latest/dist/simplebar.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Code+Pro" />';
}

function fastyle_themes_hijack_function()
{
    global $page, $form, $mybb, $db, $theme_cache, $theme, $lang, $originalList, $resourcelist;

    if (!$theme and $mybb->input['sid']) {

        // From templates. Work out if there's an associated theme and redirect
        $sets = [];

        $query = $db->simple_select("themes", "properties, tid");
        while ($t = $db->fetch_array($query)) {
            $prop = my_unserialize($t['properties']);
            $sets[$prop['templateset']] = $t['tid'];
        }

        if ($sets[$mybb->input['sid']]) {
            header('Location: index.php?module=style-themes&action=edit&tid=' . (int) $sets[$mybb->input['sid']]);
            exit;
        }

    }

    $tid = (int) $theme['tid'];
    $theme['properties'] = my_unserialize($theme['properties']);

    $lang->load('style_templates');
    $lang->load('fastyle');

    if ($theme['properties']['templateset']) {
        $sid = (int) $theme['properties']['templateset'];
    }
    else if ($mybb->input['sid']) {
        $sid = (int) $mybb->input['sid'];
    }

    // Get a list of templates
    $query = $db->simple_select("templategroups", "*");

    $template_groups = [];
    while ($templategroup = $db->fetch_array($query)) {

        $templategroup['title'] = $lang->sprintf($lang->templates, htmlspecialchars_uni($lang->parse($templategroup['title'])));

        $template_groups[$templategroup['prefix']] = $templategroup;

    }

    function sort_template_groups($a, $b) {
        return strcasecmp($a['title'], $b['title']);
    }
    uasort($template_groups, "sort_template_groups");

    // Add the ungrouped templates group at the bottom
    $template_groups['-1'] = [
        "prefix" => "",
        "title" => $lang->ungrouped_templates,
        "gid" => -1
    ];

    // Set the template group keys to lowercase for case insensitive comparison.
    $template_groups = array_change_key_case($template_groups, CASE_LOWER);

    $where = ($sid == -1) ? "sid='{$sid}'" : "sid='{$sid}' OR sid = '-2'";

    // Load the list of templates
    $query = $db->simple_select("templates", "title,sid,tid,template", $where, ['order_by' => 'sid DESC, title', 'order_dir' => 'ASC']);
    while ($template = $db->fetch_array($query)) {

        $exploded = explode("_", $template['title'], 2);

        // Set the prefix to lowercase for case insensitive comparison.
        $exploded[0] = strtolower($exploded[0]);

        if (isset($template_groups[$exploded[0]])) {
            $group = $exploded[0];
        }
        else {
            $group = -1;
        }

        $template['gid'] = -1;
        if (isset($template_groups[$exploded[0]]['gid'])) {
            $template['gid'] = $template_groups[$exploded[0]]['gid'];
        }

        // If this template is not a master template, we simply add it to the list
        if ($template['sid'] != -2) {

            $template['original'] = false;
            $template['modified'] = false;
            $template_groups[$group]['templates'][$template['title']] = $template;

        }
        // Otherwise, if we are down to master templates we need to do a few extra things
        else {

            // Master template
            if (!isset($template_groups[$group]['templates'][$template['title']])) {

                $template['original'] = true;
                $template_groups[$group]['templates'][$template['title']] = $template;

            }
            // Template that hasn't been customised in the set we have expanded
            else if ($template_groups[$group]['templates'][$template['title']]['template'] == $template['template']) {
                $template_groups[$group]['templates'][$template['title']]['original'] = true;
            }
            // Template has been modified in the set we have expanded (it doesn't match the master)
            else if ($template_groups[$group]['templates'][$template['title']]['template'] != $template['template'] and $template_groups[$group]['templates'][$template['title']]['sid'] != -2) {
                $template_groups[$group]['templates'][$template['title']]['modified'] = true;
            }

            // Save some memory!
            unset($template_groups[$group]['templates'][$template['title']]['template']);

        }

    }

    $resourcelist = '<ul>';

    // Stylesheets
    if ($tid) {

        $file_stylesheets = my_unserialize($theme['stylesheets']);

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

        // Order stylesheets
        $ordered_stylesheets = [];

        foreach ($theme['properties']['disporder'] as $style_name => $order) {

            foreach ($stylesheets as $filename => $style) {

                if (strpos($filename, 'css.php?stylesheet=') !== false) {

                    $style['sid'] = (int) str_replace('css.php?stylesheet=', '', $filename);
                    $filename = $theme_stylesheets[$style['sid']];

                }

                if (basename($filename) != $style_name) {
                    continue;
                }

                $ordered_stylesheets[$filename] = $style;

            }

        }

        $resourcelist .= '<li class="header icon">Stylesheets</li>';
        $resourcelist .= '<ul data-prefix="stylesheets">';

        foreach ($ordered_stylesheets as $filename => $style) {

            $modified = '';

            if (strpos($filename, 'css.php?stylesheet=') !== false) {

                $style['sid'] = (int) str_replace('css.php?stylesheet=', '', $filename);
                $filename = $theme_stylesheets[$style['sid']];

            }
            else {

                $filename = basename($filename);
                $style['sid'] = $theme_stylesheets[$filename]['sid'];

            }

            $filename = htmlspecialchars_uni($theme_stylesheets[$filename]['name']);

            $inherited = "";
            $inherited_ary = [];

            if (is_array($style['inherited'])) {

                foreach ($style['inherited'] as $_tid) {

                    if ($inherited_themes[$_tid]) {
                        $inherited_ary[$_tid] = $inherited_themes[$_tid];
                    }

                }

            }

            if (!empty($inherited_ary)) {

                $inherited = " <i class='fas fa-exclamation-triangle' title='{$lang->inherited_from}";
                $sep = " ";
                $inherited_count = count($inherited_ary);
                $count = 0;

                foreach ($inherited_ary as $_tid => $file) {

                    if (isset($applied_to_count) && $count == $applied_to_count && $count != 0) {
                        $sep = " {$lang->and} ";
                    }

                    $inherited .= $sep.$file;
                    $sep = $lang->comma;

                    ++$count;

                }

                $inherited .= "'></i>";

            }
            else {
                $modified = ' data-status="modified"';
            }

            if (is_array($style['applied_to']) && (!isset($style['applied_to']['global']) || $style['applied_to']['global'][0] != "global")) {

                $attached_to = '';

                $applied_to_count = count($style['applied_to']);
                $count = 0;
                $sep = " ";
                $name = "";

                $colors = [];

                if (!is_array($properties['colors'])) {
                    $properties['colors'] = [];
                }

                foreach ($style['applied_to'] as $name => $actions) {

                    if (!$name) {
                        continue;
                    }

                    if (array_key_exists($name, $properties['colors'])) {
                        $colors[] = $properties['colors'][$name];
                    }

                    // Colors override files and are handled below.
                    if (count($colors)) {
                        continue;
                    }

                    // It's a file:
                    ++$count;

                    if ($actions[0] != "global") {
                        $name = "{$name} ({$lang->actions}: ".implode(',', $actions).")";
                    }

                    if ($count == $applied_to_count && $count > 1) {
                        $sep = " {$lang->and} ";
                    }
                    $attached_to .= $sep.$name;

                    $sep = $lang->comma;

                }

                if ($attached_to) {
                    $attached_to = $lang->attached_to . $attached_to;
                }

                if (count($colors)) {

                    // Attached to color instead of files.
                    $count = 1;
                    $color_list = $sep = '';

                    foreach ($colors as $color) {

                        if ($count == count($colors) && $count > 1) {
                            $sep = " {$lang->and} ";
                        }

                        $color_list .= $sep.trim($color);
                        ++$count;

                        $sep = ', ';

                    }

                    $attached_to = $lang->attached_to . $lang->sprintf($lang->colors_attached_to) . ' ' . $color_list;

                }

                // Orphaned! :(
                if ($attached_to == '') {
                    $attached_to = $lang->attached_to_nothing;
                }

            }
            else {
                $attached_to = $lang->attached_to_all_pages;
            }

            $resourcelist .= "<li data-title='{$filename}'{$modified} data-attachedto='{$attached_to}' data-id='{$style['sid']}'><i class='fab fa-css3-alt'></i> {$inherited}{$filename}</li>";

        }

        $resourcelist .= '</ul>';

    }

    // Templates
    $resourcelist .= "<li class='header icon'>Templates</li>";
    $resourcelist .= "<ul>";

    // Global templates
    if ($sid == -1 and !empty($template_groups[-1]['templates'])) {

        foreach ($template_groups[-1]['templates'] as $template) {
            $resourcelist .= "<li data-tid='{$template['tid']}' data-title='{$template['title']}' data-status='original'><i class='fas fa-file-code'></i> {$template['title']}</li>";
        }

    }
    // Regular set
    else {

        foreach ($template_groups as $prefix => $group) {

            $title = str_replace(' Templates', '', $group['title']);

            // We can delete this group
            $deletegroup = /* (isset($group['isdefault']) && !$group['isdefault']) ? '<i class="delete icon-cancel"></i>' :  */'';

            $resourcelist .= "<li class='header icon' data-gid='{$group['gid']}'><i class='fas fa-folder'></i> {$title}{$deletegroup}</li>";

            // Templates for this group exist
            if (isset($group['templates']) and count($group['templates']) > 0) {

                $templates = $group['templates'];
                ksort($templates);

                $resourcelist .= "<ul data-type='templates' data-prefix='{$prefix}'>";

                $lastTier = 0;
                $lastFragments = $templatesTree = [];

                foreach ($templates as $template) {

                    $originalOrModified = '';

                    // Strip out the prefix first
                    $displayTitle = str_replace($prefix . '_', '', $template['title']);

                    $fragments = explode('_', $displayTitle, 4);

                    // Work out the "tier"
                    $tier = 1;

                    $intersect = array_intersect_assoc($fragments, $lastFragments);
                    $count = count($intersect);
                    $md5 = md5(serialize($intersect));

                    if ($templatesTree[$md5]) {
                        $tier = $templatesTree[$md5];
                    }
                    else if ($intersect == $lastFragments or $count > $lastTier) {
                        $tier = $lastTier + 1;
                    }
                    else if ($count > 0 and $count == $lastTier) {
                        $tier = $lastTier;
                    }

                    if ($displayTitle == $prefix) {
                        $tier = 0;
                    }

                    $lastTier = $tier;
                    $lastFragments = $fragments;

                    $templatesTree[$md5] = $tier;

                    // Build the new, shortened title
                    $title = array_diff_assoc($fragments, $intersect);
                    $displayTitle = implode('_', $title);

                    if (isset($template['modified']) && $template['modified'] == true) {
                        $originalOrModified = ' data-status="modified"';
                    }
                    else if (isset($template['original']) && $template['original'] == false) {
                        $originalOrModified = ' data-status="original"';
                    }

                    $resourcelist .= "<li class='tier-{$tier}' data-tid='{$template['tid']}' data-title='{$template['title']}'{$originalOrModified}><i class='far fa-sticky-note'></i> {$displayTitle}</li>";

                }

                $resourcelist .= '</ul>';

            }
            // No templates in this group
            else {
                $resourcelist .= "<ul data-type='templates' data-prefix='{$prefix}'><li>{$lang->fastyle_no_templates_available}</li></ul>";
            }

            $resourcelist .= '</li>';

        }

    }

    $resourcelist .= '</ul>';

    // JavaScripts
    $resourcelist .= "<li class='header icon'>JavaScripts</li>";
    $resourcelist .= "<ul data-prefix='javascripts'>";

    $folder = MYBB_ROOT . 'jscripts';

    $originalList = [
        'jeditable/jeditable.min.js',
        'sceditor/jquery.sceditor.bbcode.min.js',
        'sceditor/jquery.sceditor.min.js',
        'sceditor/jquery.sceditor.xhtml.min.js',
        'sceditor/editor_plugins/bbcode.js',
        'sceditor/editor_plugins/format.js',
        'sceditor/editor_plugins/undo.js',
        'sceditor/editor_plugins/xhtml.js',
        'select2/select2.min.js',
        'validate/additional-methods.min.js',
        'validate/jquery.validate.min.js',
        'bbcodes_sceditor.js',
        'captcha.js',
        'general.js',
        'inline_edit.js',
        'inline_moderation.js',
        'inline_reports.js',
        'jquery.js',
        'jquery.plugins.js',
        'jquery.plugins.min.js',
        'post.js',
        'question.js',
        'rating.js',
        'report.js',
        'thread.js',
        'usercp.js'
    ];

    function build_scripts_list($folder = '') {

        global $originalList, $resourcelist, $folders;

        if (is_readable($folder)) {

            $files = scandir($folder);

            // . and ..
            unset($files[0], $files[1]);

            foreach ($files as $key => $file) {

                $relative = str_replace(MYBB_ROOT . 'jscripts' . DIRECTORY_SEPARATOR, '', realpath($folder . DIRECTORY_SEPARATOR . $file));

                // Determine this file status
                $status = (in_array($relative, $originalList)) ? 'modified' : 'original';

                // File or folder? Folders are grouped on top of a group
                $path = $folder . DIRECTORY_SEPARATOR . $file;
                if (is_dir($path)) {

                    $folders[] = "<li class='header icon'><i class='fas fa-folder'></i> {$file}</li>";
                    $folders[] = "<ul>";

                    build_scripts_list($path);

                    $folders[] = "</ul>";

                }
                else if (get_extension($file) == 'js') {
                    $_files[] = "<li data-title='{$relative}' data-status='{$status}'><i class='fab fa-node-js'></i> {$file}</li>";
                }

            }

            if (!empty($folders)) {

                foreach ((array) $folders as $folder) {
                    $resourcelist .= $folder;
                }

            }

            // If this directory is not empty, add its files and subdirs
            if (!empty($_files)) {

                foreach ((array) $_files as $file) {
                    $resourcelist .= $file;
                }

            }

            $folders = $_files = [];

        }

    }

    if (is_dir($folder)) {
        build_scripts_list($folder);
    }

    $resourcelist .= "</ul>";

    if ($mybb->input['sid']) {

        global $sub_tabs;

    	$page->output_header($lang->template_sets);
    	$page->output_nav_tabs($sub_tabs, 'manage_templates');

    }

    $form = new Form("index.php", "post", "fastyle_editor");

    $textarea = $form->generate_text_area('editor', '', ['id' => 'editor', 'style' => 'width: 100%; height: 520px']);
    echo <<<HTML
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.1/css/all.css" />
    <div class="fastyle">
        <div class="sidebar" id="sidebar">
            <ul class="search">
                <li><input type="textbox" name="search" autocomplete="off" placeholder="{$lang->fastyle_search_asset}" /></li>
            </ul>
            $resourcelist
            <ul class="nothing-found">
                <li>{$lang->fastyle_nothing_found}</li>
            </ul>
        </div>
        <div class="content">
            <div class="bar switcher">
                <div class="content">
                    <div class="swiper-wrapper"></div>
                </div>
                <div class="fas fa-chevron-circle-left swiper-button-prev"></div>
                <div class="fas fa-chevron-circle-right swiper-button-next"></div>
            </div>
            <div class="bar top">
                <div class="label">
                    <span class="title"></span>
                    <span class="dateline meta"></span>
                    <span class="attachedto meta"></span>
                </div>
                <div class="actions">
                    <span class="button diff" data-mode="diff">{$lang->fastyle_diff}</span>
                    <span class="button revert" data-mode="revert">{$lang->fastyle_revert}</span>
                    <span class="button delete" data-mode="delete">{$lang->fastyle_delete}</span>
                    <input type="submit" class="button visible" name="continue" value="{$lang->fastyle_save}" />
                    <input type="textbox" name="title" /><span class="button add visible" data-mode="add" title="{$lang->fastyle_add_asset}"><i class="fas fa-plus"></i></span>
                    <span class="button quickmode visible" title="{$lang->fastyle_quick_mode}"><i class="fas fa-hamsa"></i></span>
                    <i class="fas fa-expand fullpage"></i>
                </div>
            </div>
            <div>
                <div class="form_row">
                    $textarea
                    <div id="mergeview"></div>
                </div>
            </div>
        </div>
    </div>
HTML;

    echo fastyle_load_javascript($sid, $tid);
    echo "<br>";

    if ($mybb->input['sid']) {
        $page->output_footer();
        exit;
    }

}

function fastyle_templates_edit()
{
	global $page, $mybb, $db, $sid, $lang;

	if ($mybb->input['ajax']) {

		if (empty($mybb->input['title'])) {
			$errors[] = $lang->error_missing_title;
		}

		if (check_template($mybb->input['template'])) {
			$errors[] = $lang->error_security_problem;
		}

		if ($errors) {
			fastyle_message(implode("\n", $errors), 'error');
		}

	}

}

function fastyle_templates_edit_commit()
{
	global $template, $mybb, $set, $lang;

	if ($mybb->input['ajax']) {

		$lang->load('fastyle');

		log_admin_action($template['tid'], $mybb->input['title'], $mybb->input['sid'], $set['title']);

		$data = [
			'message' => $lang->sprintf($lang->fastyle_success_saved, $mybb->input['title'])
		];

		// Check if the tid coming from the browser matches the one returned from the db. If it doesn't = new template,
		// pass the tid to the client which will update its own tid
		if ($template['tid'] != $mybb->input['tid']) {
			$data['tid'] = $template['tid'];
		}

		fastyle_message($data);

	}

}

function fastyle_themes_edit_advanced_commit()
{
	global $mybb, $theme, $lang, $stylesheet;

	if ($mybb->request_method == "post" and $mybb->input['ajax']) {

		log_admin_action(htmlspecialchars_uni($theme['name']), $stylesheet['name']);

		fastyle_message($lang->sprintf($lang->fastyle_success_saved, $stylesheet['name']));

	}
}

function fastyle_admin_config_settings_change()
{
	global $page;

	$page->extra_header .= fastyle_load_javascript();
}

function fastyle_admin_config_settings_change_commit()
{
	global $mybb, $errors, $cache, $lang;

	if ($mybb->request_method == "post" and $mybb->input['ajax']) {

		if (!$errors) {

			// Log admin action
			log_admin_action();

			fastyle_message($lang->success_settings_updated);

		}
		else {
			fastyle_message($errors);
		}

	}
}

function fastyle_load_javascript($sid = 0, $tid = 0)
{
	static $loaded;

	$sid = (int) $sid;
	$tid = (int) $tid;

	$html = '';

	if ($loaded != true) {
		$html .= <<<HTML
<script type="text/javascript" src="jscripts/FASTyle/spin/spin.js?v=2.3"></script>
<script type="text/javascript" src="jscripts/FASTyle/main.js?v=2.3"></script>
HTML;
	}

	$loaded = true;

	$html .= <<<HTML
<script type="text/javascript">

    $(document).ready(function() {
    	FASTyle.init($sid, $tid);
    });

</script>
HTML;

	return $html;

}

function fastyle_message($data, $type = 'success')
{
	if (!is_array($data)) {
		$data = ['message' => $data];
	}

	if ($type == 'error') {
		$data['error'] = 1;
	}

	echo json_encode($data);

	exit;
}

function fastyle_admin_style_action_handler($actions)
{
	$actions['fastyle'] = array(
		"active" => "fastyle",
		"file" => "fastyle.php"
	);

	return $actions;
}
