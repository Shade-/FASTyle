<?php
	
if (!defined("IN_MYBB")) {
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}
	
require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

$lang->load('style_themes');
$lang->load('style_templates');

$page->add_breadcrumb_item($lang->fastyle, "index.php?module=style-fastyle");

$tid = $mybb->get_input('tid', MyBB::INPUT_INT);
	
$template_sets = [];
$template_sets[-1]['title'] = $lang->global_templates;
$template_sets[-1]['sid'] = -1;
		
$themes = cache_themes();

// Get template sets
$query = $db->simple_select("templatesets", "*", "", ['order_by' => 'title', 'order_dir' => 'ASC']);
while ($template_set = $db->fetch_array($query)) {
	$template_sets[$template_set['sid']] = $template_set;
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
	
	$title = $db->escape_string($mybb->input['title']);
	$mode = (get_extension($mybb->input['title']) != 'css') ? 'templates' : 'stylesheets';
	
	// Get stylesheet/template
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
			fastyle_message('Resource not found', 'error');
		}
	
	}
	
	// Revert template
	if ($mybb->input['action'] == 'revert') {
		
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
	
	// Delete template
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

		fastyle_message($lang->success_template_deleted);
		
	}
	
	// Delete template group
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
	
	// Diff mode
	if ($mybb->input['action'] == 'diff') {
		
		if ($mode == 'templates') {
			
			$query = $db->simple_select("templates", "template", "title='".$title."' AND sid='-2'");
			$content = $db->fetch_field($query, 'template');
			
		}
		else {
			
			$query = $db->simple_select('themestylesheets', 'stylesheet', "name='".$title."' AND tid='1'");
			$content = $db->fetch_field($query, 'stylesheet');
			
		}
		
		if (!$content) {
			fastyle_message(['error' => 'Resource not found']);
		}
		
		fastyle_message(['content' => $content]);
		
	}
	
	// Add template group
	if ($mybb->input['action'] == 'addgroup') {
		
		if (!trim($mybb->input['title'])) {
			fastyle_message($lang->error_missing_set_title, 'error');
		}
		
		$gid = $db->insert_query("templatesets", ['title' => $db->escape_string($mybb->input['title'])]);

		// Log admin action
		log_admin_action($gid, $mybb->input['title']);

		fastyle_message($lang->success_template_set_saved);
		
	}
	
	// Add resource
	if ($mybb->input['action'] == 'add') {
		
		// Stylesheet
		if ($mode == 'stylesheets') {
			
			// Remove special characters
			$mybb->input['title'] = preg_replace('#([^a-z0-9-_\.]+)#i', '', $mybb->input['title']);
			if (!$mybb->input['title'] or $mybb->input['title'] == ".css") {
				fastyle_message($lang->error_missing_stylesheet_name, 'error');
			}
	
			// Get 30 chars only because we don't want more than that
			$mybb->input['title'] = my_substr($mybb->input['title'], 0, 30);

			// Add Stylesheet
			$insert_array = [
				'name' => $db->escape_string($mybb->input['title']),
				'tid' => $tid,
				'attachedto' => '',
				'stylesheet' => $db->escape_string($mybb->input['stylesheet']),
				'cachefile' => $db->escape_string(str_replace('/', '', $mybb->input['title'])),
				'lastmodified' => TIME_NOW
			];

			$sid = $db->insert_query("themestylesheets", $insert_array);

			if (!cache_stylesheet($theme['tid'], str_replace('/', '', $mybb->input['title']), $mybb->input['title'])) {
				$db->update_query("themestylesheets", ['cachefile' => "css.php?stylesheet={$sid}"], "sid='{$sid}'", 1);
			}

			// Update the CSS file list for this theme
			update_theme_stylesheet_list($theme['tid'], $theme, true);

			// Log admin action
			log_admin_action($sid, $mybb->input['title'], $theme['tid'], htmlspecialchars_uni($theme['name']));

			fastyle_message($lang->success_stylesheet_added);

		}
		
		// Template
		if (empty($mybb->input['title'])) {
			$errors[] = $lang->error_missing_set_title;
		}
		else {
			
			$query = $db->simple_select("templates", "COUNT(tid) as count", "title='" . $title . "' AND (sid = '-2' OR sid = '{$sid}')");
			
			if ($db->fetch_field($query, "count") > 0) {
				$errors[] = $lang->error_already_exists;
			}
			
		}

		if (!isset($template_sets[$sid])) {
			$errors[] = $lang->error_invalid_set;
		}

		// Are we trying to do malicious things in our template?
		if (check_template($mybb->input['template'])) {
			$errors[] = $lang->error_security_problem;
		}
		
		if ($errors) {
			fastyle_message(implode("\n", $errors), 'error');
		}

		$template_array = [
			'title' => $title,
			'sid' => $sid,
			'template' => $db->escape_string(rtrim($mybb->input['template'])),
			'version' => $db->escape_string($mybb->version_code),
			'status' => '',
			'dateline' => TIME_NOW
		];

		$tid = $db->insert_query("templates", $template_array);

		// Log admin action
		log_admin_action($tid, $mybb->input['title'], $sid, $template_sets[$sid]);
		
		$data = [
			'message' => $lang->sprintf($lang->fastyle_success_template_saved, $template_array['title']),
			'tid' => $tid
		];

		fastyle_message($data);
		
	}
	
}

// Get this theme's associated template set
if ($theme['properties']['templateset']) {
	$sid = $theme['properties']['templateset'];
}

if ($tid or $sid) {
	
	/*if (!isset($theme_cache[$tid])) {
		flash_message($lang->error_invalid_input, 'error');
		admin_redirect("index.php?module=style-fastyle");
	}*/
	
	$page->add_breadcrumb_item($lang->sprintf($lang->fastyle_breadcrumb_editing_theme, $theme_cache[$tid]['name']), "index.php?module=style-fastyle&amp;tid={$tid}");
	
	if ($admin_options['codepress'] != 0) {
		
		$page->extra_header .= '
<link href="./jscripts/codemirror/lib/codemirror.css" rel="stylesheet">
<script type="text/javascript" src="./jscripts/codemirror/lib/codemirror.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/mode/xml/xml.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/mode/javascript/javascript.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/mode/css/css.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/mode/htmlmixed/htmlmixed.js"></script>
<link href="./jscripts/FASTyle/dialog.css" rel="stylesheet">
<script type="text/javascript" src="./jscripts/FASTyle/search.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/addon/dialog/dialog.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/addon/search/searchcursor.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/addon/fold/foldcode.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/addon/fold/xml-fold.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/addon/fold/foldgutter.js"></script>
<script type="text/javascript" src="./jscripts/FASTyle/comment.js"></script>
<script type="text/javascript" src="./jscripts/FASTyle/sublime.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/diff_match_patch/20121119/diff_match_patch.js"></script>
<script type="text/javascript" src="./jscripts/FASTyle/merge.js"></script>
<link href="./jscripts/codemirror/addon/fold/foldgutter.css" rel="stylesheet">
<link href="./jscripts/FASTyle/merge.css" rel="stylesheet">
<link href="./jscripts/FASTyle/editor.css" rel="stylesheet">
<link href="./jscripts/FASTyle/material.css" rel="stylesheet">';

	}

	$page->output_header($lang->template_sets);
	
	// Get a list of templates
	$query = $db->simple_select("templategroups", "*");

	$template_groups = [];
	while ($templategroup = $db->fetch_array($query)) {
		
		$templategroup['title'] = $lang->sprintf($lang->templates, htmlspecialchars_uni($lang->parse($templategroup['title'])));
		
		$template_groups[$templategroup['prefix']] = $templategroup;
		
	}

	/**
	 * @param array $a
	 * @param array $b
	 *
	 * @return int
	 */
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
				
				$inherited = " <i class='icon-attention' title='{$lang->inherited_from}";
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
			
			$resourcelist .= "<li data-title='{$filename}'{$modified} data-attachedto='{$attached_to}'>{$filename}{$inherited}</li>";
		
		}
		
		$resourcelist .= '</ul>';
		
	}

	// Template list
	// Global templates
	if ($sid == -1 and !empty($template_groups[-1]['templates'])) {
		
		foreach ($template_groups[-1]['templates'] as $template) {
			$resourcelist .= "<li data-tid='{$template['tid']}' data-title='{$template['title']}' data-original>{$template['title']}</li>";
		}
		
	}
	// Regular set
	else {
		
		foreach ($template_groups as $prefix => $group) {
						
			$title = str_replace(' Templates', '', $group['title']);
			
			// We can delete this group
			$deletegroup = (isset($group['isdefault']) && !$group['isdefault']) ? '<i class="deletegroup icon-cancel"></i>' : '';
			
			$resourcelist .= "<li class='header icon' data-gid='{$group['gid']}'>{$title}{$deletegroup}</li>";
						
			// Templates for this group exist
			if (isset($group['templates']) and count($group['templates']) > 0) {
				
				$templates = $group['templates'];
				ksort($templates);
				
				$resourcelist .= "<ul data-type='templates' data-prefix='{$prefix}'>";
	
				foreach ($templates as $template) {
					
					$originalOrModified = '';
					
					if (isset($template['modified']) && $template['modified'] == true) {
						$originalOrModified = ' data-status="modified"';
					}
					else if (isset($template['original']) && $template['original'] == false) {
						$originalOrModified = ' data-status="original"';
					}
					
					$resourcelist .= "<li data-tid='{$template['tid']}' data-title='{$template['title']}'{$originalOrModified}>{$template['title']}</li>";
					
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
	
	$form = new Form("index.php", "post", "fastyle_editor");
	
	$form_container = new FormContainer();
	
	$textarea = $form->generate_text_area('editor', '', ['id' => 'editor', 'style' => 'width: 100%; height: 500px']);
	$content = <<<HTML
<div class="fastyle">
	<div class="bar top">
		<div class="sidebar">
			<ul><li class="header search"><input type="textbox" name="search" autocomplete="off" /></li></ul>
		</div>
		<div class="label">
			<span class="title"></span>
			<span class="dateline meta"></span>
			<span class="attachedto meta"></span>
		</div>
		<div class="actions">
			<span class="button diff" data-mode="diff">Diff</span>
			<span class="button revert" data-mode="revert">Revert</span>
			<span class="button delete" data-mode="delete">Delete</span>
			<i class="icon-resize-full fullpage"></i>
		</div>
	</div>
	<div>
		<div class="sidebar" id="sidebar">
			$resourcelist
			<ul class="nothing-found"><li>Nothing found</li></ul>
		</div>
		<div class="form_row">
			$textarea
		</div>
	</div>
	<div class="bar bottom">
		<div class="actions">
			<input type="textbox" name="title" /><span class="button add visible" data-mode="add">Add</span>
			<span class="button quickmode visible">Quick mode</span>
		</div>
	</div>
</div>
HTML;
	
	$form_container->output_row("", "", $content);
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_continue, ['name' => 'continue']);

	$form->output_submit_wrapper($buttons);

	$form->end();
	
	echo fastyle_load_javascript($sid, $tid);

	$page->output_footer();
	
}

if (!$mybb->input['action']) {
	
	$page->output_header($lang->themes);

	$table = new Table;
	$table->construct_header($lang->theme);
	
	$not_processed = $template_sets;

	fastyle_build_theme_list();

	$table->output($lang->themes);
	
	// Include template sets not attached to any theme and global templates
	if ($not_processed) {
		
		$table = new Table;
		$table->construct_header('Other sets');
		
		foreach ($not_processed as $set) {
			
			$table->construct_cell("<div><strong><a href=\"index.php?module=style-fastyle&amp;sid={$set['sid']}\">" . htmlspecialchars_uni($set['title']) . "</a></strong></div>");
			$table->construct_row();
			
		}
		
		$table->output($lang->themes);
		
	}

	$page->output_footer();
	
}

function fastyle_build_theme_list($parent = 0, $depth = 0)
{
	global $mybb, $db, $table, $lang, $page, $template_sets, $not_processed;
	static $theme_cache;
	
	$padding = $depth * 20; // Padding
	
	if (!is_array($theme_cache)) {
		
		$themes = cache_themes();
		
		// Restrucure the theme array to something we can "loop-de-loop" with
		foreach ($themes as $key => $theme) {
			
			if ($key == "default") {
				continue;
			}
		
			$theme_cache[$theme['pid']][$theme['tid']] = $theme;
			
		}
		
		$theme_cache['num_themes'] = count($themes);
		unset($themes);
		
	}

	if (!is_array($theme_cache[$parent])) {
		return;
	}

	foreach ($theme_cache[$parent] as $theme) {
		
		$notice = '';
		
		// Figure out the template set this theme is using
		$set = $template_sets[$theme['properties']['templateset']];
		if ($set) {
			$notice = 'Using: ' . $set['title'];
		}
		
		if ($theme['tid'] > 1) {
			$theme['name'] = "<a href=\"index.php?module=style-fastyle&amp;tid={$theme['tid']}\">" . htmlspecialchars_uni($theme['name']) . "</a>";
		}
		
		$table->construct_cell("<div style=\"margin-left: {$padding}px;\"><strong>{$theme['name']}</strong><br /><small>{$notice}</small></div>");
		$table->construct_row();
		
		unset ($not_processed[$theme['properties']['templateset']]);

		// Fetch & build any child themes
		fastyle_build_theme_list($theme['tid'], ++$depth);
		
	}
	
}