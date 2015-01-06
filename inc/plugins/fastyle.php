<?php

/**
 * Save templates and themes on the fly using the power of AJAX.
 *
 * @package FASTyle
 * @author  Shade <legend_k@live.it>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 1.1
 */

$GLOBALS['fastyle'] = array(
	'header' => '<script type="text/javascript">

$(document).ready(function() {
',
	'footer' => '
		button_container = button.parent();
		button_container_html = button_container.html();
		spinner = "<img src=\"../images/spinner.gif\" style=\"vertical-align: middle;\" alt=\"\" /> ";
	
		e.preventDefault();
		
	    url = $(this).attr(\'action\') + \'&ajax=1\';
	    
	    // Go, spinner!
	    button.replaceWith(spinner);
	
	    $.ajax({
	           type: "POST",
	           url: url,
	           data: $(this).serialize(),
	           complete: function(data)
	           {
	               button_container.html(button_container_html);
	               $.jGrowl(data.responseText);
	           }
	         });
	
	    return false;
	});
	
	$(window).bind(\'keydown\', function(event) {
	    if (event.ctrlKey || event.metaKey) {
	        switch (String.fromCharCode(event.which).toLowerCase()) {
	        case \'s\':
	            event.preventDefault();
	            $(form).submit();
	            break;
	        }
	    }
	});

});

</script>
');

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

function fastyle_info()
{
	return array(
		'name' => 'FASTyle',
		'description' => 'Save templates and themes on the fly using the power of AJAX.',
		'author' => 'Shade',
		'version' => '1.1',
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
	$plugins->add_hook("admin_style_themes_edit_stylesheet_advanced", "fastyle_themes_edit_advanced");
	$plugins->add_hook("admin_style_themes_edit_stylesheet_simple", "fastyle_themes_edit_simple");
	$plugins->add_hook("admin_style_themes_edit_stylesheet_simple_commit", "fastyle_themes_edit_simple_commit");
	
}

function fastyle_templates_edit()
{
	global $mybb, $db, $sid, $page, $lang, $errors, $fastyle;
	
	$page->extra_header .= $fastyle['header'] . '
	
	form = "#edit_template";

	$(form).submit(function(e) {
		
		button = $(\'.submit_button[name="continue"]\');
	
' . $fastyle['footer'];
	
	if ($mybb->request_method == 'post' and $mybb->input['ajax']) {
	
		if (empty($mybb->input['title'])) {
			$errors[] = $lang->error_missing_title;
		}
	
		// Are we trying to do malicious things in our template?
		if (check_template($mybb->input['template'])) {
			$errors[] = $lang->error_security_problem;
		}
	
		if (!$errors) {
		
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
	
			if ($sid > 0) {
			
				// Check to see if it's never been edited before (i.e. master) or if this a new template (i.e. we've renamed it)  or if it's a custom template
				$query = $db->simple_select("templates", "sid", "title='".$db->escape_string($mybb->input['title'])."' AND (sid = '-2' OR sid = '{$sid}' OR sid='{$template['sid']}')", array('order_by' => 'sid', 'order_dir' => 'desc'));
				$existing_sid = $db->fetch_field($query, "sid");
				$existing_rows = $db->num_rows($query);
				
				if (($existing_sid == -2 and $existing_rows == 1) or $existing_rows == 0) {
					$template['tid'] = $db->insert_query("templates", $template_array);
				}
				else {
					$db->update_query("templates", $template_array, "tid='{$template['tid']}' AND sid != '-2'");
				}
				
			}
			else {
				// Global template set
				$db->update_query("templates", $template_array, "tid='{$template['tid']}' AND sid != '-2'");
			}
	
			$query = $db->simple_select("templatesets", "title", "sid='{$sid}'");
			$set = $db->fetch_array($query);
	
			$exploded = explode("_", $template_array['title'], 2);
			$prefix = $exploded[0];
	
			$query = $db->simple_select("templategroups", "gid", "prefix = '".$db->escape_string($prefix)."'");
			$group = $db->fetch_field($query, "gid");
	
			if (!$group) {
				$group = "-1";
			}
	
			// Log admin action
			log_admin_action($template['tid'], $mybb->input['title'], $mybb->input['sid'], $set['title']);
			
			fastyle_message($lang->success_template_saved);
			
		}
		else {
			fastyle_message($errors);
		}
		
		exit;
		
	}
	
}

function fastyle_themes_edit_advanced()
{
	global $mybb, $db, $theme, $lang, $page, $fastyle;
	
	$page->extra_header .= $fastyle['header'] . '
	
	form = "#edit_stylesheet";

	$(form).submit(function(e) {
		
		button = $(\'.submit_button[name="save"]\');
	
' . $fastyle['footer'];

	if($mybb->request_method == "post" and $mybb->input['ajax']) {

		$parent_list = make_parent_theme_list($theme['tid']);
		$parent_list = implode(',', $parent_list);
		
		if (!$parent_list) {
			$parent_list = 1;
		}
	
		$query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string($mybb->input['file'])."' AND tid IN ({$parent_list})", array('order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1));
		$stylesheet = $db->fetch_array($query);
	
		// Does the theme not exist?
		if(!$stylesheet['sid']) {
			fastyle_message($lang->error_invalid_stylesheet, true);
		}
		
		$sid = $stylesheet['sid'];

		// Theme & stylesheet theme ID do not match, editing inherited - we copy to local theme
		if($theme['tid'] != $stylesheet['tid']) {
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
		if(!cache_stylesheet($theme['tid'], $stylesheet['name'], $mybb->input['stylesheet'])) {
			$db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet={$sid}"), "sid='{$sid}'", 1);
		}

		// Update the CSS file list for this theme
		update_theme_stylesheet_list($theme['tid']);

		// Log admin action
		log_admin_action(htmlspecialchars_uni($theme['name']), $stylesheet['name']);

		fastyle_message($lang->success_stylesheet_updated, true);
		
	}

}

function fastyle_themes_edit_simple()
{
	global $page, $fastyle;
	
	$page->extra_header .= $fastyle['header'] . '

	form = \'form[action*="edit_stylesheet"]\';

	$(document).on(\'submit\', form, function(e) {
		
		button = $(\'.submit_button[name="save"]\');
	
' . $fastyle['footer'];

}

function fastyle_themes_edit_simple_commit()
{
	global $mybb, $lang, $theme, $stylesheet;
	
	// Log admin action
	log_admin_action(htmlspecialchars_uni($theme['name']), $stylesheet['name']);
	
	if ($mybb->input['ajax']) {
		fastyle_message($lang->success_stylesheet_updated, true);
	}

}

function fastyle_message($messages, $exit = false)
{

	if (!is_array($messages) or count($messages) == 1) {
		
		if (is_array($messages)) {
			echo $messages[0];
		}
		else {
			echo $messages;
		}
		
	}
	else {
	
		foreach($messages as $message)
		{
			echo "<li>{$message}</li>\n";
		}
		
	}
	
	if ($exit) {
		exit;
	}
	
	return;

}