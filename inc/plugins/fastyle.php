<?php

/**
 * Save templates, themes and settings on the fly using the power of AJAX.
 *
 * @package FASTyle
 * @author  Shade <legend_k@live.it>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 1.6.1
 */

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined("PLUGINLIBRARY")) {
	define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

function fastyle_info()
{	
	return [
		'name' => 'FASTyle',
		'description' => 'Save templates, themes and settings on the fly using the power of AJAX.',
		'author' => 'Shade',
		'authorsite' => 'http://www.mybboost.com',
		'version' => '1.6.1',
		'compatibility' => '18*'
	];
}

function fastyle_is_installed()
{
	global $cache;
	
	$info      = fastyle_info();
	$installed = $cache->read("shade_plugins");
	if ($installed[$info['name']]) {
		return true;
	}
}

function fastyle_install()
{
	global $cache, $PL, $mybb;
	
	if (!file_exists(PLUGINLIBRARY)) {
		flash_message('FASTyle requires PluginLibrary to be installed.', "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	$PL or require_once PLUGINLIBRARY;
	
	$PL->edit_core('fastyle', $mybb->config['admin_dir'] . '/modules/style/templates.php', [
		[
			'search' => '$form_container->output_row($lang->template_set, $lang->template_set_desc, $form->generate_select_box(\'sid\', $template_sets, $sid));',
			'before' => '$plugins->run_hooks("admin_style_templates_edit_template_fastyle");'
		]
	], true);
	
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
	global $cache, $PL;
	
	if (!file_exists(PLUGINLIBRARY)) {
		flash_message('FASTyle requires PluginLibrary to be uninstalled.', "error");
		admin_redirect("index.php?module=config-plugins");
	}
	
	$PL or require_once PLUGINLIBRARY;
	
	$PL->edit_core('fastyle', 'admin/modules/style/templates.php', [], true);
	
	// Delete the plugin from cache
	$info         = fastyle_info();
	$shade_plugins = $cache->read('shade_plugins');
	unset($shade_plugins[$info['name']]);
	$cache->update('shade_plugins', $shade_plugins);
	
}

// Hooks
if (defined('IN_ADMINCP')) {
	
	$plugins->add_hook("admin_load", "fastyle_ad");
	$plugins->add_hook("admin_style_templates_edit_template", "fastyle_templates_edit");
	$plugins->add_hook("admin_style_templates_edit_template_commit", "fastyle_templates_edit_commit");
	$plugins->add_hook("admin_style_themes_edit_stylesheet_advanced", "fastyle_themes_edit_advanced");
	$plugins->add_hook("admin_style_themes_edit_stylesheet_simple", "fastyle_themes_edit_simple");
	$plugins->add_hook("admin_style_themes_edit_stylesheet_simple_commit", "fastyle_themes_edit_simple_commit");
	$plugins->add_hook("admin_config_settings_change", "fastyle_admin_config_settings_change", 1000);
	$plugins->add_hook("admin_config_settings_change_commit", "fastyle_admin_config_settings_change_commit");
	$plugins->add_hook("admin_style_templates_set", "fastyle_admin_style_templates_set");
	$plugins->add_hook("admin_load", "fastyle_get_templates");
	$plugins->add_hook("admin_style_templates_edit_template_fastyle", "fastyle_quick_templates_jump");
	
}

// Advertising
function fastyle_ad()
{
	global $cache, $mybb;
	
	$plugins = $cache->read('shade_plugins');
	if (!in_array($mybb->user['uid'], (array) $plugins['FASTyle']['ad_shown'])) {
		
		flash_message('Thank you for using FASTyle! You might also be interested in other great plugins on <a href="http://projectxmybb.altervista.org">MyBBoost</a>, where you can also get support for FASTyle itself.<br /><small>This message will not be shown again to you.</small>', 'success');
		
		$plugins['FASTyle']['ad_shown'][] = $mybb->user['uid'];
		$cache->update('shade_plugins', $plugins);
		
	}
	
}

function fastyle_templates_edit()
{
	global $page, $mybb, $lang, $db, $sid;
	
	if ($mybb->input['get_template_ajax']) {
		
		$query = $db->simple_select('templates', 'template, tid',
			'title = \'' . $db->escape_string($mybb->input['title']) . '\' AND (sid = -2 OR sid = ' . (int) $sid . ')',
			['order_by' => 'sid', 'order_dir' => 'desc', 'limit' => 1]);
		$template = $db->fetch_array($query);
		
		fastyle_message(['template' => $template['template'], 'tid' => $template['tid']]);
		
	}
	
	$page->extra_header .= fastyle_build_header_template(<<<HTML

	$("#edit_template").submit(function(e) {
	
HTML
);

	if ($mybb->input['ajax']) {
		
		if (empty($mybb->input['title'])) {
			$errors[] = $lang->error_missing_title;
		}
		
		if (check_template($mybb->input['template'])) {
			$errors[] = $lang->error_security_problem;
		}
		
		if ($errors) {
			fastyle_message($errors);
		}
		
	}
	
}

function fastyle_templates_edit_commit()
{
	global $template, $mybb, $set, $lang, $errors, $template_array;
	
	if ($mybb->input['ajax']) {
	
		log_admin_action($template['tid'], $mybb->input['title'], $mybb->input['sid'], $set['title']);
		
		$data = [
			'message' => "{$template['title']} has been updated successfully."
		];
		
		// Check if the tid coming from the browser matches the one returned from the db. If it doesn't = new template,
		// pass the tid to the client which will update its own tid
		if ($template['tid'] != $mybb->input['tid']) {
			$data['tid'] = $template['tid'];
		}
		
		fastyle_message($data);
				
	}
	
}

function fastyle_themes_edit_advanced()
{
	global $mybb, $db, $theme, $lang, $page, $plugins, $stylesheet;
	
	$page->extra_header .= fastyle_build_header_template(<<<HTML

	$("#edit_stylesheet").submit(function(e) {
	
HTML
);

	if ($mybb->request_method == "post" and $mybb->input['ajax']) {

		$parent_list = make_parent_theme_list($theme['tid']);
		$parent_list = implode(',', $parent_list);
		
		if (!$parent_list) {
			$parent_list = 1;
		}
	
		$query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string($mybb->input['file'])."' AND tid IN ({$parent_list})", ['order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1]);
		$stylesheet = $db->fetch_array($query);
	
		// Does the theme not exist?
		if (!$stylesheet['sid']) {
			fastyle_message($lang->error_invalid_stylesheet);
		}
		
		$sid = $stylesheet['sid'];

		// Theme & stylesheet theme ID do not match, editing inherited - we copy to local theme
		if ($theme['tid'] != $stylesheet['tid']) {
			$sid = copy_stylesheet_to_theme($stylesheet, $theme['tid']);
		}

		// Now we have the new stylesheet, save it
		$updated_stylesheet = [
			"cachefile" => $db->escape_string($stylesheet['name']),
			"stylesheet" => $db->escape_string(unfix_css_urls($mybb->input['stylesheet'])),
			"lastmodified" => TIME_NOW
		];
		$db->update_query("themestylesheets", $updated_stylesheet, "sid='{$sid}'");

		// Cache the stylesheet to the file
		if (!cache_stylesheet($theme['tid'], $stylesheet['name'], $mybb->input['stylesheet'])) {
			$db->update_query("themestylesheets", ['cachefile' => "css.php?stylesheet={$sid}"], "sid='{$sid}'", 1);
		}

		// Update the CSS file list for this theme
		update_theme_stylesheet_list($theme['tid']);
		
		$plugins->run_hooks("admin_style_themes_edit_stylesheet_advanced_commit");

		// Log admin action
		log_admin_action(htmlspecialchars_uni($theme['name']), $stylesheet['name']);

		fastyle_message($lang->success_stylesheet_updated);
		
	}
}

function fastyle_themes_edit_simple()
{
	global $page;
	
	$page->extra_header .= fastyle_build_header_template(<<<HTML

	$(document).on('submit', 'form[action*="edit_stylesheet"]', function(e) {

HTML
);

}

function fastyle_themes_edit_simple_commit()
{
	global $mybb, $lang, $theme, $stylesheet;
	
	// Log admin action
	log_admin_action(htmlspecialchars_uni($theme['name']), $stylesheet['name']);
	
	if ($mybb->input['ajax']) {
		fastyle_message($lang->success_stylesheet_updated);
	}
}

function fastyle_admin_config_settings_change()
{
	global $page;
	
	$page->extra_header .= fastyle_build_header_template(<<<HTML

	$("#change").submit(function(e) {
	
HTML
);

}

function fastyle_admin_config_settings_change_commit()
{
	global $mybb, $errors, $cache, $lang;
	
	if ($mybb->request_method == "post" and $mybb->input['ajax']) {
		
		if (!$errors) {
			
			// If we have changed our report reasons recache them
			if(isset($mybb->input['upsetting']['reportreasons']))
			{
				$cache->update_reportedposts();
			}
	
			// Log admin action
			log_admin_action();
			
			fastyle_message($lang->success_settings_updated);
			
		}
		else {
			fastyle_message($errors);
		}
		
	}
}

function fastyle_admin_style_templates_set()
{
	global $page;
	
	$page->extra_header .= <<<HTML
<script type="text/javascript" src="jscripts/FASTyle/spin.js"></script>
<script type="text/javascript">
	
	$(document).ready(function() {
		
		var getUrlParameter = function (sParam) {
		    var sPageURL = decodeURIComponent(window.location.search.substring(1)),
		        sURLVariables = sPageURL.split('&'),
		        sParameterName,
		        i;
		
		    for (i = 0; i < sURLVariables.length; i++) {
		        sParameterName = sURLVariables[i].split('=');
		
		        if (sParameterName[0] === sParam) {
		            return sParameterName[1] === undefined ? true : sParameterName[1];
		        }
		    }
		};
		
		var replaceUrlParameter = function (url, paramName, paramValue) {
		    if (paramValue == null)
		        paramValue = '';
		        
		    var pattern = new RegExp('('+paramName+'=).*?(&|$)');
		    
		    if (url.search(pattern) >= 0) {
		        return url.replace(pattern, '$1' + paramValue + '$2');
		    }
		    
		    return url + (url.indexOf('?') > 0 ? '&' : '?') + paramName + '=' + paramValue;
		}
		
		var removeItem = function (array, value) {
		    if(Array.isArray(value)) {  // For multi remove
		        for(var i = array.length - 1; i >= 0; i--) {
		            for(var j = value.length - 1; j >= 0; j--) {
		                if(array[i] == value[j]) {
		                    array.splice(i, 1);
		                };
		            }
		        }
		    }
		    else { // For single remove
		        for(var i = array.length - 1; i >= 0; i--) {
		            if(array[i] == value) {
		                array.splice(i, 1);
		            }
		        }
		    }
		}
		
		var expand_list = (typeof getUrlParameter('expand') !== 'undefined') ? getUrlParameter('expand').split('|') : [];
		
		var updateUrls = function (gid) {
			
			var expanded_string = expand_list.join('|');
	    	
	    	// Update the url of every link
	    	$('.group' + gid + ' a:not([class])').each(function(k, v) {
		    	return ($(this).attr('href').indexOf('javascript:;') === -1) ? $(this).attr('href', replaceUrlParameter($(this).attr('href'), 'expand', expanded_string)) : false;
		    });
		    
		    // Update the current page url
			var currentExpand = getUrlParameter('expand');
			if (currentExpand != expanded_string) {
				history.replaceState(null, '', replaceUrlParameter(window.location.href, 'expand', expanded_string));
			}
			
		}
		
		$('body').on('click', 'tr[id*="group_"] .first a', function(e) {
			
			e.preventDefault();
			
			var a = $(this);
			var url = a.attr('href'),
				string = '#group_';
				
			var gid = Number(url.substring(url.indexOf(string) + string.length));
			
			if (!gid || typeof gid == 'undefined') {
				return false
			}
			
			// Check if there are rows already open
			var visible_rows = a.parents('tr').nextUntil('tr[id*="group_"]');
			
			if (visible_rows.length > 0 && !visible_rows.hasClass('group' + gid)) {
				
				visible_rows.addClass('group' + gid);
				a.data('expanded', true);
				
			}
			
			// Open
			if (a.data('expanded') != true) {
				
				var items = $('.group' + gid);
		    	
		    	expand_list.push(gid);
				
				if (items.length) {
					
					items.show();
		    	
					a.data('expanded', true);
			    	updateUrls(gid);
			    	
				}
				else {
					
					var opts = {
						  lines: 9 // The number of lines to draw
						, length: 20 // The length of each line
						, width: 9 // The line thickness
						, radius: 19 // The radius of the inner circle
						, scale: 0.25 // Scales overall size of the spinner
						, corners: 1 // Corner roundness (0..1)
						, color: '#000' // #rgb or #rrggbb or array of colors
						, opacity: 0.25 // Opacity of the lines
						, rotate: 0 // The rotation offset
						, direction: 1 // 1: clockwise, -1: counterclockwise
						, speed: 1 // Rounds per second
						, trail: 60 // Afterglow percentage
						, fps: 20 // Frames per second when using setTimeout() as a fallback for CSS
						, zIndex: 2e9 // The z-index (defaults to 2000000000)
						, className: 'spinner' + gid // The CSS class to assign to the spinner
						, top: '50%' // Top position relative to parent
						, left: '120%' // Left position relative to parent
						, shadow: false // Whether to render a shadow
						, hwaccel: false // Whether to use hardware acceleration
						, position: 'absolute' // Element positioning
					}
					
					var spinner = new Spinner(opts).spin();
				    
				    // Launch the spinner
				    a.css('position', 'relative').append(spinner.el);
				    
					$.ajax({
			    		type: 'GET',
			    		url: 'index.php?action=get_templates',
			    		data: {
				    		'gid': gid,
				    		'sid': Number(getUrlParameter('sid'))
				    	},
				    	success: function(data) {
					    	
					    	// Delete the spinner
					    	spinner.stop();
					    	
					    	var html = $.parseJSON(data);
					    	
					    	a.parents('tr').after(html);
		    	
							a.data('expanded', true);
					    	
					    	updateUrls(gid);
					    		
					    }	
			    	});
			    	
			    }
		    	
		    }
		    // Close
		    else {
			    
				a.data('expanded', false).parents('tr').siblings('.group' + gid).hide();
				
				removeItem(expand_list, gid);
				updateUrls(gid);
				
			}
			
		});
		
	});
	
</script>
HTML;
	
}

function fastyle_get_templates()
{
	global $mybb, $db, $lang;
	
	if ($mybb->input['action'] != 'get_templates') {
		return false;
	}
	
	$gid = (int) $mybb->input['gid'];
	$sid = (int) $mybb->input['sid'];
	
	$prefixes = [];
	
	$where_sql = ($gid != -1) ? "gid = '$gid'" : '';
	
	$query = $db->simple_select("templategroups", "prefix", $where_sql);
	while ($prefix = $db->fetch_field($query, 'prefix')) {
		$prefixes[$prefix] = 1;
	}
	
	$html = $temp_templates = [];
	
	$ungrouped = (count($prefixes) > 1) ? true : false;
	
	$query = $db->simple_select("templates", "*", "sid='{$sid}' OR sid='-2'", ['order_by' => 'sid DESC, title', 'order_dir' => 'ASC']);
	while ($template = $db->fetch_array($query)) {
		
		$exploded = explode("_", $template['title'], 2);

		// Set the prefix to lowercase for case insensitive comparison.
		$exploded[0] = strtolower($exploded[0]);
		
		if ((!$ungrouped and !$prefixes[$exploded[0]]) or ($ungrouped and $prefixes[$exploded[0]])) {
			continue;
		}
		
		$templates[$template['sid']][$template['title']] = $template;
		
		$temp_templates[] = $template;
		
	}
	
	$lang->load('style_templates', false, true);
	$alt = ' alt_row';
	
	// No templates found
	if (empty($temp_templates)) {
		$html[] = '<tr>
	<td colspan="2">' . $lang->empty_template_set . '</td>
</tr>';
	}
	
	foreach ($temp_templates as $template) {
		
		if (($html[$template['title']] and $template['sid'] == -2) or (in_array($template['title'], $prefixes) and !$multiple_prefixes)) {
			continue;
		}
		
		$template_title = urlencode($template['title']);
		
		$popup = new PopupMenu("template_{$template['tid']}", $lang->options);
		$popup->add_item($lang->full_edit, "index.php?module=style-templates&amp;action=edit_template&amp;title={$template_title}&amp;sid={$sid}");
		
		// Not modified
		$title = $template['title'];
		
		// Modified
		if ($templates['sid'] != -2 and $templates[-2][$template['title']] and $templates[-2][$template['title']]['template'] != $template['template']) {
			
			$title = '<span style="color: green">' . $template['title'] . '</span>';
			
			// Add diff/revert options
			$popup->add_item($lang->diff_report, "index.php?module=style-templates&amp;action=diff_report&amp;title={$template_title}&amp;sid2={$sid}");
			$popup->add_item($lang->revert_to_orig, "index.php?module=style-templates&amp;action=revert&amp;title={$template_title}&amp;sid={$sid}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_template_revertion}')");
			
		}
		// Not present in masters
		else if (!$templates[-2][$template['title']]) {
			$title = '<span style="color: blue">' . $title . '</span>';
		}
		
		$alt = ($alt == '') ? ' alt_row' : '';
		
		$html[$template['title']] = <<<HTML
<tr class="group{$gid}{$alt}">
	<td class="first"><span style="padding: 20px"><a href="index.php?module=style-templates&amp;action=edit_template&amp;title={$template_title}&amp;sid={$sid}">{$title}</a></span></td>
	<td class="align_center last alt_col">{$popup->fetch()}</td>
</tr>
HTML;

	}
	
	ksort($html);

	echo json_encode(implode("\n", $html));
	
	exit;
	
}

function fastyle_quick_templates_jump()
{
	global $db, $lang, $form_container, $form, $template_sets, $sid;
	
	$templates = ['' => 'Select a template'];
	
	$query = $db->simple_select('templates', 'title', "sid = '-2' OR sid = '{$sid}'");
	while ($title = $db->fetch_field($query, 'title')) {
		
		if ($templates[$title]) {
			continue;
		}
		
		$templates[$title] = $title;
		
	}
	
	ksort($templates);
	
	$script = <<<HTML
<link rel="stylesheet" href="../jscripts/select2/select2.css" type="text/css" />
<script type="text/javascript" src="../jscripts/select2/select2.min.js"></script>
<script type="text/javascript">

	$(document).ready(function() {
		
		$('select[name="quickjump"]').select2({width: '400px'});
		
		var FASTyle = {},
			tid = $('input[name="tid"]'),
			title = $('input[name="title"]'),
			textarea = $('textarea[name="template"]'),
			switching = false;
			
		var use_editor = (typeof editor !== 'undefined') ? true : false;
		
		// Load switcher
		textarea.before('<div id="tabs-wrapper"><ul id="fastyle_switcher" class="tabs"></ul></div>');
		
		var switcher = $('#fastyle_switcher');
		
		// Load the current tab into the switcher
		load_button(title.val(), true);
		
		if ($('select[name="quickjump"]').length) {
			
			title.parents('form').prepend(title.clone().attr('type', 'hidden'));
			
			title.parents('tr').remove();
			
			title = $('input[name="title"]');
			
		}
			
		FASTyle.templates = {};
		
		function getUrlParameter(sParam) {
		    var sPageURL = decodeURIComponent(window.location.search.substring(1)),
		        sURLVariables = sPageURL.split('&'),
		        sParameterName,
		        i;
		
		    for (i = 0; i < sURLVariables.length; i++) {
		        sParameterName = sURLVariables[i].split('=');
		
		        if (sParameterName[0] === sParam) {
		            return sParameterName[1] === undefined ? true : sParameterName[1];
		        }
		    }
		};
		
		function replaceUrlParameter(url, paramName, paramValue) {
		    if (paramValue == null)
		        paramValue = '';
		        
		    var pattern = new RegExp('('+paramName+'=).*?(&|$)');
		    
		    if (url.search(pattern) >= 0) {
		        return url.replace(pattern, '$1' + paramValue + '$2');
		    }
		    
		    return url + (url.indexOf('?') > 0 ? '&' : '?') + paramName + '=' + paramValue;
		}
			
		function switch_to_template(name, template, id) {
			
			switching = true;
			
			load_button(name, true);
			
			switcher.find(':not(.' + name + ')').removeClass('active');
			
			title.val(name);
			tid.val(parseInt(id));
			
			// Wipe history and load the appropriate one
			if (use_editor) {
				
				editor.setValue(template);
				
				editor.clearHistory();
				
				if (typeof FASTyle.templates[name] !== 'undefined' && FASTyle.templates[name].history) {
					editor.setHistory(FASTyle.templates[name].history);
				}
				
				editor.focus();
				editor.changeGeneration();
				
			}
			else {
				textarea.val(template);
				textarea.focus();
			}
			
			// Stop the spinner
			FASTyle.templates_spinner.stop();
			
			// Update the URL
			var currentTitle = getUrlParameter('title');
			if (currentTitle != name) {
				history.replaceState(null, '', replaceUrlParameter(window.location.href, 'title', name));
			}
			
			return save_template(name, template, id);
			
		}
		
		function load_button(name, active) {
			
			// Load the button in the switcher
			var tab = $('#fastyle_switcher .' + name);
			
			var className = (active) ? ' active' : '';
			
			if (!tab.length) {
				switcher.append('<li><a class="' + name + className + '">' + name + ' <span class="close"></span></a></li>');
			}
			else if (className) {
				tab.addClass(className);
			}
			
		}
		
		function remove_button(name) {
			
			var tab = $('#fastyle_switcher .' + name);
			
			if (tab.length) {
				
				if (tab.parent('li').is(':only-child')) {
					return false;
				}
				
				var load_new = (tab.hasClass('active')) ? true : false;
				
				tab.closest('li').remove();
				
				// Switch to the first item if this is the active tab
				if (load_new) {
					load_template($('#fastyle_switcher li:first a').text());
				}
				
				return true;
				
			}
			
			return false;
			
		}
		
		function save_current_template() {
			
			var current_template = (use_editor) ? editor.getValue() : textarea.val();
			
			return save_template(title.val(), current_template, tid.val());
			
		}
		
		function save_template(name, template, tid) {
			
			FASTyle.templates[name] = {
				'tid': parseInt(tid),
				'template': template
			};
			
			if (use_editor) {
				FASTyle.templates[name].history = editor.getHistory();
			}
			
			// Add this template in the opened tabs cache
			var currentlyOpen = Cookie.get('fastyle_tabs_opened');
			var newCookie = (typeof currentlyOpen !== 'undefined' && currentlyOpen.length) ? currentlyOpen.split('|') : [name];
			
			if (newCookie.indexOf(name) == -1) {
				newCookie.push(name);
			}
			
			Cookie.set('fastyle_tabs_opened', newCookie.join('|'));
			
		}
		
		function unload_template(name) {
			
			name = name.trim();
			
			if (!remove_button(name)) {
				return false;
			}
			
			delete FASTyle.templates[name];
			
			// Delete this template from the opened tabs cache
			var currentlyOpen = Cookie.get('fastyle_tabs_opened');
			var newCookie = (typeof currentlyOpen !== 'undefined' && currentlyOpen.length) ? currentlyOpen.split('|') : '';
			
			var index = newCookie.indexOf(name);
			
			if (index > -1) {
				newCookie.splice(index, 1);
			}
			
			Cookie.set('fastyle_tabs_opened', newCookie.join('|'));
			
		}
		
		function load_template(name) {
			
			name = name.trim();
			
			var t = FASTyle.templates[name];
					
			var opts = {
				  lines: 9 // The number of lines to draw
				, length: 20 // The length of each line
				, width: 9 // The line thickness
				, radius: 19 // The radius of the inner circle
				, scale: 0.25 // Scales overall size of the spinner
				, corners: 1 // Corner roundness (0..1)
				, color: '#000' // #rgb or #rrggbb or array of colors
				, opacity: 0.25 // Opacity of the lines
				, rotate: 0 // The rotation offset
				, direction: 1 // 1: clockwise, -1: counterclockwise
				, speed: 1 // Rounds per second
				, trail: 60 // Afterglow percentage
				, fps: 20 // Frames per second when using setTimeout() as a fallback for CSS
				, zIndex: 2e9 // The z-index (defaults to 2000000000)
				, className: 'spinner' // The CSS class to assign to the spinner
				, top: '-13px' // Top position relative to parent
				, left: '-30px' // Left position relative to parent
				, shadow: false // Whether to render a shadow
				, hwaccel: false // Whether to use hardware acceleration
				, position: 'relative' // Element positioning
			}
			
			FASTyle.templates_spinner = new Spinner(opts).spin();
		    
		    // Launch the spinner
		    $('select[name="quickjump"]').after(FASTyle.templates_spinner.el);
			
			if (typeof t !== 'undefined') {
				switch_to_template(name, t.template, t.tid);
			}
			else {
				
				$.get('index.php?module=style-templates&action=edit_template&sid={$sid}&get_template_ajax=1&title=' + name, function(data) {
					
					data = JSON.parse(data);
					
					switch_to_template(name, data.template, data.tid);
					
				});
			
			}
			
		}
		
		save_current_template();
		
		// Load the previously opened tabs
		var currentlyOpen = Cookie.get('fastyle_tabs_opened');
		if (typeof currentlyOpen !== 'undefined') {
			
			currentlyOpen = currentlyOpen.split('|');
			$.each(currentlyOpen, function(k, v) {
				load_button(v);
			});
			
		}
		
		// Close tab
		$('body').on('click', '#fastyle_switcher span.close', function(e) {
			
			e.stopImmediatePropagation();
			
			var _this = $(this);
			var d = true;
			
			if (_this.parent('a').hasClass('not_saved')) {
				d = confirm('You have unsaved changes in this tab. Would you like to close it anyway?');
			}
			
			if (d) {
				unload_template(_this.parent('a').clone().children().remove().end().text());
			}
			
		});
		
		// Mark tabs as not saved when edited
		if (use_editor) {
			
			editor.on('changes', function(a, b, event) {
				
				if (!switching) {
					switcher.find('.' + title.val()).addClass('not_saved');
				}
				else {
					switching = false;
				}
				
			});
			
		}
		else {
			
			textarea.on('keydown', function(e) {
				if (e.which !== 0 && e.charCode !== 0 && !e.ctrlKey && !e.metaKey && !e.altKey) {
					switcher.find('.' + title.val()).addClass('not_saved');
				}
			});
			
		}
		
		$('body').on('click', '#fastyle_switcher a', function(e) {
			
			e.preventDefault();
			
			var name = $(this).text();
			
			save_current_template();
			
			if (name != title.val()) {
				load_template(name);	
			}
			
			return false;
			
		});
		
		$('body').on('change', 'select[name="quickjump"]', function(e) {
			
			var name = this.value;
			
			if (name.length) {
				
				save_current_template();
				
				load_template(name);
				
			}
			
		});
		
	});
</script>
HTML;
	
	return $form_container->output_row('Template name', 'Search and select a template to load it into this browser tab.', $script . $form->generate_select_box('quickjump', $templates));
}

function fastyle_build_header_template($extraHeader = '')
{
	
	return <<<HTML
<script type="text/javascript" src="jscripts/FASTyle/spin.js"></script>	
<script type="text/javascript">

(function() {
	
	var fastyle_deferred;
	
	$(document).ready(function() {
		
		var switcher = $('#fastyle_switcher'),
			tid_input = $('input[name="tid"]'),
			title_input = $('input[name="title"]');
		
		$extraHeader
	
			var pressed = $(this).find("input[type=submit]:focus").attr("name");
			
			if (pressed == "close" || pressed == "save_close") return;
		
			e.preventDefault();
			
			var button = $('.submit_button[name="continue"], .submit_button[name="save"], .form_button_wrapper > label:only-child > .submit_button');
			var button_container = button.parent();
			var button_container_html = button_container.html();
			
			// Set up the container to be as much similar to the container 
			var spinnerContainer = $('<div></div>').hide();
			
			var buttonHeight = button.outerHeight(true);
			var buttonWidth = button.outerWidth(true);
			
			spinnerContainer.css({width: buttonWidth, height: buttonHeight, position: 'relative', 'display': 'inline-block', 'vertical-align': 'top'});
		    
		    // Replace the button with the spinner container
		    button.replaceWith(spinnerContainer);
			
			var opts = {
				  lines: 9 // The number of lines to draw
				, length: 20 // The length of each line
				, width: 9 // The line thickness
				, radius: 19 // The radius of the inner circle
				, scale: 0.25 // Scales overall size of the spinner
				, corners: 1 // Corner roundness (0..1)
				, color: '#000' // #rgb or #rrggbb or array of colors
				, opacity: 0.25 // Opacity of the lines
				, rotate: 0 // The rotation offset
				, direction: 1 // 1: clockwise, -1: counterclockwise
				, speed: 1 // Rounds per second
				, trail: 60 // Afterglow percentage
				, fps: 20 // Frames per second when using setTimeout() as a fallback for CSS
				, zIndex: 2e9 // The z-index (defaults to 2000000000)
				, className: 'spinner' // The CSS class to assign to the spinner
				, top: '50%' // Top position relative to parent
				, left: '50%' // Left position relative to parent
				, shadow: false // Whether to render a shadow
				, hwaccel: false // Whether to use hardware acceleration
				, position: 'absolute' // Element positioning
			}
			
			var spinner = new Spinner(opts).spin();
			spinnerContainer.append(spinner.el);
			
			var url = $(this).attr('action') + '&ajax=1';
		    
			if (typeof fastyle_deferred === 'object' && fastyle_deferred.state() == 'pending') {
				fastyle_deferred.abort();
			}
			
			var data = $(this).serialize();
			var old_name = title_input.val();
		    
			fastyle_deferred = $.ajax({
	    		type: "POST",
	    		url: url,
	    		data: data
	    	});
			
			$.when(
				fastyle_deferred
			).done(function(d, t, response) {
				
				// Stop the spinner
				spinner.stop();
				
				// Remove the not_saved marker
				$('#fastyle_switcher .' + old_name).removeClass('not_saved');
				
				// Restore the button
				button_container.html(button_container_html);
				
				var response = JSON.parse(response.responseText);
				
				// Notify the user
				$.jGrowl(response.message);
				
				// Eventually handle the updated tid
				if (response.tid) {
					tid_input.val(response.tid);
				}
				
			});
		
		    return false;
		});
		
		// Add shortcuts
		$(window).bind('keydown', function(event) {
			
			// CTRL/CMD
		    if (event.ctrlKey || event.metaKey) {
			    
		        switch (String.fromCharCode(event.which).toLowerCase()) {
			        
			        // + S = save
			        case 's':
			            var submitButton = $('.submit_button[name="continue"], .submit_button[name="save"]');
			            if (submitButton.length) {
			            	event.preventDefault();
							submitButton.click();
						}
			            break;
			            
			        // + F = search template
			        case 'f':
			            var quickjump = $('select[name="quickjump"]');
			            if (quickjump.length) {
			            	event.preventDefault();
			            	quickjump.select2('open');
			            }
			            break;
			            
		        }
		        
		    }
		    
		    // ALT
		    if (event.altKey) {
			    
			    switch (String.fromCharCode(event.which).toLowerCase()) {
				    
			        // + W = close tab
			        case 'w':
			        	var closeButton = $('#fastyle_switcher a.active .close');
			        	if (closeButton.length) {
				            event.preventDefault();
				            closeButton.click();
				        }
			            break;
			            
		        }
		        
			}
		    
		});
	
	});

})();

</script>
<style type="text/css">

#fastyle_switcher {
	margin: 10px 0
}

#fastyle_switcher a {
	cursor: pointer
}

#fastyle_switcher .not_saved {
	border-bottom: 2px solid yellow
}

#fastyle_switcher .not_saved:before {
	content: "*"
}

#fastyle_switcher li:only-child span.close {
	display: none
}

#fastyle_switcher li span.close:after {
	content: "×";
	color: red;
	cursor: pointer
}

</style>
HTML;
	
}

function fastyle_message($data)
{
	if (!is_array($data)) {
		$data = ['message' => $data];
	}
	
	echo json_encode($data);
	
	exit;
}