<?php

/**
 * An all-in-one utility to improve and speed up stylesheets, settings and templates management.
 *
 * @package FASTyle
 * @author  Shade <legend_k@live.it>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 2.0
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
		'description' => 'An all-in-one utility to improve and speed up stylesheets, settings and templates management.',
		'author' => 'Shade',
		'authorsite' => 'https://www.mybboost.com',
		'version' => '2.0',
		'codename' => 'fastyle',
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
	$plugins->add_hook("admin_style_themes_edit_stylesheet_advanced_commit", "fastyle_themes_edit_advanced_commit");
	$plugins->add_hook("admin_config_settings_change", "fastyle_admin_config_settings_change", 1000);
	$plugins->add_hook("admin_config_settings_change_commit", "fastyle_admin_config_settings_change_commit");
		
	// Custom module
	$plugins->add_hook("admin_style_menu", "fastyle_admin_style_menu");
	$plugins->add_hook("admin_style_action_handler", "fastyle_admin_style_action_handler");
	
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
	global $template, $mybb, $set, $lang, $errors;
	
	if ($mybb->input['ajax']) {
		
		$lang->load('fastyle');
	
		log_admin_action($template['tid'], $mybb->input['title'], $mybb->input['sid'], $set['title']);
		
		$data = [
			'message' => $lang->sprintf($lang->fastyle_success_template_saved, $template['title'])
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
		
		fastyle_message($lang->sprintf($lang->fastyle_success_stylesheet_updated, $stylesheet['name']));
		
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
<script type="text/javascript" src="jscripts/FASTyle/spin.js"></script>
<script type="text/javascript" src="jscripts/FASTyle/main.js"></script>
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

function fastyle_message($data)
{
	if (!is_array($data)) {
		$data = ['message' => $data];
	}
	
	echo json_encode($data);
	
	exit;
}

function fastyle_admin_style_menu($sub_menu)
{
	global $lang;
	
	$lang->load("fastyle");
	
	$sub_menu[] = [
		"id" => "fastyle",
		"title" => $lang->fastyle,
		"link" => "index.php?module=style-fastyle"
	];
	
	return $sub_menu;
}

function fastyle_admin_style_action_handler($actions)
{
	$actions['fastyle'] = array(
		"active" => "fastyle",
		"file" => "fastyle.php"
	);
	
	return $actions;
}