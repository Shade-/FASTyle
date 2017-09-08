<?php

/**
 * An all-in-one utility to improve and speed up stylesheets, settings and templates management.
 *
 * @package FASTyle
 * @author  Shade <legend_k@live.it>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 * @version 1.7
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
		'version' => '1.7',
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
	$plugins->add_hook("admin_style_themes_edit_stylesheet_advanced_commit", "fastyle_themes_edit_advanced_commit");
	$plugins->add_hook("admin_style_themes_edit_stylesheet_simple", "fastyle_themes_edit_simple");
	$plugins->add_hook("admin_style_themes_edit_stylesheet_simple_commit", "fastyle_themes_edit_simple_commit");
	$plugins->add_hook("admin_config_settings_change", "fastyle_admin_config_settings_change", 1000);
	$plugins->add_hook("admin_config_settings_change_commit", "fastyle_admin_config_settings_change_commit");
	$plugins->add_hook("admin_style_templates_set", "fastyle_admin_style_templates_set");
	$plugins->add_hook("admin_load", "fastyle_get_templates");
	$plugins->add_hook("admin_style_templates_edit_template_fastyle", "fastyle_quick_templates_jump");
	$plugins->add_hook("admin_page_output_footer", "fastyle_codemirror_sublime");
	
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
	
	$page->extra_header .= fastyle_load_javascript();

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
	global $mybb, $db, $lang, $page, $theme;
	
	$page->extra_header .= fastyle_load_javascript();

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
		
	}
}

function fastyle_themes_edit_advanced_commit()
{
	global $mybb, $theme, $lang;

	if ($mybb->request_method == "post" and $mybb->input['ajax']) {
	
		log_admin_action(htmlspecialchars_uni($theme['name']), $stylesheet['name']);
		
		fastyle_message($lang->success_stylesheet_updated);
		
	}
}

function fastyle_themes_edit_simple()
{
	global $page;
	
	$page->extra_header .= fastyle_load_javascript();

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

function fastyle_admin_style_templates_set()
{
	global $page;
	
	$page->extra_header .= fastyle_load_javascript('templatelist');
	
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
	
	$fastyle_scripts = fastyle_load_javascript('templates');
	
	$script = <<<HTML
<link rel="stylesheet" href="../jscripts/select2/select2.css" type="text/css" />
<script type="text/javascript" src="../jscripts/select2/select2.min.js"></script>
<script type="text/javascript">
FASTyle.sid = {$sid};
</script>
$fastyle_scripts
<style type="text/css">

#fastyle_switcher {
	margin: 0 0 10px;
	height: auto
}

ul.tabs li {
	margin-top: 5px
}

ul.tabs li a {
	border: 1px solid transparent
}

ul.tabs li a.active {
	border-radius: 5px;
	-moz-border-radius: 5px;
	-webkit-border-radius: 5px;
	margin: 0
}

#fastyle_switcher:after {
	content: '';
	display: block;
	clear: both
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
	
	return $form_container->output_row('Template name', 'Search and select a template to load it into this browser tab.', $script . $form->generate_select_box('quickjump', $templates));
}

function fastyle_load_javascript($type = '')
{
	static $loaded;
	
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
	FASTyle.init('$type');
});

</script>
HTML;
	
	return $html;
	
}

function fastyle_codemirror_sublime(&$args)
{
	global $mybb;
	
	if (in_array($mybb->input['action'], ['edit_template', 'add_template', 'edit_stylesheet', 'add_stylesheet'])) {
		echo <<<HTML
<script type="text/javascript" src="jscripts/FASTyle/sublime.js"></script>
<script type="text/javascript">
	if (typeof editor !== 'undefined') {
		editor.setOption('keyMap', 'sublime');
	}
</script>
HTML;
	}
}

function fastyle_message($data)
{
	if (!is_array($data)) {
		$data = ['message' => $data];
	}
	
	echo json_encode($data);
	
	exit;
}