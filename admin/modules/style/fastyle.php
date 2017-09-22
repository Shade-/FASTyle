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

// API endpoint
if ($mybb->input['get'] and $mybb->input['sid']) {
	
	switch ($mybb->input['get']) {
		
		case 'template':
		
			$query = $db->simple_select('templates', 'template, tid',
				'title = \'' . $db->escape_string($mybb->input['title']) . '\' AND (sid = -2 OR sid = ' . (int) $mybb->input['sid'] . ')',
				['order_by' => 'sid', 'order_dir' => 'desc', 'limit' => 1]);
			$template = $db->fetch_array($query);
			
			$content = $template['template'];
			
			break;
		
		case 'stylesheet':
		
			$parent_list = make_parent_theme_list($theme['tid']);
			$parent_list = implode(',', $parent_list);
			if (!$parent_list) {
				$parent_list = 1;
			}
		
			$query = $db->simple_select("themestylesheets", "*",
				"name='".$db->escape_string($mybb->input['title'])."' AND tid IN ({$parent_list})",
				['order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1]);
			$stylesheet = $db->fetch_array($query);
			
			$content = $stylesheet['stylesheet'];
			
			break;
		
	}
		
	if ($content) {
		fastyle_message(['content' => $content]);
	}
	else {
		fastyle_message('Error: resource not found');
	}
	
}

// Get template sets
$query = $db->simple_select("templatesets", "*", "", ['order_by' => 'title', 'order_dir' => 'ASC']);
while ($template_set = $db->fetch_array($query)) {
	$template_sets[$template_set['sid']] = $template_set;
}

// Get this theme's associated template set
if ($theme['properties']['templateset']) {
	$sid = $theme['properties']['templateset'];
}

if ($tid) {
	
	if (!isset($theme_cache[$tid])) {
		flash_message($lang->error_invalid_input, 'error');
		admin_redirect("index.php?module=style-fastyle");
	}
	
	$page->add_breadcrumb_item($lang->sprintf($lang->fastyle_breadcrumb_editing_theme, $theme_cache[$tid]['name']), "index.php?module=style-fastyle&amp;tid={$tid}");
	
	if ($admin_options['codepress'] != 0) {
		
		$page->extra_header .= '
<link href="./jscripts/codemirror/lib/codemirror.css" rel="stylesheet">
<link href="./jscripts/codemirror/theme/mybb.css" rel="stylesheet">
<script type="text/javascript" src="./jscripts/codemirror/lib/codemirror.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/mode/xml/xml.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/mode/javascript/javascript.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/mode/css/css.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/mode/htmlmixed/htmlmixed.js"></script>
<link href="./jscripts/codemirror/addon/dialog/dialog-mybb.css" rel="stylesheet">
<script type="text/javascript" src="./jscripts/codemirror/addon/dialog/dialog.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/addon/search/searchcursor.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/addon/search/search.js?ver=1808"></script>
<script type="text/javascript" src="./jscripts/codemirror/addon/fold/foldcode.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/addon/fold/xml-fold.js"></script>
<script type="text/javascript" src="./jscripts/codemirror/addon/fold/foldgutter.js"></script>
<script type="text/javascript" src="./jscripts/FASTyle/sublime.js"></script>
<link href="./jscripts/codemirror/addon/fold/foldgutter.css" rel="stylesheet">
<link href="./jscripts/FASTyle/editor.css" rel="stylesheet">';

	}

	$page->output_header($lang->template_sets);
	
	// Get a list of templates
	$query = $db->simple_select("templategroups", "*");

	$template_groups = array();
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
	
	// Load the list of templates
	$query = $db->simple_select("templates", "title,sid,tid,template", "sid='".$sid."' OR sid='-2'", ['order_by' => 'sid DESC, title', 'order_dir' => 'ASC']);
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
		
		$query = $db->simple_select("themestylesheets", "*", "", array('order_by' => 'sid DESC, tid', 'order_dir' => 'desc'));
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
	
	// Search
	$resourcelist .= '<li class="header search"><input type="textbox" name="search" autocomplete="off" /></li>';
	
	// Stylesheets
	$resourcelist .= '<li class="header">Stylesheets</li>';
	$resourcelist .= '<ul data-type="stylesheets">';

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
			
			foreach($style['inherited'] as $_tid) {
				
				if ($inherited_themes[$_tid]) {
					$inherited_ary[$_tid] = $inherited_themes[$_tid];
				}
				
			}
			
		}

		if (!empty($inherited_ary)) {
			
			$inherited = " <small>({$lang->inherited_from}";
			$sep = " ";
			$inherited_count = count($inherited_ary);
			$count = 0;

			foreach($inherited_ary as $_tid => $file) {
				
				if (isset($applied_to_count) && $count == $applied_to_count && $count != 0) {
					$sep = " {$lang->and} ";
				}

				$inherited .= $sep.$file;
				$sep = $lang->comma;

				++$count;
				
			}
			
			$inherited .= ")</small>";
			
		}
		else {
			$modified = ' data-modified';
		}
		
		if(is_array($style['applied_to']) && (!isset($style['applied_to']['global']) || $style['applied_to']['global'][0] != "global")) {
			
			$attached_to = '';

			$applied_to_count = count($style['applied_to']);
			$count = 0;
			$sep = " ";
			$name = "";

			$colors = array();

			if(!is_array($properties['colors'])) {
				$properties['colors'] = array();
			}

			foreach($style['applied_to'] as $name => $actions) {
				
				if(!$name) {
					continue;
				}

				if(array_key_exists($name, $properties['colors'])) {
					$colors[] = $properties['colors'][$name];
				}

				// Colors override files and are handled below.
				if(count($colors)) {
					continue;
				}

				// It's a file:
				++$count;

				if($actions[0] != "global") {
					$name = "{$name} ({$lang->actions}: ".implode(',', $actions).")";
				}

				if($count == $applied_to_count && $count > 1) {
					$sep = " {$lang->and} ";
				}
				$attached_to .= $sep.$name;

				$sep = $lang->comma;
				
			}

			if($attached_to) {
				$attached_to = "<small>{$lang->attached_to} {$attached_to}</small>";
			}

			if(count($colors)) {
				
				// Attached to color instead of files.
				$count = 1;
				$color_list = $sep = '';

				foreach($colors as $color) {
					
					if($count == count($colors) && $count > 1) {
						$sep = " {$lang->and} ";
					}

					$color_list .= $sep.trim($color);
					++$count;

					$sep = ', ';
					
				}

				$attached_to = "<small>{$lang->attached_to} ".$lang->sprintf($lang->colors_attached_to)." {$color_list}</small>";
				
			}

			// Orphaned! :(
			if($attached_to == '') {
				$attached_to = "<small>{$lang->attached_to_nothing}</small>";
			}
			
		}
		else {
			$attached_to = "<small>{$lang->attached_to_all_pages}</small>";
		}
		
		$resourcelist .= "<li data-title='{$filename}'{$modified}>{$filename}{$inherited}<br>{$attached_to}</li>";
	
	}
	
	$resourcelist .= '</ul>';

	// Template list
	foreach ($template_groups as $prefix => $group) {
		
		$title = str_replace(' Templates', '', $group['title']);
		
		$resourcelist .= "<li class='header' data-gid='{$group['gid']}'>{$title}</li>";
		
		// Templates for this group exist
		if (isset($group['templates']) and count($group['templates']) > 0) {
			
			$templates = $group['templates'];
			ksort($templates);
			
			$resourcelist .= "<ul data-type='templates' data-prefix='{$prefix}'>";

			foreach ($templates as $template) {
				
				$modified = $original = '';
				
				if (isset($template['modified']) && $template['modified'] == true) {
					$modified = ' data-modified';
				}
				else if (isset($template['original']) && $template['original'] == false) {
					$original = ' data-original';
				}
				
				$resourcelist .= "<li data-tid='{$template['tid']}' data-title='{$template['title']}'{$modified}{$original}>{$template['title']}</li>";
				
			}
			
			$resourcelist .= '</ul>';
			
		}
		// No templates in this group
		else {
			$resourcelist .= "<ul><li>{$lang->fastyle_no_templates_available}</li></ul>";
		}

		
		$resourcelist .= '</li>';
		
	}
	
	$resourcelist .= '</ul>';
	
	$form = new Form("index.php", "post", "fastyle_editor");
	
	$form_container = new FormContainer($lang->edit_template_breadcrumb, 'tfixed');
	
	$textarea = $form->generate_text_area('editor', '', ['id' => 'editor', 'style' => 'width: 100%; height: 500px']);
	$content = <<<HTML
<div class="fastyle">
	<div class="sidebar">
		$resourcelist
	</div>
	<div class="form_row">
		$textarea
	</div>
</div>
HTML;
	
	$form_container->output_row("", "", $content);
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_continue, ['name' => 'continue']);

	$form->output_submit_wrapper($buttons);

	$form->end();

	if ($admin_options['codepress'] != 0) {
		
		echo '<script type="text/javascript">
			var editor = CodeMirror.fromTextArea(document.getElementById("editor"), {
				lineNumbers: true,
				lineWrapping: true,
				foldGutter: true,
				gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
				viewportMargin: Infinity,
				indentWithTabs: true,
				indentUnit: 4,
				mode: "text/html",
				theme: "mybb",
				keyMap: "sublime"
			});
		</script>';
		
	}
	
	echo fastyle_load_javascript($sid, $tid);

	$page->output_footer();
	
}

if (!$mybb->input['action']) {
	
	$page->output_header($lang->themes);

	$table = new Table;
	$table->construct_header($lang->theme);

	fastyle_build_theme_list();

	$table->output($lang->themes);

	$page->output_footer();
	
}

function fastyle_build_theme_list($parent = 0, $depth = 0)
{
	global $mybb, $db, $table, $lang, $page, $template_sets;
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

		// Fetch & build any child themes
		fastyle_build_theme_list($theme['tid'], ++$depth);
		
	}
	
}