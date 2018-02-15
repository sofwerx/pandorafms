<?php

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2010 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation for version 2.
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// Load global vars
global $config;

check_login ();

enterprise_hook('open_meta_frame');

if (! check_acl ($config['id_user'], 0, "PM")) {
	db_pandora_audit("ACL Violation",
		"Trying to access Agent Management");
	require ("general/noaccess.php");
	exit;
}

require_once ($config['homedir'] . '/include/functions_network_components.php');
include_once($config['homedir'] . "/include/functions_categories.php");
enterprise_include_once ('meta/include/functions_components_meta.php');
require_once ($config['homedir'].'/include/functions_component_groups.php');

// Header
if (defined('METACONSOLE')) {
	components_meta_print_header();
	$sec = 'advanced';
}
else {
	
	/* Hello there! :)

We added some of what seems to be "buggy" messages to the openSource version recently. This is not to force open-source users to move to the enterprise version, this is just to inform people using Pandora FMS open source that it requires skilled people to maintain and keep it running smoothly without professional support. This does not imply open-source version is limited in any way. If you check the recently added code, it contains only warnings and messages, no limitations except one: we removed the option to add custom logo in header. In the Update Manager section, it warns about the 'danger’ of applying automated updates without a proper backup, remembering in the process that the Enterprise version comes with a human-tested package. Maintaining an OpenSource version with more than 500 agents is not so easy, that's why someone using a Pandora with 8000 agents should consider asking for support. It's not a joke, we know of many setups with a huge number of agents, and we hate to hear that “its becoming unstable and slow” :(

You can of course remove the warnings, that's why we include the source and do not use any kind of trick. And that's why we added here this comment, to let you know this does not reflect any change in our opensource mentality of does the last 14 years.

*/
	
	ui_print_page_header (__('Module management') . ' &raquo; ' .
		__('Network component management'), "", false,
		"network_component", true,"sell",true,"modulemodal");
	$sec = 'gmodules';
}


$type = (int) get_parameter ('type');
$name = (string) get_parameter ('name');
$description = (string) get_parameter ('description');
$max = (int) get_parameter ('max');
$min = (int) get_parameter ('min');
$tcp_send = (string) get_parameter ('tcp_send');
$tcp_rcv = (string) get_parameter ('tcp_rcv');
$tcp_port = (int) get_parameter ('tcp_port');
$snmp_oid = (string) get_parameter ('snmp_oid');
$snmp_community = (string) get_parameter ('snmp_community');
$id_module_group = (int) get_parameter ('id_module_group');
$module_interval = (int) get_parameter ('module_interval');
$id_group = (int) get_parameter ('id_group');
$plugin_user = (string) get_parameter ('plugin_user');
$plugin_pass = io_input_password((string) get_parameter ('plugin_pass'));
$plugin_parameter = (string) get_parameter ('plugin_parameter');
$macros = (string) get_parameter ('macros');

if (!empty($macros)) {
	$macros = json_decode(base64_decode($macros), true);
	
	foreach($macros as $k => $m) {
		$macros[$k]['value'] = get_parameter($m['macro'], '');
	}
	
	$macros = io_json_mb_encode($macros);
}

$max_timeout = (int) get_parameter ('max_timeout');
$max_retries = (int) get_parameter ('max_retries');
$id_modulo = (int) get_parameter ('id_component_type');
$id_plugin = (int) get_parameter ('id_plugin');
$min_warning = (float) get_parameter ('min_warning');
$max_warning = (float) get_parameter ('max_warning');
$str_warning = (string) get_parameter ('str_warning');
$min_critical = (float) get_parameter ('min_critical');
$max_critical = (float) get_parameter ('max_critical');
$str_critical = (string) get_parameter ('str_critical');
$ff_event = (int) get_parameter ('ff_event');
$history_data = (bool) get_parameter ('history_data');

// Don't read as (float) because it lost it's decimals when put into MySQL
// where are very big and PHP uses scientific notation, p.e:
// 1.23E-10 is 0.000000000123
	
$post_process = (string) get_parameter ('post_process', 0.0);

$unit = (string) get_parameter('unit');
$id = (int) get_parameter ('id');
$wizard_level = get_parameter ('wizard_level', 'nowizard');
$critical_instructions = (string) get_parameter('critical_instructions');
$warning_instructions = (string) get_parameter('warning_instructions');
$unknown_instructions = (string) get_parameter('unknown_instructions');
$critical_inverse = (int) get_parameter('critical_inverse');
$warning_inverse = (int) get_parameter('warning_inverse');
$id_category = (int) get_parameter('id_category');
$id_tag_selected = (array) get_parameter('id_tag_selected');
$pure = get_parameter('pure', 0);
$ff_event_normal = (int) get_parameter ('ff_event_normal');
$ff_event_warning = (int) get_parameter ('ff_event_warning');
$ff_event_critical = (int) get_parameter ('ff_event_critical');
$each_ff = (int) get_parameter ('each_ff');

if (count($id_tag_selected) == 1 && empty($id_tag_selected[0])) {
	$tags = '';
}
else {
	$tags = implode(',',$id_tag_selected);
}

$snmp_version = (string) get_parameter('snmp_version');
$snmp3_auth_user = (string) get_parameter('snmp3_auth_user');
$snmp3_auth_pass = io_input_password((string) get_parameter('snmp3_auth_pass'));
$snmp3_auth_method = (string) get_parameter('snmp3_auth_method');
$snmp3_privacy_method = (string) get_parameter('snmp3_privacy_method');
$snmp3_privacy_pass = io_input_password((string) get_parameter('snmp3_privacy_pass'));
$snmp3_security_level = (string) get_parameter('snmp3_security_level');


$throw_unknown_events = get_parameter('throw_unknown_events', false);
//Set the event type that can show.
$disabled_types_event = array(EVENTS_GOING_UNKNOWN => (int)$throw_unknown_events);
$disabled_types_event = json_encode($disabled_types_event);

$create_component = (bool) get_parameter ('create_component');
$update_component = (bool) get_parameter ('update_component');
$delete_component = (bool) get_parameter ('delete_component');
$new_component = (bool) get_parameter ('new_component');
$duplicate_network_component = (bool) get_parameter ('duplicate_network_component');
$delete_multiple = (bool) get_parameter('delete_multiple');
$multiple_delete = (bool)get_parameter('multiple_delete', 0);
$create_network_from_module = (bool) get_parameter ('create_network_from_module', 0);
$create_network_from_snmp_browser = (bool)get_parameter('create_network_from_snmp_browser', 0);

if ($duplicate_network_component) {
	$source_id = (int) get_parameter ('source_id');
	
	$id = network_components_duplicate_network_component ($source_id);
	ui_print_result_message ($id,
		__('Successfully created from %s',
			network_components_get_name ($source_id)),
		__('Could not be created'));
	
	//List unset for jump the bug in the pagination (TODO) that the make another
	//copy for each pass into pages.
	unset($_GET['source_id']);
	unset($_GET['duplicate_network_component']);
	
	$id = 0;
}

if ($create_component) {
	
	$custom_string_1 = '';
	$custom_string_2 = '';
	$custom_string_3 = '';
	$name_check = db_get_value ('name', 'tnetwork_component', 'name',
		$name);
	
	//remote_snmp = 15
	//remote_snmp_proc = 18
	if ($type >= 15 && $type <= 18) {
		// New support for snmp v3
		$tcp_send = $snmp_version;
		$plugin_user = $snmp3_auth_user;
		$plugin_pass = $snmp3_auth_pass;
		$plugin_parameter = $snmp3_auth_method;
		$custom_string_1 = $snmp3_privacy_method;
		$custom_string_2 = $snmp3_privacy_pass;
		$custom_string_3 = $snmp3_security_level;
		$name_check = db_get_value ('name', 'tnetwork_component',
			'name', $name);
	}
	
	if ($name && !$name_check) {
		
		$id = network_components_create_network_component ($name,
			$type,
			$id_group, 
			array ('description' => $description,
				'module_interval' => $module_interval,
				'max' => $max,
				'min' => $min,
				'tcp_send' => $tcp_send,
				'tcp_rcv' => $tcp_rcv,
				'tcp_port' => $tcp_port,
				'snmp_oid' => $snmp_oid,
				'snmp_community' => $snmp_community,
				'id_module_group' => $id_module_group,
				'id_modulo' => $id_modulo,
				'id_plugin' => $id_plugin,
				'plugin_user' => $plugin_user,
				'plugin_pass' => $plugin_pass,
				'plugin_parameter' => $plugin_parameter,
				'macros' => $macros,
				'max_timeout' => $max_timeout,
				'max_retries' => $max_retries,
				'history_data' => $history_data,
				'min_warning' => $min_warning,
				'max_warning' => $max_warning,
				'str_warning' => $str_warning,
				'min_critical' => $min_critical,
				'max_critical' => $max_critical,
				'str_critical' => $str_critical,
				'min_ff_event' => $ff_event,
				'custom_string_1' => $custom_string_1,
				'custom_string_2' => $custom_string_2,
				'custom_string_3' => $custom_string_3,
				'post_process' => $post_process,
				'unit' => $unit,
				'wizard_level' => $wizard_level,
				'macros' => $macros,
				'critical_instructions' => $critical_instructions,
				'warning_instructions' => $warning_instructions,
				'unknown_instructions' => $unknown_instructions,
				'critical_inverse' => $critical_inverse,
				'warning_inverse' => $warning_inverse,
				'id_category' => $id_category,
				'tags' => $tags,
				'disabled_types_event' => $disabled_types_event,
				'min_ff_event_normal' => $ff_event_normal,
				'min_ff_event_warning' => $ff_event_warning,
				'min_ff_event_critical' => $ff_event_critical,
				'each_ff' => $each_ff));
	}
	else {
		$id = '';
	}
	
	if ($id === false || !$id) {
		db_pandora_audit("Module management", "Fail try to create network component");
		ui_print_error_message (__('Could not be created'));
		include_once ('godmode/modules/manage_network_components_form.php');
		return;
	}
	db_pandora_audit("Module management", "Create network component #$id");
	ui_print_success_message (__('Created successfully'));
	$id = 0;
}

if ($update_component) {
	$id = (int) get_parameter ('id');
	
	$custom_string_1 = '';
	$custom_string_2 = '';
	$custom_string_3 = '';
	
	//$name_check = db_get_value ('name', 'tnetwork_component', 'name', $name);
	if ($type >= 15 && $type <= 18) {
		// New support for snmp v3
		$tcp_send = $snmp_version;
		$plugin_user = $snmp3_auth_user;
		$plugin_pass = $snmp3_auth_pass;
		$plugin_parameter = $snmp3_auth_method;
		$custom_string_1 = $snmp3_privacy_method;
		$custom_string_2 = $snmp3_privacy_pass;
		$custom_string_3 = $snmp3_security_level;
		//$name_check = db_get_value ('name', 'tnetwork_component', 'name', $name);
	}
	
	if (!empty($name)) { 
		$result = network_components_update_network_component ($id,
			array ('type' => $type,
				'name' => $name,
				'id_group' => $id_group,
				'description' => $description,
				'module_interval' => $module_interval,
				'max' => $max,
				'min' => $min,
				'tcp_send' => $tcp_send,
				'tcp_rcv' => $tcp_rcv,
				'tcp_port' => $tcp_port,
				'snmp_oid' => $snmp_oid,
				'snmp_community' => $snmp_community,
				'id_module_group' => $id_module_group,
				'id_modulo' => $id_modulo,
				'id_plugin' => $id_plugin,
				'plugin_user' => $plugin_user,
				'plugin_pass' => $plugin_pass,
				'plugin_parameter' => $plugin_parameter,
				'macros' => $macros,
				'max_timeout' => $max_timeout,
				'max_retries' => $max_retries,
				'history_data' => $history_data,
				'min_warning' => $min_warning,
				'max_warning' => $max_warning,
				'str_warning' => $str_warning,
				'min_critical' => $min_critical,
				'max_critical' => $max_critical,
				'str_critical' => $str_critical,
				'min_ff_event' => $ff_event,
				'custom_string_1' => $custom_string_1,
				'custom_string_2' => $custom_string_2,
				'custom_string_3' => $custom_string_3,
				'post_process' => $post_process,
				'unit' => $unit,
				'wizard_level' => $wizard_level,
				'macros' => $macros,
				'critical_instructions' => $critical_instructions,
				'warning_instructions' => $warning_instructions,
				'unknown_instructions' => $unknown_instructions,
				'critical_inverse' => $critical_inverse,
				'warning_inverse' => $warning_inverse,
				'id_category' => $id_category,
				'tags' => $tags,
				'disabled_types_event' => $disabled_types_event,
				'min_ff_event_normal' => $ff_event_normal,
				'min_ff_event_warning' => $ff_event_warning,
				'min_ff_event_critical' => $ff_event_critical,
				'each_ff' => $each_ff));
	}
	else {
		$result = '';
	}
	if ($result === false || !$result) {
		db_pandora_audit("Module management",
			"Fail try to update network component #$id");
		ui_print_error_message (__('Could not be updated'));
		include_once ('godmode/modules/manage_network_components_form.php');
		return;
	}
	
	db_pandora_audit("Module management", "Update network component #$id");
	ui_print_success_message (__('Updated successfully'));
	
	$id = 0;
}

if ($delete_component) {
	$id = (int) get_parameter ('id');
	
	$result = network_components_delete_network_component ($id);
	
	if ($result) {
		db_pandora_audit( "Module management",
			"Delete network component #$id");
	}
	else {
		db_pandora_audit( "Module management",
			"Fail try to delete network component #$id");
	}
	
	ui_print_result_message ($result,
		__('Successfully deleted'),
		__('Could not be deleted'));
	$id = 0;
}

if ($multiple_delete) {
	$ids = (array)get_parameter('delete_multiple', array());
	
	foreach ($ids as $id) {
		$result = network_components_delete_network_component ($id);
		
		if ($result === false) {
			break;
		}
	}
	
	$str_ids = implode (',', $ids);
	if ($result) {
		db_pandora_audit( "Module management",
			"Multiple delete network component: $str_ids");
	}
	else {
		db_pandora_audit( "Module management",
			"Fail try to delete network component: $str_ids");
	}
	
	ui_print_result_message ($result,
		__('Successfully multiple deleted'),
		__('Not deleted. Error deleting multiple data'));
	
	$id = 0;
}

if ($id || $new_component || $create_network_from_module || $create_network_from_snmp_browser) {
	include_once ($config['homedir'] .
		'/godmode/modules/manage_network_components_form.php');
	return;
}

$url = ui_get_url_refresh (array ('offset' => false,
	'id' => false,
	'create_component' => false,
	'update_component' => false,
	'delete_component' => false,
	'id_network_component' => false,
	'upd' => false,
	'crt' => false,
	'type' => false,
	'name' => false,
	'description' => false,
	'max' => false,
	'min' => false,
	'tcp_send' => false,
	'tcp_rcv' => false,
	'tcp_port' => false,
	'snmp_oid' => false,
	'snmp_community' => false,
	'id_module_group' => false,
	'module_interval' => false,
	'id_group' => false,
	'plugin_user' => false,
	'plugin_pass' => false,
	'plugin_parameter' => false,
	'macros' => false,
	'max_timeout' => false,
	'max_retries' => false,
	'id_modulo' => false,
	'id_plugin' => false,
	'history_data' => false,
	'min_warning' => false,
	'max_warning' => false,
	'str_warning' => false,
	'min_critical' => false,
	'max_critical' => false,
	'str_critical' => false,
	'ff_event' => false,
	'id_component_type' => false,
	'critical_instructions' => false,
	'warning_instructions' => false,
	'unknown_instructions' => false,
	'critical_inverse' => false,
	'warning_inverse' => false,
	'id_category' => false,
	'tags' => false,
	'ff_event_normal' => false,
	'ff_event_warning' => false,
	'ff_event_critical' => false,
	'each_ff' => false));


$search_id_group = (int) get_parameter ('search_id_group');
$search_string = (string) get_parameter ('search_string');

$table = new stdClass();
$table->width = '100%';
$table->class = 'databox filters';

$table->style = array ();
$table->style[0] = 'font-weight: bold';
$table->style[2] = 'font-weight: bold';

$table->data = array ();

$table->data[0][0] = __('Group');

$component_groups  = network_components_get_groups ();

if ($component_groups === false)
	$component_groups = array();

foreach ($component_groups as $component_group_key => $component_group_val) {
	$num_components = db_get_num_rows(
		'SELECT id_nc
		FROM tnetwork_component 
		WHERE id_group = ' . $component_group_key);
	
	$childs = component_groups_get_childrens($component_group_key);
	
	$num_components_childs = 0;
	
	if ($childs !== false) {
		
		foreach ($childs as $child) {
			
			$num_components_childs += db_get_num_rows(
				'SELECT id 
				FROM tlocal_component 
				WHERE id_network_component_group = ' . $child['id_sg']);
			
		}
		
	}
	
	// Only show component groups with local components
	if ($num_components  == 0 && $num_components_childs == 0)
		unset($component_groups[$component_group_key]);
}

$table->data[0][1] = html_print_select ($component_groups,
	'search_id_group', $search_id_group, '', __('All'), 0, true, false, false);
$table->data[0][2] = __('Free Search') . ui_print_help_tip(
	__('Search by name, description, tcp send or tcp rcv, list matches.'),true);
$table->data[0][3] = html_print_input_text ('search_string', $search_string, '', 25,
	255, true);
if(defined("METACONSOLE"))
	$table->data[0][4] = '<div>';
else
	$table->data[0][4] = '<div class="action-buttons">';
$table->data[0][4] .= html_print_submit_button (__('Search'), 'search', false,
	'class="sub search"', true);
$table->data[0][4] .= '</div>';

if (defined("METACONSOLE")) {
	$filter = '<form class="filters_form" method="post" action="'.$url.'">';
	$filter .= html_print_table ($table,true);
	$filter .= '</form>';
	ui_toggle($filter, __("Show Options"));
}
else {
	echo '<form method="post" action="' . $url . '">';
	html_print_table ($table);
	echo '</form>';
}

$filter = array ();
if ($search_id_group)
	$filter['id_group'] = $search_id_group;
if ($search_string != '')
	$filter[] = '(name LIKE ' . "'%" . $search_string . "%'"  .
		'OR description LIKE ' . "'%". $search_string . "%'"  .
		'OR tcp_send LIKE ' . "'%" . $search_string . "%'"  .
		'OR tcp_rcv LIKE ' . "'%" . $search_string . "%'"  . ')';

$total_components = network_components_get_network_components (false, $filter, 'COUNT(*) AS total');
$total_components = $total_components[0]['total'];
ui_pagination ($total_components, $url);
$filter['offset'] = (int) get_parameter ('offset');
$filter['limit'] = (int) $config['block_size'];
$components = network_components_get_network_components (false, $filter,
	array ('id_nc', 'name', 'description', 'id_group', 'type', 'max', 'min',
		'module_interval', 'id_modulo'));
if ($components === false)
	$components = array ();

unset ($table);

$table->width = '100%';
$table->head = array ();
$table->class = 'databox data';
$table->head[0] = __('Module name');
$table->head[1] = __('Type');
$table->head[3] = __('Description');
$table->head[4] = __('Group');
$table->head[5] = __('Max/Min');
$table->head[6] = __('Action') .
	html_print_checkbox('all_delete', 0, false, true, false,
		'check_all_checkboxes();');
$table->size = array ();
$table->size[1] = '75px';
$table->size[6] = '80px';
$table->align[6] = 'left';
$table->data = array ();

foreach ($components as $component) {
	$data = array ();
	
	if ($component['max'] == $component['min'] && $component['max'] == 0) {
		$component['max'] = $component['min'] = __('N/A');
	}
	
	$data[0] = '<a href="index.php?sec=' . $sec . '&' .
		'sec2=godmode/modules/manage_network_components&' .
		'id=' . $component['id_nc'] . '&pure=' . $pure . '">';
	$data[0] .= io_safe_output($component['name']);
	$data[0] .= '</a>';
	$data[1] = ui_print_moduletype_icon ($component['type'], true);
	switch ($component['id_modulo']) {
		case MODULE_NETWORK:
			$data[1] .= html_print_image('images/network.png', true,
				array('title' => __('Network module')));
			break;
		case MODULE_WMI:
			$data[1] .= html_print_image('images/wmi.png', true,
				array('title' => __('WMI module')));
			break;
		case MODULE_PLUGIN:
			$data[1] .= html_print_image('images/plugin.png', true,
				array('title' => __('Plug-in module')));
			break;
	}
	$data[3] = "<span style='font-size: 8px'>".
		mb_strimwidth (io_safe_output($component['description']), 0, 60, "...") . "</span>";
	$data[4] = network_components_get_group_name ($component['id_group']);
	$data[5] = $component['max']." / ".$component['min'];
	
	$data[6] = '<a style="display: inline; float: left" href="' . $url . '&search_id_group=' . $search_id_group .
		'search_string=' . $search_string . '&duplicate_network_component=1&source_id=' . $component['id_nc'] . '">' . 
		html_print_image('images/copy.png', true, array('alt' => __('Duplicate'), 'title' => __('Duplicate'))) . '</a>';
	$data[6] .= '<a href="' . $url . '&delete_component=1&id=' . $component['id_nc'] . '&search_id_group=' . $search_id_group .
		'search_string=' . $search_string . 
		'" onclick="if (! confirm (\'' . __('Are you sure?') . '\')) return false" >' . 
		html_print_image('images/cross.png', true, array('alt' => __('Delete'), 'title' => __('Delete'))) . '</a>' .
		html_print_checkbox_extended ('delete_multiple[]', $component['id_nc'], false, false, '', 'class="check_delete"', true);
	
	array_push ($table->data, $data);
}

if (isset($data)) {
	echo "<form method='post' action='index.php?sec=" . $sec .
		"&sec2=godmode/modules/manage_network_components&search_id_group=0search_string=&pure=".$pure."'>";
	html_print_input_hidden('multiple_delete', 1);
	html_print_table ($table);
	echo "<div style='float: right; margin-left: 5px;'>";
	html_print_submit_button(__('Delete'), 'delete_btn', false, 'class="sub delete"');
	echo "</div>";
	echo "</form>";
}
else {
	ui_print_info_message ( array('no_close'=>true, 'message'=>  __('There are no defined network components') ) );
}

echo '<form method="post" action="' . $url . '">';
echo '<div class="" style="float:right;">';
html_print_input_hidden ('new_component', 1);
html_print_select (array(
	2 => __('Create a new network component'),
	4 => __('Create a new plugin component'),
	6 => __('Create a new WMI component')),
	'id_component_type', '', '', '', '', '');
html_print_submit_button (__('Create'), 'crt', false, 'class="sub next" style="margin-left: 5px;"');
echo '</div>';
echo '</form>';

enterprise_hook('close_meta_frame');

?>
<script type="text/javascript">
	function check_all_checkboxes() {
		if ($("input[name=all_delete]").prop("checked")) {
			$(".check_delete").prop("checked", true);
		}
		else {
			$(".check_delete").prop("checked", true);
		}
	}
</script>
