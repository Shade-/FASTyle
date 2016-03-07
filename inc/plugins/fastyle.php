<?php

/**
 * Save templates and themes on the fly using the power of AJAX.
 *
 * @package FASTyle
 * @author  Shade <legend_k@live.it>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 1.2
 */

$GLOBALS['fastyle'] = array(
	'header' => '<script type="text/javascript">

var fastyle_deferred;
$(document).ready(function() {
',
	'footer' => '
	
		pressed = $(this).find("input[type=submit]:focus").attr("name");
		
		if (pressed == "close" || pressed == "save_close") return;
	
		e.preventDefault();
		
		var button = $(\'.submit_button[name="continue"], .submit_button[name="save"], .form_button_wrapper > label:only-child > .submit_button\');
		var button_container = button.parent();
		var button_container_html = button_container.html();
		
		// Set up the loading dots 
		var dots = $(\'<div class="loading"><span>•</span><span>•</span><span>•</span></div>\').hide();
		
		var containerHeight = button_container.outerHeight();
		var containerWidth = button_container.outerWidth();
		
		button_container.css({width: containerWidth, height: containerHeight, position: \'relative\'});
	    
	    // Launch the spinner
	    button.replaceWith(dots);
	    
		var dotsHeight = dots.outerHeight();
		var dotsWidth = dots.outerWidth();
		
		dots.css({
			top: (containerHeight / 2) - (dotsHeight / 2) + \'px\',
			left: (containerWidth / 2) - (dotsWidth / 2) + \'px\'
		}).show();
		
		var url = $(this).attr(\'action\') + \'&ajax=1\';
	    
		if (typeof fastyle_deferred === \'object\' && fastyle_deferred.state() == \'pending\') {
			fastyle_deferred.abort();
		}
	    
		fastyle_deferred =  $.ajax({
    		type: "POST",
    		url: url,
    		data: $(this).serialize()
    	});
		
		$.when(
			fastyle_deferred
		).done(function(d, t, response) {
			
			button_container.html(button_container_html).attr(\'style\', \'\');
			$.jGrowl(response.responseText);
			
		});
	
	    return false;
	});
	
	$(window).bind(\'keydown\', function(event) {
	    if (event.ctrlKey || event.metaKey) {
	        switch (String.fromCharCode(event.which).toLowerCase()) {
	        case \'s\':
	            event.preventDefault();
	            $(\'.submit_button[name="continue"], .submit_button[name="save"]\').click();
	            break;
	        }
	    }
	});

});

</script>
<style type="text/css">

@-webkit-keyframes dancing-dots-jump {
  0% { top: 0; }
  55% { top: 0; }
  60% { top: -7px; }
  80% { top: 3px; }
  90% { top: -2px; }
  95% { top: 1px; }
  100% { top: 0; }
}
.loading {
	position: absolute;
	left: 50%;
	top: 50%
}
.loading span {
  -webkit-animation-duration: 1300ms;
          animation-duration: 1300ms;
  -webkit-animation-iteration-count: infinite;
          animation-iteration-count: infinite;
  -webkit-animation-name: dancing-dots-jump;
          animation-name: dancing-dots-jump;
  -webkit-animation-delay: -700ms;
          animation-delay: -700ms;
  position: relative;
  font-size: 30px;
  color: #FF5050;
}
.loading span:nth-child(2) {
  -webkit-animation-delay: -600ms;
          animation-delay: -600ms;
  color: #FFAD53;
}
.loading span:nth-child(3) {
  -webkit-animation-delay: -400ms;
          animation-delay: -400ms;
  color: #FBFF74;
}

</style>
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
		'version' => '1.2',
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
	$plugins->add_hook("admin_config_settings_change", "fastyle_admin_config_settings_change");
	
}

function fastyle_templates_edit()
{
	global $mybb, $db, $sid, $page, $lang, $errors, $fastyle;
	
	$page->extra_header .= $fastyle['header'] . '

	$("#edit_template").submit(function(e) {
	
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
	global $mybb, $db, $theme, $lang, $page, $plugins, $stylesheet, $fastyle;
	
	$page->extra_header .= $fastyle['header'] . '

	$("#edit_stylesheet").submit(function(e) {
	
' . $fastyle['footer'];

	if ($mybb->request_method == "post" and $mybb->input['ajax']) {

		$parent_list = make_parent_theme_list($theme['tid']);
		$parent_list = implode(',', $parent_list);
		
		if (!$parent_list) {
			$parent_list = 1;
		}
	
		$query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string($mybb->input['file'])."' AND tid IN ({$parent_list})", array('order_by' => 'tid', 'order_dir' => 'desc', 'limit' => 1));
		$stylesheet = $db->fetch_array($query);
	
		// Does the theme not exist?
		if (!$stylesheet['sid']) {
			fastyle_message($lang->error_invalid_stylesheet, true);
		}
		
		$sid = $stylesheet['sid'];

		// Theme & stylesheet theme ID do not match, editing inherited - we copy to local theme
		if ($theme['tid'] != $stylesheet['tid']) {
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
		if (!cache_stylesheet($theme['tid'], $stylesheet['name'], $mybb->input['stylesheet'])) {
			$db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet={$sid}"), "sid='{$sid}'", 1);
		}

		// Update the CSS file list for this theme
		update_theme_stylesheet_list($theme['tid']);
		
		$plugins->run_hooks("admin_style_themes_edit_stylesheet_advanced_commit");

		// Log admin action
		log_admin_action(htmlspecialchars_uni($theme['name']), $stylesheet['name']);

		fastyle_message($lang->success_stylesheet_updated, true);
		
	}
}

function fastyle_themes_edit_simple()
{
	global $page, $fastyle;
	
	$page->extra_header .= $fastyle['header'] . '

	$(document).on(\'submit\', \'form[action*="edit_stylesheet"]\', function(e) {
	
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

function fastyle_admin_config_settings_change()
{
	global $mybb, $db, $page, $admin_session, $lang, $errors, $plugins, $fastyle;
	
	$page->extra_header .= $fastyle['header'] . '

	$("#change").submit(function(e) {
	
' . $fastyle['footer'];

	if ($mybb->request_method == "post" and $mybb->input['ajax']) {
		
		if (!is_writable(MYBB_ROOT.'inc/settings.php')) {
			$errors[] = $lang->error_chmod_settings_file;
		}

		// If we are changing the hidden captcha, make sure it doesn't conflict with another registration field
		if (isset($mybb->input['upsetting']['hiddencaptchaimagefield'])) {
			
			// Not allowed to be hidden captcha fields
			$disallowed_fields = array(
				'username',
				'password',
				'password2',
				'email',
				'email2',
				'imagestring',
				'allownotices',
				'hideemail',
				'receivepms',
				'pmnotice',
				'emailpmnotify',
				'invisible',
				'subscriptionmethod',
				'timezoneoffset',
				'dstcorrection',
				'language',
				'step',
				'action',
				'regsubmit'
			);

			if (in_array($mybb->input['upsetting']['hiddencaptchaimagefield'], $disallowed_fields)) {
				// Whoopsies, you can't do that!
				$errors[] = $lang->sprintf($lang->error_hidden_captcha_conflict, htmlspecialchars_uni($mybb->input['upsetting']['hiddencaptchaimagefield']));
			}
		}

		// Get settings which optionscode is a forum/group select
		// We cannot rely on user input to decide this
		if (!$errors) {
			
			$forum_group_select = array();
			$query = $db->simple_select('settings', 'name', 'optionscode IN (\'forumselect\', \'groupselect\')');
			
			while ($name = $db->fetch_field($query, 'name')) {
				$forum_group_select[] = $name;
			}
	
			if (is_array($mybb->input['upsetting'])) {
				
				foreach ($mybb->input['upsetting'] as $name => $value) {
					
					if (!empty($forum_group_select) and in_array($name, $forum_group_select)) {
						
						if ($value == 'all') {
							$value = -1;
						}
						else if ($value == 'custom') {
							
							if (isset($mybb->input['select'][$name]) and is_array($mybb->input['select'][$name])) {
								
								foreach ($mybb->input['select'][$name] as &$val) {
									$val = (int)$val;
								}
								
								unset($val);
	
								$value = implode(',', (array)$mybb->input['select'][$name]);
								
							}
							else {
								$value = '';
							}
						}
						else {
							$value = '';
						}
					}
	
					$value = $db->escape_string($value);
					$db->update_query("settings", array('value' => $value), "name='".$db->escape_string($name)."'");
					
				}
				
			}
	
			// Check if we need to create our fulltext index after changing the search mode
			if ($mybb->settings['searchtype'] != $mybb->input['upsetting']['searchtype'] and $mybb->input['upsetting']['searchtype'] == "fulltext") {
				
				if (!$db->is_fulltext("posts") and $db->supports_fulltext_boolean("posts")) {
					$db->create_fulltext_index("posts", "message");
				}
				
				if (!$db->is_fulltext("posts") and $db->supports_fulltext("threads")) {
					$db->create_fulltext_index("threads", "subject");
				}
				
			}
	
			// If the delayedthreadviews setting was changed, enable or disable the tasks for it.
			if (isset($mybb->input['upsetting']['delayedthreadviews']) and $mybb->settings['delayedthreadviews'] != $mybb->input['upsetting']['delayedthreadviews']) {
				
				if ($mybb->input['upsetting']['delayedthreadviews'] == 0) {
					$updated_task = array(
						"enabled" => 0
					);
				}
				else {
					$updated_task = array(
						"enabled" => 1
					);
				}
				
				$db->update_query("tasks", $updated_task, "file='threadviews'");
				
			}
	
			// Have we changed our cookie prefix? If so, update our adminsid so we're not logged out
			if ($mybb->input['upsetting']['cookieprefix'] and $mybb->input['upsetting']['cookieprefix'] != $mybb->settings['cookieprefix']) {
				
				my_unsetcookie("adminsid");
				$mybb->settings['cookieprefix'] = $mybb->input['upsetting']['cookieprefix'];
				my_setcookie("adminsid", $admin_session['sid'], '', true);
				
			}
	
			// Have we opted for a reCAPTCHA and not set a public/private key?
			if ($mybb->input['upsetting']['captchaimage'] == 2 and !$mybb->input['upsetting']['captchaprivatekey'] and !$mybb->input['upsetting']['captchapublickey']) {
				$db->update_query("settings", array("value" => 1), "name = 'captchaimage'");
			}
	
			rebuild_settings();
	
			$plugins->run_hooks("admin_config_settings_change_commit");
	
			// If we have changed our report reasons recache them
			if (isset($mybb->input['upsetting']['reportreasons'])) {
				$cache->update_reportedposts();
			}

			// Log admin action
			log_admin_action();

			fastyle_message($lang->success_settings_updated);
			
		}
		else {
			fastyle_message($errors);
		}
		
		exit;
		
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