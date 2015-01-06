<?php

/**
 * Save templates and themes on the fly using the power of AJAX.
 *
 * @package FASTyle
 * @author  Shade <legend_k@live.it>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 1.0
 */

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined("PLUGINLIBRARY")) {
	define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

function fastyle_info()
{
	return array(
		'name' => 'FASTyle',
		'description' => 'Save templates and themes on the fly using the power of AJAX.',
		'author' => 'Shade',
		'version' => '1.0',
		'compatibility' => '18*'
	);
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
	global $cache;
	
	// Create cache
	$info                        = fastyle_info();
	$shadePlugins                = $cache->read('shade_plugins');
	$shadePlugins[$info['name']] = array(
		'title' => $info['name'],
		'version' => $info['version']
	);
	
	$cache->update('shade_plugins', $shadePlugins);
	
}

function fastyle_uninstall()
{
	global $cache;
	
	// Delete the plugin from cache
	$info         = fastyle_info();
	$shadePlugins = $cache->read('shade_plugins');
	unset($shadePlugins[$info['name']]);
	$cache->update('shade_plugins', $shadePlugins);
	
}

// Hooks
if (defined('IN_ADMINCP')) {
	
	$plugins->add_hook("admin_style_templates_edit_template", "fastyle_templates_edit");
	$plugins->add_hook("admin_style_themes_edit_stylesheet_advanced", "fastyle_themes_edit");
	
}

function fastyle_templates_edit()
{
	global $mybb, $db, $sid, $page, $lang, $errors;
	
	$page->extra_header .= <<<HTML
<script type="text/javascript">

$(document).ready(function() {

	$("#edit_template").submit(function() {
		
		button = $(document.activeElement);
		button_name = '';
	
	    if (button.length && $(this).has(button) && button.is('input[type="submit"]') && button.is('[name]')) {
	        button_name = button.attr('name');
	    }
		
		if (button_name == 'close') {
			return;
		}
		
	    url = $(this).attr('action') + '&ajax=1';
	
	    $.ajax({
	           type: "POST",
	           url: url,
	           data: $(this).serialize(),
	           success: function(data)
	           {
	               $.jGrowl(data);
	           }
	         });
	
	    return false;
	});

});

</script>
HTML;
	
	if ($mybb->request_method == 'post' && $mybb->input['ajax']) {
	
		if(empty($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}
	
		// Are we trying to do malicious things in our template?
		if(check_template($mybb->input['template']))
		{
			$errors[] = $lang->error_security_problem;
		}
	
		if(!$errors)
		{
			$query = $db->simple_select("templates", "*", "tid='{$mybb->input['tid']}'");
			$template = $db->fetch_array($query);
	
			$template_array = array(
				'title' => $db->escape_string($mybb->input['title']),
				'sid' => $sid,
				'template' => $db->escape_string(rtrim($mybb->input['template'])),
				'version' => $mybb->version_code,
				'status' => '',
				'dateline' => TIME_NOW
			);
	
			// Make sure we have the correct tid associated with this template. If the user double submits then the tid could originally be the master template tid, but because the form is sumbitted again, the tid doesn't get updated to the new modified template one. This then causes the master template to be overwritten
			$query = $db->simple_select("templates", "tid", "title='".$db->escape_string($template['title'])."' AND (sid = '-2' OR sid = '{$template['sid']}')", array('order_by' => 'sid', 'order_dir' => 'desc', 'limit' => 1));
			$template['tid'] = $db->fetch_field($query, "tid");
	
			if($sid > 0)
			{
				// Check to see if it's never been edited before (i.e. master) or if this a new template (i.e. we've renamed it)  or if it's a custom template
				$query = $db->simple_select("templates", "sid", "title='".$db->escape_string($mybb->input['title'])."' AND (sid = '-2' OR sid = '{$sid}' OR sid='{$template['sid']}')", array('order_by' => 'sid', 'order_dir' => 'desc'));
				$existing_sid = $db->fetch_field($query, "sid");
				$existing_rows = $db->num_rows($query);
	
				if(($existing_sid == -2 && $existing_rows == 1) || $existing_rows == 0)
				{
					$template['tid'] = $db->insert_query("templates", $template_array);
				}
				else
				{
					$db->update_query("templates", $template_array, "tid='{$template['tid']}' AND sid != '-2'");
				}
			}
			else
			{
				// Global template set
				$db->update_query("templates", $template_array, "tid='{$template['tid']}' AND sid != '-2'");
			}
	
			$query = $db->simple_select("templatesets", "title", "sid='{$sid}'");
			$set = $db->fetch_array($query);
	
			$exploded = explode("_", $template_array['title'], 2);
			$prefix = $exploded[0];
	
			$query = $db->simple_select("templategroups", "gid", "prefix = '".$db->escape_string($prefix)."'");
			$group = $db->fetch_field($query, "gid");
	
			if(!$group)
			{
				$group = "-1";
			}
	
			// Log admin action
			log_admin_action($template['tid'], $mybb->input['title'], $mybb->input['sid'], $set['title']);
			
			echo $lang->success_template_saved;
			
			exit;
	
		}
	}
		
	return;
	
}

function fastyle_themes_edit()
{
	global $mybb, $db, $theme, $lang, $page;
	
	$page->extra_header .= <<<HTML
<script type="text/javascript">

$(document).ready(function() {

	$("#edit_stylesheet").submit(function(e) {
		
		button = $(document.activeElement);
		button_name = '';
	
	    if (button.length && $(this).has(button) && button.is('input[type="submit"]') && button.is('[name]')) {
	        button_name = button.attr('name');
	    }
		
		if (button_name == 'save_close') {
			return;
		}
	
		e.preventDefault();
		
	    url = $(this).attr('action') + '&ajax=1';
	
	    $.ajax({
	           type: "POST",
	           url: url,
	           data: $(this).serialize(),
	           success: function(data)
	           {
	               $.jGrowl(data);
	           }
	         });
	});

});

</script>
HTML;

	if($mybb->request_method == "post" && $mybb->input['ajax'])
	{

		$parent_list = make_parent_theme_list($theme['tid']);
		$parent_list = implode(',', $parent_list);
		if(!$parent_list)
		{
			$parent_list = 1;
		}
	
		$query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string($mybb->input['file'])."' AND tid IN ({$parent_list})", array('order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1));
		$stylesheet = $db->fetch_array($query);
	
		// Does the theme not exist?
		if(!$stylesheet['sid'])
		{
			echo $lang->error_invalid_stylesheet;
			exit;			
		}
		
		$sid = $stylesheet['sid'];

		// Theme & stylesheet theme ID do not match, editing inherited - we copy to local theme
		if($theme['tid'] != $stylesheet['tid'])
		{
			$sid = copy_stylesheet_to_theme($stylesheet, $theme['tid']);
		}

		// Now we have the new stylesheet, save it
		$updated_stylesheet = array(
			"cachefile" => $db->escape_string($stylesheet['name']),
			"stylesheet" => $db->escape_string(unfix_css_urls($mybb->input['stylesheet'])),
			"lastmodified" => TIME_NOW
		);
		$db->update_query("themestylesheets", $updated_stylesheet, "sid='{$sid}'");

		// Cache the stylesheet to the file
		if(!cache_stylesheet($theme['tid'], $stylesheet['name'], $mybb->input['stylesheet']))
		{
			$db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet={$sid}"), "sid='{$sid}'", 1);
		}

		// Update the CSS file list for this theme
		update_theme_stylesheet_list($theme['tid']);

		// Log admin action
		log_admin_action(htmlspecialchars_uni($theme['name']), $stylesheet['name']);

		echo $lang->success_stylesheet_updated;
		
		exit;
		
	}

}