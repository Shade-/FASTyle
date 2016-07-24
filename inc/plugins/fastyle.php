<?php

/**
 * Save templates and themes on the fly using the power of AJAX.
 *
 * @package FASTyle
 * @author  Shade <legend_k@live.it>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 1.3
 */

$GLOBALS['fastyle'] = array(
	'spinner_css' => '
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
  line-height: 0
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
',
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
' . $GLOBALS['fastyle']['spinner_css']);

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
		'version' => '1.3',
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
	global $cache, $PL;
	
	$PL or require_once PLUGINLIBRARY;
	
	$PL->edit_core('fastyle', 'admin/modules/style/templates.php', array(
		array(
			'search' => '$form_container->output_row($lang->template_set, $lang->template_set_desc, $form->generate_select_box(\'sid\', $template_sets, $sid));',
			'before' => '$plugins->run_hooks("admin_style_templates_edit_template_fastyle");'
		)
	), true);
	
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
	global $cache, $PL;
	
	$PL or require_once PLUGINLIBRARY;
	
	$PL->edit_core('fastyle', 'admin/modules/style/templates.php', array(), true);
	
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
	$plugins->add_hook("admin_style_templates_set", "fastyle_admin_style_templates_set");
	$plugins->add_hook("admin_load", "fastyle_get_templates");
	$plugins->add_hook("admin_style_templates_edit_template_fastyle", "fastyle_quick_templates_jump");
	
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

function fastyle_admin_style_templates_set()
{
	global $page;
	
	$page->extra_header .= <<<HTML
	
<script type="text/javascript">
	
	$(document).ready(function() {
		
		getUrlParameter = function getUrlParameter(sParam) {
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
		
		$('body').on('click', 'tr[id*="group_"] .first a', function(e) {
			
			e.preventDefault();
			
			var a = $(this);
			var url = a.attr('href');
			var string = '#group_';
			var gid = Number(url.substring(url.indexOf(string) + string.length));
			
			if (a.data('expanded') != true) {
				
				var items = $('.group' + gid);
				
				if (items.length) {
					items.show();
				}
				else {
			
					var dots = $('<span style="position: relative; left: 10px" class="spinner' + gid + '"><span class="loading"><span>•</span><span>•</span><span>•</span></span></span>');
				    
				    // Launch the spinner
				    a.after(dots);
				    
					$.ajax({
			    		type: 'GET',
			    		url: 'index.php?action=get_templates',
			    		data: {
				    		'gid': gid,
				    		'sid': Number(getUrlParameter('sid'))
				    	},
				    	success: function(data) {
					    	
					    	// Delete the spinner
					    	$('.spinner' + gid).remove();
					    	
					    	var html = $.parseJSON(data);
					    	
					    	a.parents('tr').after(html);
					    		
					    }	
			    	});
			    }
		    	
		    	a.data('expanded', true);
		    	
		    }
		    else {
				a.data('expanded', false).parents('tr').siblings('.group' + gid).hide();
			}
			
		});
		
	});
	
</script>
{$GLOBALS['fastyle']['spinner_css']}
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
	
	$prefixes = array();
	
	$where_sql = ($gid != -1) ? "gid = '$gid'" : '';
	
	$query = $db->simple_select("templategroups", "prefix", $where_sql);
	while ($prefix = $db->fetch_field($query, 'prefix')) {
		$prefixes[] = $prefix;
	}
	
	$where_sql = '';
	$multiple_prefixes = (count($prefixes) == 1);
	if ($prefixes) {
		$where_sql = ($multiple_prefixes) ? "LIKE '{$prefixes[0]}%'" : "NOT LIKE '" . implode("_%' AND title NOT LIKE '", $prefixes) . "_%'";
	}
	
	$html = array();
	
	$query = $db->simple_select("templates", "*", "(sid='{$sid}' OR sid='-2') AND title {$where_sql}", array('order_by' => 'sid DESC, title', 'order_dir' => 'ASC'));
	while ($template = $db->fetch_array($query)) {
		
		$templates[$template['sid']][$template['title']] = $template;
		
		$temp_templates[] = $template;
		
	}
	
	$lang->load('style_templates', false, true);
	$alt = ' alt_row';
	
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
	
	$templates = array('' => 'Select a template');
	
	$query = $db->simple_select('templates', 'title', "sid = '-2' OR sid = '{$sid}'");
	while ($title = $db->fetch_field($query, 'title')) {
		
		if ($templates[$title]) {
			continue;
		}
		
		$templates[$title] = $title;
		
	}
	
	ksort($templates);
	
	$script = <<<HTML
<script type="text/javascript">

	$(document).ready(function() {
		
		$('body').on('change', 'select[name="quickjump"]', function(e) {
			
			var sid = '{$sid}';
			var template = this.value;
			
			if (template.length) {
				window.location.href = window.location.href.replace(/(title=)[^\&]+/, '$1' + template);
			}
			
		});
		
	});
</script>
HTML;
	
	return $form_container->output_row('Quick jump', 'Search and select a template to quickly jump to it.', $script . $form->generate_select_box('quickjump', $templates));
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