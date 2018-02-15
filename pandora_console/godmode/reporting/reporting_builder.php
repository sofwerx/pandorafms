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
global $config;

// IMPORTANT NOTE: All reporting pages are used also for metaconsole reporting functionality
// So, it's very important to specify full url and paths to resources because metaconsole has a different
// entry point: enterprise/meta/index.php than normal console !!!

// Login check
check_login ();

enterprise_hook('open_meta_frame');
$report_r = check_acl ($config['id_user'], 0, "RR");
$report_w = check_acl ($config['id_user'], 0, "RW");
$report_m = check_acl ($config['id_user'], 0, "RM");
$access = ($report_r == true) ? 'RR' : (($report_w == true) ? 'RW' : (($report_m == true) ? 'RM' : 'RR'));
if (!$report_r  && !$report_w && !$report_m) {
	db_pandora_audit("ACL Violation",
		"Trying to access report builder");
	require ("general/noaccess.php");
	exit;
}

require_once ($config['homedir'] . "/include/functions_reports.php");

// Load enterprise extensions
enterprise_include ('operation/reporting/custom_reporting.php');
enterprise_include_once ('include/functions_metaconsole.php');

$enterpriseEnable = false;
if (enterprise_include_once('include/functions_reporting.php') !== ENTERPRISE_NOT_HOOK) {
	$enterpriseEnable = true;
}

//Constant with fonts directory
define ('_MPDF_TTFONTPATH', 'include/fonts/');

$activeTab = get_parameter('tab', 'main');
$action = get_parameter('action', 'list');
$idReport = get_parameter('id_report', 0);
$offset = (int) get_parameter('offset', 0);
$idItem = get_parameter('id_item', 0);
$pure = get_parameter('pure',0);
$schedule_report = get_parameter('schbutton', '');
$pagination = (int) get_parameter ("pagination", $config["block_size"]);
$strict_user = db_get_value('strict_acl', 'tusuario', 'id_user', $config['id_user']);

if ($schedule_report != '') {
	
	$id_user_task = 1;
	$scheduled = 'no';
	$date = date(DATE_FORMAT);
	$time = date(TIME_FORMAT);
	$parameters[0] = get_parameter('id_schedule_report');
	//$parameters[1] = db_get_value('schedule_email', 'treport', 'id_report', $id_report);
	$parameters[1] = get_parameter('schedule_email');
	$parameters['first_execution'] = strtotime ($date.' '.$time);
	
	$values = array(
		'id_usuario' => $config['id_user'],
		'id_user_task' => $id_user_task,
		'args' => serialize ($parameters),
		'scheduled' => $scheduled,
		'flag_delete' => 1);
	
	$result = db_process_sql_insert('tuser_task_scheduled', $values);
	
	ui_print_result_message ($result,
		__('Your report has been planned, and the system will email you a PDF with the report as soon as its finished'),
		__('An error has ocurred'));
	echo '<br>';
}

//Other Checks for the edit the reports
if ($idReport != 0) {
	$report = db_get_row_filter('treport', array('id_report' => $idReport));
	$type_access_selected = reports_get_type_access($report);
	$edit = false;
	switch ($type_access_selected) {
		case 'group_view':
			$edit = check_acl($config['id_user'], $report['id_group'], "RW");
			break;
		case 'group_edit':
			$edit = check_acl($config['id_user'], $report['id_group_edit'], "RW");
			break;
		case 'user_edit':
			if ($config['id_user'] == $report['id_user'] ||
				is_user_admin ($config["id_user"]))
				$edit = true;
			break;
	}
	
	if (! $edit) {
		// The user that created the report should can delete it. Despite its permissions.
		$delete_report_bypass = false;
		
		if ($action == 'delete_report') {
			if ($config['id_user'] == $report['id_user'] || is_user_admin ($config["id_user"])) {
				$delete_report_bypass = true;
			}
		}
		
		if (!$delete_report_bypass) {
			db_pandora_audit("ACL Violation",
				"Trying to access report builder");
			require ("general/noaccess.php");
			exit;
		}
	}
}

switch ($action) {
	case 'sort_items':
		switch ($activeTab) {
			case 'list_items':
				$resultOperationDB = null;
				$position_to_sort = (int)get_parameter('position_to_sort', 1);
				$ids_serialize = (string)get_parameter('ids_items_to_sort', '');
				$move_to = (string)get_parameter('move_to', 'after');
				
				$countItems = db_get_sql('
					SELECT COUNT(id_rc)
					FROM treport_content
					WHERE id_report = ' . $idReport);
				
				if (($countItems < $position_to_sort) || ($position_to_sort < 1)) {
					$resultOperationDB = false;
				}
				else if (!empty($ids_serialize)) {
					$ids = explode('|', $ids_serialize);
					
					switch ($config["dbtype"]) {
						case "mysql":
							$items = db_get_all_rows_sql('
								SELECT id_rc, `order`
								FROM treport_content
								WHERE id_report = ' . $idReport . '
								ORDER BY `order`');
							break;
						case "oracle":
						case "postgresql":
							$items = db_get_all_rows_sql('
								SELECT id_rc, "order"
								FROM treport_content
								WHERE id_report = ' . $idReport . '
								ORDER BY "order"');
							break;
					}
					
					if ($items === false) $items = array();
					
					
					// Clean the repeated order values
					$order_temp = 1;
					foreach ($items as $item) {
						switch ($config["dbtype"]) {
							case "mysql":
								db_process_sql_update('treport_content',
									array('`order`' => $order_temp),
									array('id_rc' => $item['id_rc']));
								break;
							case "postgresql":
							case "oracle":
								db_process_sql_update('treport_content',
									array('"order"' => $order_temp),
									array('id_rc' => $item['id_rc']));
								break;
						}
						
						$order_temp++;
					}
					
					
					switch ($config["dbtype"]) {
						case "mysql":
							$items = db_get_all_rows_sql('
								SELECT id_rc, `order`
								FROM treport_content
								WHERE id_report = ' . $idReport . '
								ORDER BY `order`');
							break;
						case "oracle":
						case "postgresql":
							$items = db_get_all_rows_sql('
								SELECT id_rc, "order"
								FROM treport_content
								WHERE id_report = ' . $idReport . '
								ORDER BY "order"');
							break;
					}
					
					if ($items === false) $items = array();
					
					
					
					$temp = array();
					
					$temp = array();
					foreach ($items as $item) {
						//Remove the contents from the block to sort
						if (array_search($item['id_rc'], $ids) === false) {
							$temp[$item['order']] = $item['id_rc'];
						}
					}
					$items = $temp;
					
					
					
					$sorted_items = array();
					foreach ($items as $pos => $id_unsort) {
						if ($pos == $position_to_sort) {
							if ($move_to == 'after') {
								$sorted_items[] = $id_unsort;
							}
							
							foreach ($ids as $id) {
								$sorted_items[] = $id;
							}
							
							if ($move_to != 'after') {
								$sorted_items[] = $id_unsort;
							}
						}
						else {
							$sorted_items[] = $id_unsort;
						}
					}
					
					$items = $sorted_items;
					
					
					
					foreach ($items as $order => $id) {
						switch ($config["dbtype"]) {
							case "mysql":
								db_process_sql_update('treport_content',
									array('`order`' => ($order + 1)),
									array('id_rc' => $id));
								break;
							case "postgresql":
							case "oracle":
								db_process_sql_update('treport_content',
									array('"order"' => ($order + 1)),
									array('id_rc' => $id));
								break;
						}
					}
					
					$resultOperationDB = true;
				}
				else {
					$resultOperationDB = false;
				}
				break;
			}
		break;
	case 'delete_items':
		$resultOperationDB = null;
		$ids_serialize = (string)get_parameter('ids_items_to_delete', '');
		
		if (!empty($ids_serialize)) {
			$sql = "DELETE FROM treport_content WHERE id_rc IN ($ids_serialize)";
			$resultOperationDB = db_process_sql($sql);
		}
		else {
			$resultOperationDB = false;
		}
		
		break;
	case 'delete_items_pos':
		$resultOperationDB = null;
		$position_to_delete = (int)get_parameter('position_to_delete', 1);
		$pos_delete = (string)get_parameter('delete_m', 'below');
		
		$countItems = db_get_sql('SELECT COUNT(id_rc)
			FROM treport_content WHERE id_report = ' . $idReport);
		
		if (($countItems < $position_to_delete) || ($position_to_delete < 1)) {
			$resultOperationDB = false;
		}
		else {
			$sql = "SELECT id_rc FROM treport_content WHERE id_report=$idReport ORDER BY '`order`'";
			$items = db_get_all_rows_sql($sql);
			switch ($pos_delete) {
				case 'above':
					if ($position_to_delete == 1) {
						$resultOperationDB = false;
					}
					else {
						$i = 1;
						foreach ($items as $key => $item) {
							if ($i < $position_to_delete) {
								$resultOperationDB = db_process_sql_delete('treport_content', array('id_rc' => $item['id_rc']));
							}
							$i++;
						}
					}
					break;
				case 'below':
					if ($position_to_delete == $countItems) {
						$resultOperationDB = false;
					}
					else {
						$i = 1;
						foreach ($items as $key => $item) {
							if ($i > $position_to_delete) {
								$resultOperationDB =
									db_process_sql_delete(
										'treport_content',
										array('id_rc' => $item['id_rc']));
							}
							$i++;
						}
					}
					break;
			}
		}
		break;
	case 'delete_report':
	case 'list':
		$buttons = array(
			'list_reports' => array('active' => false,
				'text' => '<a href="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&pure='.$pure.'">' . 
					html_print_image("images/report_list.png", true, array ("title" => __('Reports list'))) .'</a>')
			);
		
		if ($enterpriseEnable) {
			$buttons = reporting_enterprise_add_main_Tabs($buttons);
		}
		
		$subsection = '';
		switch ($activeTab) {
			case 'main':
				$buttons['list_reports']['active'] = true;
				$subsection = ' &raquo; '.__('Custom reporting');
				break;
			default:
				$subsection = reporting_enterprise_add_subsection_main($activeTab, $buttons);
				break;
		}
		
		// Page header for metaconsole
		if ($enterpriseEnable and defined('METACONSOLE')) {
			// Bread crumbs
			ui_meta_add_breadcrumb(
				array(
				'link' => 'index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&pure='.$pure,
				'text' => __('Reporting')));
			
			ui_meta_print_page_header($nav_bar);
			
			// Print header
			ui_meta_print_header(__('Reporting'), "", $buttons);
		}
		// Page header for normal console
		else
			ui_print_page_header (__('Reporting').' &raquo; '.__('Custom reporting'), "images/op_reporting.png", false, "", false, $buttons);
		
		
		if ($action == 'delete_report') {
			$delete = false;
			switch ($type_access_selected) {
				case 'group_view':
					if ($config['id_user'] == $report['id_user'] || is_user_admin ($config["id_user"])) {
						$delete = true; //owner can delete
					} else {
						$delete = check_acl($config['id_user'],
							$report['id_group'], "RM");
					}
					break;
				case 'group_edit':
					if ($config['id_user'] == $report['id_user'] || is_user_admin ($config["id_user"])) {
						$delete = true; //owner can delete
					} else {
						$delete = check_acl($config['id_user'],
							$report['id_group'], "RM");
					}
					break;
				case 'user_edit':
					if ($config['id_user'] == $report['id_user'] ||
						is_user_admin ($config["id_user"])) {
						$delete = true;
					}
					break;
			}
			
			if (! $delete) {
				db_pandora_audit("ACL Violation",
					"Trying to access report builder deletion");
				require ("general/noaccess.php");
				exit;
			}
			
			$result = reports_delete_report ($idReport);
			if ($result !== false)
				db_pandora_audit("Report management", "Delete report #$idReport");
			else
				db_pandora_audit("Report management", "Fail try to delete report #$idReport");
			
			ui_print_result_message ($result,
				__('Successfully deleted'),
				__('Could not be deleted'));
		}
		
		$id_group = (int) get_parameter ("id_group", 0);
		$search = trim(get_parameter ("search", ""));
		
		$search_sql = '';
		if ($search != "") {
			$search_name = "%$search%' OR description LIKE '%$search%";
		}
		
		$table_aux = new stdClass();
		$table_aux->width = '100%';
		$table_aux->class = 'databox filters';
		$table_aux->cellpadding = 0;
		$table_aux->cellspacing = 0;
		
		$table_aux->colspan[0][0] = 4;
		$table_aux->data[0][0] = "<b>". __("Group") . "</b>";
		
		$table_aux->data[0][1] = html_print_select_groups(false, $access, true, 'id_group', $id_group, '', '', '', true, false, true, '', false, 'width:150px', false, false, 'id_grupo', $strict_user). '<br>';
		
		$table_aux->data[0][2] = "<b>". __("Free text for search: ") . ui_print_help_tip(
		__('Search by report name or description, list matches.'),true) . "</b>";
		$table_aux->data[0][3] = html_print_input_text ("search", $search, '', 30, '', true);
		
		$table_aux->data[0][6] = html_print_submit_button(__('Search'), 'search_submit', false, 'class="sub upd"', true);
		
		if (is_metaconsole()) {
			$filter = "<form class ='' action='index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&id_group=$id_group&pure=$pure'
				method='post'>";
			$filter .= html_print_table($table_aux,true);
			$filter .= "</form>";
			ui_toggle($filter, __("Show Option"));
		}
		else {
			echo "<form action='index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&id_group=$id_group&pure=$pure'
				method='post'>";
			html_print_table($table_aux);
			echo "</form>";
		}
		
		ui_require_jquery_file ('pandora.controls');
		ui_require_jquery_file ('ajaxqueue');
		ui_require_jquery_file ('bgiframe');
		ui_require_jquery_file ('autocomplete');
		
		// Show only selected groups
		if ($id_group > 0) {
			$group = array("$id_group" => $id_group);
		}
		else {
			$group = false;
		}
		
		$own_info = get_user_info ($config['id_user']);
		if ($own_info['is_admin'] || check_acl ($config['id_user'], 0, "RM"))
			$return_all_group = true;
		else
			$return_all_group = false;
		if ($search != "") {
			$filter = array (
				'name' => $search_name, 
				'order' => 'name'
			);
		}
		else {
			$filter = array (
				'order' => 'name'
			);
		}
		
		# Fix : group filter was not working
		// Show only selected groups
		if ($id_group > 0) {
			$group = array("$id_group" => $id_group);
			$filter['id_group'] = $id_group;
		}
		else {
			$group = false;
		}
		
		// Filter normal and metaconsole reports
		if ($config['metaconsole'] == 1 and defined('METACONSOLE'))
			$filter['metaconsole'] = 1;
		else
			$filter['metaconsole'] = 0;
		
		$reports = reports_get_reports ($filter,
			array (
				'name',
				'id_report',
				'description',
				'private',
				'id_user',
				'id_group',
				'non_interactive'), $return_all_group, $access, $group, $strict_user);
		
		$total_reports = (int) count(reports_get_reports ($filter,
			array ('name'), $return_all_group, $access, $group, $strict_user));
		
		
		if (sizeof ($reports)) {
			$url = "index.php?sec=reporting&sec2=godmode/reporting/reporting_builder";
			ui_pagination ($total_reports, $url, $offset, $pagination);
			
			$table = new stdClass();
			$table->id = 'report_list';
			$table->width = '100%';
			$table->class = 'databox data';
			$table->cellpadding = 0;
			$table->cellspacing = 0;
			
			$table->head = array ();
			$table->align = array ();
			$table->align[2] = 'left';
			$table->align[3] = 'left';
			$table->align[4] = 'left';
			$table->data = array ();
			$table->head[0] = __('Report name');
			$table->head[1] = __('Description');
			$table->head[2] = __('HTML');
			$table->head[3] = __('XML');
			$table->size[0] = '20%';
			$table->size[1] = '30%';
			$table->size[2] = '2%';
			$table->size[3] = '2%';
			$table->size[4] = '2%';
			$table->size[5] = '2%';
			$table->size[6] = '2%';
			$table->size['csv'] = '5%';
			
			$next = 4;
			//Calculate dinamically the number of the column
			if (enterprise_hook ('load_custom_reporting_1') !== ENTERPRISE_NOT_HOOK) {
				$next = 7;
			}
			
			//Admin options only for RM flag
			if (check_acl ($config['id_user'], 0, "RM")) {
				
				$table->head[$next] = __('Private');
				$table->size[$next] = '2%';
				if(defined('METACONSOLE'))
					$table->align[$next] = '';
				else
					$table->align[$next] = 'left';
				$next++;
				$table->head[$next] = __('Group');
				$table->size[$next] = '15%';
				
				$next++;
				if(!defined('METACONSOLE'))
					$table->head[$next] = '<span title="Operations">' .
						__('Op.') . '</span>';
					
				//$table->size = array ();
				$table->size[$next] = '10%';
				$table->align[$next] = 'left';
				$table->headstyle[$next] = 'text-align:left;';
			
			}
			$columnview = false;
			
			$reports = array_slice($reports, $offset, $pagination);
			
			foreach ($reports as $report) {
				
				if (!is_user_admin ($config["id_user"])) {
					if ($report["private"] && $report["id_user"] != $config['id_user'])
						if (!check_acl ($config["id_user"], $report["id_group"], "RR") && 
								!check_acl ($config["id_user"], $report["id_group"], "RW") 
								&& !check_acl ($config["id_user"], $report["id_group"], "RM"))
							continue;
					if (!check_acl ($config["id_user"], $report["id_group"], "RR") && 
							!check_acl ($config["id_user"], $report["id_group"], "RW") 
								&& !check_acl ($config["id_user"], $report["id_group"], "RM"))
						continue;
				}
				
				$data = array ();
				
				if (check_acl ($config["id_user"], $report["id_group"], "RW") || check_acl ($config["id_user"], $report["id_group"], "RM")) {
					$data[0] = '<a href="' . $config['homeurl'] . 'index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&action=edit&id_report='.
						$report['id_report'].'&pure='.$pure.'">'.$report['name'].'</a>';
				}
				else {
					$data[0] = $report['name'];
				}
				
				
				$data[1] = $report['description'];
				
				//Remove html and xml button if items are larger than limit
				$item_count = db_get_num_rows ('SELECT * FROM treport_content WHERE id_report=' . $report['id_report']);
				$report['overload'] = $item_count >= $config['report_limit'];
				if ($report['overload']) {
					$data[2] = html_print_image("images/application_not_writable.png", true, array('title' => __('This report exceeds the item limit for realtime operations')));
					$data[3] = null;
				}
				else if (!$report['non_interactive']) {
					$data[2] = '<a href="' . $config['homeurl'] . 'index.php?sec=reporting&sec2=operation/reporting/reporting_viewer&id='.$report['id_report'].'&pure='.$pure.'">' .
						html_print_image("images/html.png", true, array('title' => __('HTML view'))) . '</a>';
					$data[3] = '<a href="'. ui_get_full_url(false, false, false, false) . 'ajax.php?page=' . $config['homedir'] . '/operation/reporting/reporting_xml&id='.$report['id_report'].'">' . html_print_image("images/xml.png", true, array('title' => __('Export to XML'))) . '</a>'; //I chose ajax.php because it's supposed to give XML anyway
				}
				else {
					$data[2] = html_print_image(
						"images/html_disabled.png", true);
					$data[3] = html_print_image(
						"images/xml_disabled.png", true);
				}
				
				
				//Calculate dinamically the number of the column
				$next = 4;
				if (enterprise_hook ('load_custom_reporting_2') !== ENTERPRISE_NOT_HOOK) {
					$next = 7;
				}
				
				
				//Admin options only for RM flag
				if (check_acl ($config['id_user'], 0, "RM")) {
					
					if ($report["private"] == 1)
						$data[$next] = __('Yes');
					else
						$data[$next] = __('No');
					
					$next++;
					
					
					$data[$next] = ui_print_group_icon(
						$report['id_group'], true, "groups_small", '', !defined('METACONSOLE'));
					$next++;
				}
				
				$type_access_selected = reports_get_type_access($report);
				$edit = false;
				$delete = false;
				
				switch ($type_access_selected) {
					case 'group_view':
						$edit = check_acl($config['id_user'],
							$report['id_group'], "RW");
						
						if ($config['id_user'] == $report['id_user'] || is_user_admin ($config["id_user"])) {
							$delete = true; //owner can delete
						} else {
							$delete = false;
						}
						break;
					case 'group_edit':
						$edit = check_acl($config['id_user'],
							$report['id_group_edit'], "RW");
						
						if ($config['id_user'] == $report['id_user'] || is_user_admin ($config["id_user"])) {
							$delete = true; //owner can delete
						} else {
							$delete = check_acl($config['id_user'],
								$report['id_group'], "RM");
						}
						break;
					case 'user_edit':
						if ($config['id_user'] == $report['id_user'] ||
							is_user_admin ($config["id_user"])) {
							$edit = true;
							$delete = true;
						}
						break;
				}
				
				if ($edit || $delete) {
					$columnview = true;
					if (!isset($table->head[$next])) {
						$table->head[$next] = '<span title="Operations">' . __('Op.') . '</span>';
						$table->size = array ();
						$table->size[$next] = '80px';
						$table->style[$next] = 'text-align:left;';
					}
					
					if ($edit) {
						$data[$next] = '<form method="post" action="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&action=edit&pure='.$pure.'" style="display:inline">';
						$data[$next] .= html_print_input_hidden('id_report',
							$report['id_report'], true);
						$data[$next] .= html_print_input_image('edit',
							'images/config.png', 1, '', true, array ('title' => __('Edit')));
						$data[$next] .= '</form>';
					}
					
					if ($delete) {
						$data[$next] .= '<form method="post" style="display:inline;" onsubmit="if (!confirm (\''.__('Are you sure?').'\')) return false">';
						$data[$next] .= html_print_input_hidden ('id_report', $report['id_report'], true);
						$data[$next] .= html_print_input_hidden ('action','delete_report', true);
						$data[$next] .= html_print_input_image ('delete', 'images/cross.png', 1, '',
							true, array ('title' => __('Delete')));
						$data[$next] .= '</form>';
					}
				}
				
				array_push ($table->data, $data);
				
			}
			
			if ($columnview){
				$count = 0;
				foreach ($table->data as $datos) {
					if (!isset($datos[9]))
						$table->data[$count][9] = '';
					$count++;
				}
			}
			html_print_table ($table);
		}
		else {
			ui_print_info_message ( array ( 'no_close' => true, 'message' =>  __('No data found.') ) );
		}
		if (check_acl ($config['id_user'], 0, "RW") || check_acl ($config['id_user'], 0, "RM")) {
			echo '<form method="post" action="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=main&action=new&pure='.$pure.'">';
			if (defined("METACONSOLE"))
				echo '<div class="action-buttons" style="width: 100%; ">';
			else
				echo '<div class="action-buttons" style="width: 100%;">';
			html_print_submit_button (__('Create report'), 'create', false, 'class="sub next"');
			echo "</div>";
			echo "</form>";
		}
		
		enterprise_hook('close_meta_frame');
		
		return;
		break;
	case 'new':
		switch ($activeTab) {
			case 'main':
				$reportName = '';
				$idGroupReport = 0; //All groups
				$description = '';
				$resultOperationDB = null;
				$report_id_user = 0;
				$type_access_selected = reports_get_type_access(false);
				$id_group_edit = 0;
				break;
			case 'item_editor':
				$resultOperationDB = null;
				$report = db_get_row_filter('treport',
					array('id_report' => $idReport));
				
				$reportName = $report['name'];
				$idGroupReport = $report['id_group'];
				$description = $report['description'];
				break;
		}
		break;
	case 'update':
	case 'save':
		switch ($activeTab) {
			case 'main':
				$reportName = get_parameter('name');
				$idGroupReport = get_parameter('id_group');
				$description = get_parameter('description');
				$type_access_selected = get_parameter('type_access', 'group_view');
				$id_group_edit_param = (int)get_parameter('id_group_edit', 0);
				$report_id_user = get_parameter('report_id_user');
				$non_interactive = get_parameter('non_interactive', 0);
				
				// Pretty font by default for pdf
				$custom_font = 'FreeSans.ttf';
				
				switch ($type_access_selected) {
					case 'group_view':
						$id_group_edit = 0;
						$private = 0;
						break;
					case 'group_edit':
						$id_group_edit = $id_group_edit_param;
						$private = 0;
						break;
					case 'user_edit':
						$id_group_edit = 0;
						$private = 1;
						break;
				}
				if ($action == 'update') {
					if ($reportName != "" && $idGroupReport != "") {
						$new_values = array('name' => $reportName,
							'id_group' => $idGroupReport,
							'description' => $description,
							'private' => $private,
							'id_group_edit' => $id_group_edit,
							'non_interactive' => $non_interactive);
						
						
						$report = db_get_row_filter('treport',
							array('id_report' => $idReport));
						$report_id_user = $report['id_user'];
						if ($report_id_user != $config['id_user'] &&
							!is_user_admin ($config["id_user"])) {
							unset($new_values['private']);
							unset($new_values['id_group_edit']);
						}
						$resultOperationDB = (bool)db_process_sql_update(
							'treport', $new_values,
							array('id_report' => $idReport));
						
						if ($resultOperationDB !== false)
							db_pandora_audit( "Report management",
								"Update report #$idReport");
						else
							db_pandora_audit( "Report management",
								"Fail try to update report #$idReport");
					}
					else {
						$resultOperationDB = false;
					}
					
					$action = 'edit';
				}
				else if ($action == 'save') {
					if ($reportName != "" && $idGroupReport != "") {
						
						// This flag allow to differentiate between normal console and metaconsole reports 
						$metaconsole_report = (int) is_metaconsole();
						
						// Juanma (07/05/2014) New feature: Custom front page for reports  
						if ($config['custom_report_front']) {
							
							$custom_font = $config['custom_report_front_font'];
							$logo = $config['custom_report_front_logo'];
							$header = $config['custom_report_front_header'];
							$first_page = $config['custom_report_front_firstpage'];
							$footer = $config['custom_report_front_footer'];							
							
						}
						else {
							
							$start_url = ui_get_full_url(false, false, false, false);
							$first_page = "&lt;p&#x20;style=&quot;text-align:&#x20;center;&quot;&gt;&amp;nbsp;&lt;/p&gt;&#x0d;&#x0a;&lt;p&#x20;style=&quot;text-align:&#x20;center;&quot;&gt;&amp;nbsp;&lt;/p&gt;&#x0d;&#x0a;&lt;p&#x20;style=&quot;text-align:&#x20;center;&quot;&gt;&amp;nbsp;&lt;/p&gt;&#x0d;&#x0a;&lt;p&#x20;style=&quot;text-align:&#x20;center;&quot;&gt;&amp;nbsp;&lt;/p&gt;&#x0d;&#x0a;&lt;p&#x20;style=&quot;text-align:&#x20;center;&quot;&gt;&amp;nbsp;&lt;/p&gt;&#x0d;&#x0a;&lt;p&#x20;style=&quot;text-align:&#x20;center;&quot;&gt;&amp;nbsp;&lt;/p&gt;&#x0d;&#x0a;&lt;p&#x20;style=&quot;text-align:&#x20;center;&quot;&gt;&amp;nbsp;&lt;/p&gt;&#x0d;&#x0a;&lt;p&#x20;style=&quot;text-align:&#x20;center;&quot;&gt;&lt;img&#x20;src=&quot;" . $start_url . "/images/pandora_report_logo.png&quot;&#x20;alt=&quot;&quot;&#x20;width=&quot;800&quot;&#x20;/&gt;&lt;/p&gt;&#x0d;&#x0a;&lt;p&#x20;style=&quot;text-align:&#x20;center;&quot;&gt;&amp;nbsp;&lt;/p&gt;&#x0d;&#x0a;&lt;p&#x20;style=&quot;text-align:&#x20;center;&quot;&gt;&lt;span&#x20;style=&quot;font-size:&#x20;xx-large;&quot;&gt;&#40;_REPORT_NAME_&#41;&lt;/span&gt;&lt;/p&gt;&#x0d;&#x0a;&lt;p&#x20;style=&quot;text-align:&#x20;center;&quot;&gt;&lt;span&#x20;style=&quot;font-size:&#x20;large;&quot;&gt;&#40;_DATETIME_&#41;&lt;/span&gt;&lt;/p&gt;";
							$logo = $header = $footer = null;
							
						}
						
						$idOrResult = db_process_sql_insert('treport',
							array('name' => $reportName,
								'id_group' => $idGroupReport,
								'description' => $description,
								'first_page' => $first_page,
								'private' => $private,
								'id_group_edit' => $id_group_edit,
								'id_user' => $config['id_user'],
								'metaconsole' => $metaconsole_report,
								'non_interactive' => $non_interactive,
								'custom_font' => $custom_font,
								'custom_logo' => $logo,
								'header' => $header,
								'footer' => $footer));
								
						if ($idOrResult !== false)
							db_pandora_audit( "Report management", "Create report #$idOrResult");
						else
							db_pandora_audit( "Report management", "Fail try to create report");
					}
					else {
						$idOrResult = false;
					}
					
					if ($idOrResult === false) {
						$resultOperationDB = false;
					}
					else {
						$resultOperationDB = true;
						$idReport = $idOrResult;
						$report_id_user = $config['id_user'];
					}
					
					$action = $resultOperationDB ? 'edit' : 'new';
				}
				break;
			case 'item_editor':
				$resultOperationDB = null;
				$report = db_get_row_filter('treport',
					array('id_report' => $idReport));
				
				$reportName = $report['name'];
				$idGroupReport = $report['id_group'];
				$description = $report['description'];
				$good_format = false;
				switch ($action) {
					case 'update':
						$values = array();
						$values['id_report'] = $idReport;
						//$values['name'] = (string) get_parameter('name');
						$values['description'] = get_parameter('description');
						$values['type'] = get_parameter('type', null);
						$label = get_parameter('label', '');
						
						//Add macros name
						$items_label = array();
						$items_label['type'] = get_parameter('type');
						$items_label['id_agent'] = get_parameter('id_agent');
						$items_label['id_agent_module'] = get_parameter('id_agent_module');
						$name_it = (string) get_parameter('name');
						$values['name'] = reporting_label_macro($items_label, $name_it);

						// Added support for projection graphs, prediction date and SLA reports
						// 'top_n_value','top_n' and 'text' fields will be reused for these types of report
						switch ($values['type']) {
							case 'projection_graph':
								$values['period'] = get_parameter('period1');
								$values['top_n_value'] = get_parameter('period2');
								$values['text'] = get_parameter('text');
								$good_format = true;
								break;
							case 'prediction_date':
								$values['period'] = get_parameter('period1');
								$values['top_n'] = get_parameter('radiobutton_max_min_avg');
								$values['top_n_value'] = get_parameter('quantity');
								$interval_max = get_parameter('max_interval');
								$interval_min = get_parameter('min_interval');
								// Checks intervals fields
								if (preg_match('/^(\-)*[0-9]*\.?[0-9]+$/', $interval_max) and preg_match('/^(\-)*[0-9]*\.?[0-9]+$/', $interval_min)) {
									$good_format = true;
								}
								$intervals = get_parameter('max_interval') . ';' . get_parameter('min_interval');
								$values['text'] = $intervals;
								break;
							case 'SLA_monthly':
							case 'SLA_weekly':
							case 'SLA_hourly':
							case 'SLA_services':
							case 'SLA':
							case 'availability_graph':
								$values['period'] = get_parameter('period');
								$values['top_n'] = get_parameter('combo_sla_sort_options',0);
								$values['top_n_value'] = get_parameter('quantity');
								$values['text'] = get_parameter('text');
								$values['show_graph'] = get_parameter('combo_graph_options');
								
								$good_format = true;
								break;
							case 'inventory':
								$values['period'] = 0;
								$es['date'] = get_parameter('date');
								$es['id_agents'] = get_parameter('id_agents');
								$es['inventory_modules'] = get_parameter('inventory_modules');
								$description = get_parameter('description');
								$values['external_source'] = json_encode($es);
								$good_format = true;
								break;
							case 'inventory_changes':
								$values['period'] = get_parameter('period');
								$es['id_agents'] = get_parameter('id_agents');
								$es['inventory_modules'] = get_parameter('inventory_modules');
								$description = get_parameter('description');
								$values['external_source'] = json_encode($es);
								$good_format = true;
								break;
							case 'netflow_area':
							case 'netflow_pie':
							case 'netflow_data':
							case 'netflow_statistics':
							case 'netflow_summary':
								$values['text'] = get_parameter('netflow_filter');
								$values['description'] = get_parameter('description');
								$values['period'] = get_parameter('period');
								$values['top_n'] = get_parameter('resolution');
								$values['top_n_value'] = get_parameter('max_values');
								$good_format = true;
								break;
							case 'availability':
								// HACK it is saved in show_graph field.
								// Show interfaces instead the modules
								$values['show_graph'] =
									get_parameter('checkbox_show_address_agent');
								$values['period'] = get_parameter('period');
								$good_format = true;
								break;
							case 'simple_graph':
							case 'simple_baseline_graph':
								// HACK it is saved in show_graph field.
								$values['show_graph'] =
									(int)get_parameter('time_compare_overlapped');
								$values['period'] = get_parameter('period');
								$good_format = true;
								break;
							default:
								$values['period'] = get_parameter('period');
								$values['top_n'] = get_parameter('radiobutton_max_min_avg',0);
								$values['top_n_value'] = get_parameter('quantity');
								$values['text'] = get_parameter('text');
								$values['show_graph'] = get_parameter('combo_graph_options');
								$good_format = true;
						}
						
						$values['id_agent'] = get_parameter('id_agent');
						$values['id_gs'] = get_parameter('id_custom_graph');
						
						$values['id_agent_module'] = '';
						if (isset($values['type'])) {
							if (($values['type'] == 'alert_report_agent') or ($values['type'] == 'event_report_agent') or ($values['type'] == 'agent_configuration') or ($values['type'] == 'group_configuration'))
								$values['id_agent_module'] = '';
							else
								$values['id_agent_module'] = get_parameter('id_agent_module');
						}
						else
							$values['id_agent_module'] = get_parameter('id_agent_module');
						
						$values['only_display_wrong'] = (int)get_parameter('checkbox_only_display_wrong', 0);
						$values['monday'] = get_parameter('monday', 0);
						$values['tuesday'] = get_parameter('tuesday', 0);
						$values['wednesday'] = get_parameter('wednesday', 0);
						$values['thursday'] = get_parameter('thursday', 0);
						$values['friday'] = get_parameter('friday', 0);
						$values['saturday'] = get_parameter('saturday', 0);
						$values['sunday'] = get_parameter('sunday', 0);
						switch ($config['dbtype']) {
							case "mysql":
							case "postgresql":
								$values['time_from'] = get_parameter('time_from');
								$values['time_to'] = get_parameter('time_to');
								break;
							case "oracle":
								$values['time_from'] = '#to_date(\'' . get_parameter('time_from') . '\',\'hh24:mi:ss\')';
								$values['time_to'] = '#to_date(\'' . get_parameter('time_to') . '\', \'hh24:mi:ss\')';
								break;
						}
						$values['group_by_agent'] = get_parameter ('checkbox_row_group_by_agent');
						$values['show_resume'] = get_parameter ('checkbox_show_resume');
						$values['order_uptodown'] = get_parameter ('radiobutton_order_uptodown');
						$values['exception_condition'] = (int)get_parameter('exception_condition', 0);
						$values['exception_condition_value'] = get_parameter('exception_condition_value');
						$values['id_module_group'] = get_parameter('combo_modulegroup');
						$values['id_group'] = get_parameter ('combo_group');
						$values['server_name'] = get_parameter ('server_name');
						$server_id = (int)get_parameter('server_id');
						if ($server_id != 0) {
							$connection = metaconsole_get_connection_by_id($server_id);
							$values['server_name'] = $connection['server_name'];
						}
						if ($values['server_name'] == '')
							$values['server_name'] = get_parameter ('combo_server');
						
						if ((($values['type'] == 'custom_graph') or ($values['type'] == 'automatic_custom_graph')) && ($values['id_gs'] == 0 || $values['id_gs'] == '')) {
							$resultOperationDB = false;
							break;
						}
						$filter_event_validated = get_parameter('filter_event_validated', 0);
						$filter_event_no_validated = get_parameter('filter_event_no_validated', 0);
						$filter_event_critical = get_parameter('filter_event_critical', 0);
						$filter_event_warning = get_parameter('filter_event_warning', 0);
						$filter_event_type = get_parameter('filter_event_type', '');
						
						$event_graph_by_agent = get_parameter('event_graph_by_agent', 0);
						$event_graph_by_user_validator = get_parameter('event_graph_by_user_validator', 0);
						$event_graph_by_criticity = get_parameter('event_graph_by_criticity', 0);
						$event_graph_validated_vs_unvalidated = get_parameter('event_graph_validated_vs_unvalidated', 0);
						
						$event_filter_search = get_parameter('filter_search', '');
						
						// If metaconsole is activated
						if ($config['metaconsole'] == 1 && defined('METACONSOLE')) {
							if (($values['type'] == 'custom_graph') or ($values['type'] == 'automatic_custom_graph')) {
								$id_gs = substr ($values['id_gs'], 0, strpos ($values['id_gs'], '|'));
								if ($id_gs !== false) {
									$server_name = strstr($values ['id_gs'], '|');
									$values ['id_gs'] = $id_gs;
									$values['server_name'] = substr ($server_name, 1, strlen($server_name));
								}
							}
							
							// Get agent and server name
							$agent_name_server = io_safe_output(get_parameter('agent'));
							
							if (isset($agent_name_server)) {
								
								$separator_pos = strpos($agent_name_server, '(');
								
								if (($separator_pos != false) and ($separator_pos != 0)) {
									
									$server_name = substr($agent_name_server, $separator_pos);
									$server_name = str_replace('(', '', $server_name);
									$server_name = str_replace(')', '', $server_name);
									// Will update server_name variable
									$values['server_name'] = trim($server_name);
									$agent_name = substr($agent_name_server, 0, $separator_pos);
									
								}
								
							}
						}
						
						if (($values['type'] == 'sql') OR ($values['type'] == 'sql_graph_hbar') OR ($values['type'] == 'sql_graph_vbar') OR ($values['type'] == 'sql_graph_pie')) {
							$values['treport_custom_sql_id'] = get_parameter('id_custom');
							if ($values['treport_custom_sql_id'] == 0) {
								$values['external_source'] = get_parameter('sql');
							}
						}
						else if ($values['type'] == 'url') {
							$values['external_source'] = get_parameter('url');
						}
						else if ($values['type'] == 'event_report_group') {
							$values['id_agent'] = get_parameter('group');
						}
						
						$values['header_definition'] = get_parameter('header');
						$values['column_separator'] = get_parameter('field');
						$values['line_separator'] = get_parameter('line');
						
						$style = array();
						$style['show_in_two_columns'] = get_parameter('show_in_two_columns', 0);
						$style['show_in_landscape'] = get_parameter('show_in_landscape', 0);
						$style['hide_notinit_agents'] = get_parameter('hide_notinit_agents', 0);
						
						switch ($values['type']) {
							case 'event_report_agent':
							case 'event_report_group':
								//Added for events items
								$style['filter_event_no_validated'] = $filter_event_no_validated;
								$style['filter_event_validated'] = $filter_event_validated;
								$style['filter_event_critical'] = $filter_event_critical;
								$style['filter_event_warning'] = $filter_event_warning;
								$style['filter_event_type'] = json_encode($filter_event_type);
								$style['event_graph_by_agent'] = $event_graph_by_agent;
								$style['event_graph_by_user_validator'] = $event_graph_by_user_validator;
								$style['event_graph_by_criticity'] = $event_graph_by_criticity;
								$style['event_graph_validated_vs_unvalidated'] = $event_graph_validated_vs_unvalidated;
								
								switch ($values['type']) {
									case 'event_report_group':
										$style['event_filter_search'] =
											$event_filter_search;
										break;
									case 'event_report_agent':
										if ($label != '')
											$style['label'] = $label;
										else
											$style['label'] = '';
										break;
								}
								break;
							case 'simple_graph':
								// Warning. We are using this column to hold this value to avoid
								// the modification of the database for compatibility reasons.
								$style['only_avg'] = (int) get_parameter('only_avg');
								$style['percentil_95'] = (int) get_parameter('percentil_95');
								if ($label != '')
									$style['label'] = $label;
								else
									$style['label'] = '';
								break;
							case 'agent_configuration':
							case 'event_report_module':
							case 'alert_report_agent':
							case 'alert_report_module':
							case 'historical_data':
							case 'sumatory':
							case 'database_serialized':
							case 'monitor_report':
							case 'min_value':
							case 'max_value':
							case 'avg_value':
							case 'projection_graph':
							case 'prediction_date':
							case 'TTRT':
							case 'TTO':
							case 'MTBF':
							case 'MTTR':
							case 'simple_baseline_graph':
								if ($label != '')
									$style['label'] = $label;
								else
									$style['label'] = '';
								break;
						}
						
						$values['style'] = io_safe_input(json_encode($style));
						
						if ($good_format) {
							switch ($config["dbtype"]) {
								case "oracle":
									if (isset($values['type'])) {
										$values[
											db_escape_key_identifier(
												"type")] = $values['type'];
										unset($values['type']);
									}
									break;
							}
							
							
							$resultOperationDB = db_process_sql_update(
								'treport_content',
								$values,
								array('id_rc' => $idItem));
						}
						else {
							$resultOperationDB = false;
						}
						break;
					case 'save':
						$values = array();
						$values['id_report'] = $idReport;
						$values['type'] = get_parameter('type', null);
						//$values['name'] = (string) get_parameter('name');
						$values['description'] = get_parameter('description');
						$label = get_parameter('label', '');
						
						//Add macros name
						$items_label = array();
						$items_label['type'] = get_parameter('type');
						$items_label['id_agent'] = get_parameter('id_agent');
						$items_label['id_agent_module'] = get_parameter('id_agent_module');
						$name_it = (string) get_parameter('name');
						$values['name'] = reporting_label_macro($items_label, $name_it);
						
						// Support for projection graph, prediction date and SLA reports
						// 'top_n_value', 'top_n' and 'text' fields will be reused for these types of report
						switch ($values['type']) {
							case 'projection_graph':
								$values['period'] = get_parameter('period1');
								$values['top_n_value'] = get_parameter('period2');
								$values['text'] = get_parameter('text');
								$good_format = true;
								break;
							case 'prediction_date':
								$values['period'] = get_parameter('period1');
								$values['top_n'] = get_parameter('radiobutton_max_min_avg');
								$values['top_n_value'] = get_parameter('quantity');
								$interval_max = get_parameter('max_interval');
								$interval_min = get_parameter('min_interval');
								// Checks intervals fields
								if (preg_match('/^(\-)*[0-9]*\.?[0-9]+$/', $interval_max) and preg_match('/^(\-)*[0-9]*\.?[0-9]+$/', $interval_min)) {
									$good_format = true;
								}
								$intervals = get_parameter('max_interval') . ';' . get_parameter('min_interval');
								$values['text'] = $intervals;
								break;
							case 'SLA':
								$values['period'] = get_parameter('period');
								$values['top_n'] = get_parameter('combo_sla_sort_options',0);
								$values['top_n_value'] = get_parameter('quantity');
								$values['text'] = get_parameter('text');
								$values['show_graph'] = get_parameter('combo_graph_options');
								
								$good_format = true;
								break;
							case 'inventory':
								$values['period'] = 0;
								$es['date'] = get_parameter('date');
								$es['id_agents'] = get_parameter('id_agents');
								$es['inventory_modules'] = get_parameter('inventory_modules');
								$values['external_source'] = json_encode($es);
								$good_format = true;
								break;
							case 'inventory_changes':
								$values['period'] = get_parameter('period');
								$es['id_agents'] = get_parameter('id_agents');
								$es['inventory_modules'] = get_parameter('inventory_modules');
								$values['external_source'] = json_encode($es);
								$good_format = true;
								break;
							case 'agent_configuration':
								$values['id_agent'] = get_parameter('id_agent');
								$good_format = true;
								break;
							case 'group_configuration':
								$values['id_group'] = get_parameter('id_group');
								$good_format = true;
								break;
							case 'netflow_area':
							case 'netflow_pie':
							case 'netflow_data':
							case 'netflow_statistics':
							case 'netflow_summary':
								$values['text'] = get_parameter('netflow_filter');
								$values['description'] = get_parameter('description');
								$values['period'] = get_parameter('period');
								$values['top_n'] = get_parameter('resolution');
								$values['top_n_value'] = get_parameter('max_values');
								$good_format = true;
								break;
							case 'availability':
								$values['period'] = get_parameter('period');
								// HACK it is saved in show_graph field.
								// Show interfaces instead the modules
								$values['show_graph'] =
									get_parameter('checkbox_show_address_agent');
								$good_format = true;
								break;
							case 'simple_graph':
							case 'simple_baseline_graph':
								// HACK it is saved in show_graph field.
								$values['show_graph'] =
									(int)get_parameter('time_compare_overlapped');
								$values['period'] = get_parameter('period');
								$good_format = true;
								break;
							default:
								$values['period'] = get_parameter('period');
								$values['top_n'] = get_parameter('radiobutton_max_min_avg',0);
								$values['top_n_value'] = get_parameter('quantity');
								$values['text'] = get_parameter('text');
								$values['show_graph'] = get_parameter('combo_graph_options');
								$good_format = true;
								break;
						}
						
						
						
						$values['server_name'] = get_parameter ('server_name');
						$server_id = (int)get_parameter('server_id');
						if ($server_id != 0) {
							$connection = metaconsole_get_connection_by_id($server_id);
							
							$values['server_name'] = $connection['server_name'];
						}
						if ($values['server_name'] == '')
							$values['server_name'] = get_parameter ('combo_server');
						
						
						if (is_metaconsole()) {
							// For SQL Query check if it is setted in the meta
							if ($values['type'] == "sql") {
								if (empty($values['server_name'])) {
									$good_format = false;
								}
							}
						}
						
						
						$values['id_agent'] = get_parameter('id_agent');
						$values['id_gs'] = get_parameter('id_custom_graph');
						if (($values['type'] == 'alert_report_agent') or ($values['type'] == 'event_report_agent') or ($values['type'] == 'agent_configuration') or ($values['type'] == 'group_configuration'))
							$values['id_agent_module'] = '';
						else
							$values['id_agent_module'] = get_parameter('id_agent_module');
						switch ($config['dbtype']) {
							case "mysql":
							case "postgresql":
								$values['only_display_wrong'] = (int)get_parameter('checkbox_only_display_wrong', 0);
								break;
							case "oracle":
								$only_display_wrong_tmp = get_parameter('checkbox_only_display_wrong');
								if (empty($only_display_wrong_tmp)) {
									$values['only_display_wrong'] = 0;
								}
								else {
									$values['only_display_wrong'] = $only_display_wrong_tmp;
								}
								break;
						}
						$values['monday'] = get_parameter('monday', 0);
						$values['tuesday'] = get_parameter('tuesday', 0);
						$values['wednesday'] = get_parameter('wednesday', 0);
						$values['thursday'] = get_parameter('thursday', 0);
						$values['friday'] = get_parameter('friday', 0);
						$values['saturday'] = get_parameter('saturday', 0);
						$values['sunday'] = get_parameter('sunday', 0);
						switch ($config['dbtype']) {
							case "mysql":
							case "postgresql":
								$values['time_from'] = get_parameter('time_from');
								$values['time_to'] = get_parameter('time_to');
								break;
							case "oracle":
								$values['time_from'] = '#to_date(\'' . get_parameter('time_from') . '\',\'hh24:mi:ss\')';
								$values['time_to'] = '#to_date(\'' . get_parameter('time_to') . '\', \'hh24:mi:ss\')';
								break;
						}
						$values['group_by_agent'] = get_parameter ('checkbox_row_group_by_agent',0);
						$values['show_resume'] = get_parameter ('checkbox_show_resume',0);
						$values['order_uptodown'] = get_parameter ('radiobutton_order_uptodown',0);
						$values['exception_condition'] = (int)get_parameter('radiobutton_exception_condition', 0);
						$values['exception_condition_value'] = get_parameter('exception_condition_value');
						$values['id_module_group'] = get_parameter('combo_modulegroup');
						$values['id_group'] = get_parameter ('combo_group');
						
						
						if ((($values['type'] == 'custom_graph') or ($values['type'] == 'automatic_custom_graph')) && ($values['id_gs'] == 0 || $values['id_gs'] == '')) {
							$resultOperationDB = false;
							break;
						}
						
						if ($config['metaconsole'] == 1 && defined('METACONSOLE')) {
							if (($values['type'] == 'custom_graph') or ($values['type'] == 'automatic_custom_graph')) {
								$id_gs = substr ($values['id_gs'], 0, strpos ($values['id_gs'], '|'));
								if ($id_gs !== false && $id_gs !== '') {
									$server_name = strstr($values ['id_gs'], '|');
									$values ['id_gs'] = $id_gs;
									$values['server_name'] = substr ($server_name, 1, strlen($server_name));
								}
							}
						}
						
						if (($values['type'] == 'sql') OR ($values['type'] == 'sql_graph_hbar')
							OR ($values['type'] == 'sql_graph_vbar') OR ($values['type'] == 'sql_graph_pie')) {
							
							$values['treport_custom_sql_id'] = get_parameter('id_custom');
							if ($values['treport_custom_sql_id'] == 0) {
								$values['external_source'] = get_parameter('sql');
							}
						}
						elseif ($values['type'] == 'url') {
							$values['external_source'] = get_parameter('url');
						}
						else if ($values['type'] == 'event_report_group') {
							$values['id_agent'] = get_parameter('group');
						}
						
						$values['header_definition'] = get_parameter('header');
						$values['column_separator'] = get_parameter('field');
						$values['line_separator'] = get_parameter('line');
						
						$style = array();
						$style['show_in_two_columns'] = get_parameter('show_in_two_columns', 0);
						$style['show_in_landscape'] = get_parameter('show_in_landscape', 0);
						$style['hide_notinit_agents'] = get_parameter('hide_notinit_agents', 0);
						
						switch ($values['type']) {
							case 'event_report_agent':
							case 'event_report_group':
								$filter_event_no_validated = get_parameter('filter_event_no_validated', 0);
								$filter_event_validated = get_parameter('filter_event_validated', 0);
								$filter_event_critical = get_parameter('filter_event_critical', 0);
								$filter_event_warning = get_parameter('filter_event_warning', 0);
								
								$event_graph_by_agent = get_parameter('event_graph_by_agent', 0);
								$event_graph_by_user_validator = get_parameter('event_graph_by_user_validator', 0);
								$event_graph_by_criticity = get_parameter('event_graph_by_criticity', 0);
								$event_graph_validated_vs_unvalidated = get_parameter('event_graph_validated_vs_unvalidated', 0);
								$filter_event_type = get_parameter('filter_event_type', '');
								
								$event_filter_search = get_parameter('filter_search', '');
								
								//Added for events items
								$style['filter_event_no_validated'] = $filter_event_no_validated;
								$style['filter_event_validated'] = $filter_event_validated;
								$style['filter_event_critical'] = $filter_event_critical;
								$style['filter_event_warning'] = $filter_event_warning;
								$style['filter_event_type'] = json_encode($filter_event_type);
								
								$style['event_graph_by_agent'] = $event_graph_by_agent;
								$style['event_graph_by_user_validator'] = $event_graph_by_user_validator;
								$style['event_graph_by_criticity'] = $event_graph_by_criticity;
								$style['event_graph_validated_vs_unvalidated'] = $event_graph_validated_vs_unvalidated;
								
								switch ($values['type']) {
									case 'event_report_group':
										$style['event_filter_search'] =
											$event_filter_search;
										break;
									case 'event_report_agent':
										if ($label != '')
											$style['label'] = $label;
										else
											$style['label'] = '';
										break;
								}
								
								break;
							case 'simple_graph':
								// Warning. We are using this column to hold this value to avoid
								// the modification of the database for compatibility reasons.
								$style['only_avg'] = (int) get_parameter('only_avg');
								$style['percentil_95'] = (int) get_parameter('percentil_95');
								if ($label != '')
									$style['label'] = $label;
								else
									$style['label'] = '';
								break;
							case 'agent_configuration':
							case 'event_report_module':
							case 'alert_report_agent':
							case 'alert_report_module':
							case 'historical_data':
							case 'sumatory':
							case 'database_serialized':
							case 'monitor_report':
							case 'min_value':
							case 'max_value':
							case 'avg_value':
							case 'projection_graph':
							case 'prediction_date':
							case 'TTRT':
							case 'TTO':
							case 'MTBF':
							case 'MTTR':
							case 'simple_baseline_graph':
								if ($label != '')
									$style['label'] = $label;
								else
									$style['label'] = '';
								break;
						}
						
						$values['style'] = io_safe_input(json_encode($style));
						
						if ($good_format) {
							switch ($config["dbtype"]) {
								case "oracle":
									if (isset($values['type'])) {
										$values[
											db_escape_key_identifier(
												"type")] = $values['type'];
										unset($values['type']);
									}
									break;
							}
							
							$result = db_process_sql_insert(
								'treport_content', $values);
							
							if ($result === false) {
								$resultOperationDB = false;
							}
							else {
								$idItem = $result;
								
								switch ($config["dbtype"]) {
									case "mysql":
										$max = db_get_all_rows_sql(
											'SELECT max(`order`) AS max 
											FROM treport_content
											WHERE id_report = ' . $idReport . ';');
										break;
									case "postgresql":
									case "oracle":
										$max = db_get_all_rows_sql(
											'SELECT max("order") AS max 
											FROM treport_content
											WHERE id_report = ' . $idReport);
										break;
								}
								if ($max === false) {
									$max = 0;
								}
								else {
									$max = $max[0]['max'];
								}
								switch ($config["dbtype"]) {
									case "mysql":
										db_process_sql_update('treport_content',
											array('`order`' => $max + 1),
											array('id_rc' => $idItem));
										break;
									case "postgresql":
									case "oracle":
										db_process_sql_update('treport_content',
											array('"order"' => $max + 1),
											array('id_rc' => $idItem));
										break;
								}
								$resultOperationDB = true;
							}
							break;
						}
						// If fields dont have good format
						else {
							$resultOperationDB = false;
						}
				}
				break;
			default:
				if ($enterpriseEnable and $activeTab != 'advanced') {
					$resultOperationDB = reporting_enterprise_update_action();
				}
				break;
		}
		break;
	case 'filter':
	case 'edit':
		$resultOperationDB = null;
		$report = db_get_row_filter('treport',
			array('id_report' => $idReport));
		
		$reportName = $report['name'];
		$idGroupReport = $report['id_group'];
		$description = $report['description'];
		$type_access_selected = reports_get_type_access($report);
		$id_group_edit = $report['id_group_edit'];
		$report_id_user = $report['id_user'];
		$non_interactive = $report['non_interactive'];
		break;
	case 'delete':
		$idItem = get_parameter('id_item');
		
		$report = db_get_row_filter('treport', array('id_report' => $idReport));
		$reportName = $report['name'];
		
		$resultOperationDB = db_process_sql_delete('treport_content_sla_combined', array('id_report_content' => $idItem));
		$resultOperationDB2 = db_process_sql_delete('treport_content_item', array('id_report_content' => $idItem));
		if ($resultOperationDB !== false) {
			$resultOperationDB = db_process_sql_delete('treport_content', array('id_rc' => $idItem));
		}
		if ($resultOperationDB2 !== false) {
			$resultOperationDB2 = db_process_sql_delete('treport_content', array('id_rc' => $idItem));
		}
		break;
	case 'order':
		$resultOperationDB = null;
		$report = db_get_row_filter('treport', array('id_report' => $idReport));
		
		$reportName = $report['name'];
		$idGroupReport = $report['id_group'];
		$description = $report['description'];
		
		$idItem = get_parameter('id_item');
		$dir = get_parameter('dir');
		$field = get_parameter('field', null);
		
		switch ($field) {
			case 'module':
			case 'agent':
			case 'type':
				
				// Sort functionality for normal console
				if (!defined('METACONSOLE')) {
					switch ($field) {
						case 'module':
							$sql = "
								SELECT t1.id_rc, t2.nombre
								FROM treport_content t1
									LEFT JOIN tagente_modulo t2
										ON t1.id_agent_module = t2.id_agente_modulo
								WHERE %s
								ORDER BY nombre %s
							";
							break;
						case 'agent':
							$sql = "
								SELECT t4.id_rc, t5.nombre
								FROM
									(
									SELECT t1.*, id_agente
									FROM treport_content t1
										LEFT JOIN tagente_modulo t2
											ON t1.id_agent_module = id_agente_modulo
									) t4
									LEFT JOIN tagente t5
										ON (t4.id_agent = t5.id_agente OR t4.id_agente = t5.id_agente)
								WHERE %s
								ORDER BY t5.nombre %s
							";
							break;
						case 'type':
							$sql = "SELECT id_rc FROM treport_content WHERE %s ORDER BY type %s";
							break;
					}
					$sql = sprintf($sql, 'id_report = ' . $idReport, '%s');
					switch ($dir) {
						case 'up':
							$sql = sprintf($sql, 'ASC');
							break;
						case 'down':
							$sql = sprintf($sql, 'DESC');
							break;
					}
					echo $sql;
					$ids = db_get_all_rows_sql($sql);
				
				}
				// Sort functionality for metaconsole
				else if ($config['metaconsole'] == 1) {
					switch ($field) {
						case 'agent':
						case 'module':
							$sql = "SELECT id_rc, id_agent, id_agent_module, server_name FROM treport_content WHERE %s ORDER BY server_name";
							$sql = sprintf($sql, 'id_report = ' . $idReport, '%s');
							
							$report_items = db_get_all_rows_sql($sql);
							
							$ids = array();
							$temp_sort = array();
							$i = 0;
							
							if (!empty($report_items)) {
								
								foreach ($report_items as $report_item) {
									
									$connection = metaconsole_get_connection($report_item['server_name']);
									if (metaconsole_load_external_db($connection) != NOERR) {
										//ui_print_error_message ("Error connecting to ".$server_name);
									}
									
									switch ($field) {
										case 'agent':
											$agents_name = agents_get_agents(array('id_agente' => $report_item['id_agent']), 'nombre');
											
											// Item without agent
											if (!$agents_name) 
												$element_name = '';
											else {
												$agent_name = array_shift($agents_name);
												$element_name = $agent_name['nombre'];
											}
											
											break;
											
										case 'module':
											$module_name = modules_get_agentmodule_name($report_item['id_agent_module']);
											
											// Item without module
											if (!$module_name) 
												$element_name = '';
											else {
												$element_name = $module_name;
											}
											
											break;
									}
									
									metaconsole_restore_db_force();
									
									$temp_sort[$report_item['id_rc']] = $element_name;
								
								}
								
								// Performes sorting
								switch ($dir) {
									case 'up':
										asort($temp_sort);
										break;
									case 'down':
										arsort($temp_sort);
										break;
								}
								
								foreach ($temp_sort as $temp_element_key => $temp_element_val) {
									$ids[$i]['id_rc'] = $temp_element_key;
									$ids[$i]['element_name'] = $temp_element_val;
									$i++;
								}
								
								// Free resources
								unset($temp_sort);
								unset($report_items);
								
							}
							
							break;
						// Type case only depends of local database
						case 'type':
							$sql = "SELECT id_rc
								FROM treport_content
								WHERE %s ORDER BY type %s";
							
							$sql = sprintf($sql,
								'id_report = ' . $idReport, '%s');
							switch ($dir) {
								case 'up':
									$sql = sprintf($sql, 'ASC');
									break;
								case 'down':
									$sql = sprintf($sql, 'DESC');
									break;
							}
							
							$ids = db_get_all_rows_sql($sql);
							
							break;
					}
					
					
				}
				
				$count = 1;
				$resultOperationDB = true;
				foreach($ids as $id) {
					$result = db_process_sql_update('treport_content', array('order' => $count), array('id_rc' => $id['id_rc']));
					
					if ($result === false) {
						$resultOperationDB = false;
						break;
					}
					
					$count = $count + 1;
				}
				
				break;
			default:
				switch ($config["dbtype"]) {
					case "mysql":
						$oldOrder = db_get_value_sql('
							SELECT `order`
							FROM treport_content
							WHERE id_rc = ' . $idItem);
						break;
					case "postgresql":
					case "oracle":
						$oldOrder = db_get_value_sql('
							SELECT "order"
							FROM treport_content
							WHERE id_rc = ' . $idItem);
						break;
				}
				//db_get_value_filter('order', 'treport_content', array('id_rc' => $idItem));
				
				switch ($dir) {
					case 'up':
						$newOrder = $oldOrder - 1;
						break;
					case 'down':
						$newOrder = $oldOrder + 1;
						break;
				}
				
				switch ($config["dbtype"]) {
					case "mysql":
						$resultOperationDB = db_process_sql_update('treport_content',
							array('`order`' => $oldOrder),
							array('`order`' => $newOrder,
								'id_report' => $idReport));
						break;
					case "postgresql":
						$resultOperationDB = db_process_sql_update('treport_content',
							array('"order"' => $oldOrder),
							array('"order"' => $newOrder,
								'id_report' => $idReport));
						break;
					case "oracle":
						$resultOperationDB = db_process_sql_update('treport_content',
							array('"order"' => $oldOrder),
							array('"order"' => $newOrder,
								'id_report' => $idReport),
							'AND', false);
						break;
				}
				if ($resultOperationDB !== false) {
					switch ($config["dbtype"]) {
						case "mysql":
							$resultOperationDB = db_process_sql_update(
								'treport_content',
								array('`order`' => $newOrder), array('id_rc' => $idItem));
							break;
						case "postgresql":
							$resultOperationDB = db_process_sql_update(
								'treport_content',
								array('"order"' => $newOrder), array('id_rc' => $idItem));
							break;
						case "oracle":
							$resultOperationDB = db_process_sql_update(
								'treport_content',
								array('"order"' => $newOrder), array('id_rc' => $idItem),
								'AND', false);
							break;
					}
				}
				
				break;
		}
		break;
	// Added for report templates
	default:
		if ($enterpriseEnable) {
			$buttons = array(
				'list_reports' => array('active' => false,
					'text' => '<a href="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&pure='.$pure.'">' . 
						html_print_image("images/report_list.png", true, array ("title" => __('Reports list'))) .'</a>')
				);
			
			$buttons = reporting_enterprise_add_main_Tabs($buttons);
			
			$subsection = '';
			switch ($activeTab) {
				case 'main':
					$buttons['list_reports']['active'] = true;
					$subsection = ' &raquo; ' . __('Custom reporting');
					break;
				default:
					$subsection = reporting_enterprise_add_subsection_main($activeTab, $buttons);
					break;
			}
			
			// Page header for metaconsole
			if ($enterpriseEnable and defined('METACONSOLE')) {
				// Bread crumbs
				ui_meta_add_breadcrumb(
					array(
					'link' => 'index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&pure='.$pure,
					'text' => __('Reporting')));
				
				ui_meta_print_page_header($nav_bar);
				
				// Print header
				ui_meta_print_header(__('Reporting'), "", $buttons);
			}
			// Page header for normal console
			else
				ui_print_page_header (__('Reporting') . $subsection, "images/op_reporting.png", false, "", false, $buttons);
			
			
			reporting_enterprise_select_main_tab($action);
			
		}
		
		return;
		break;
}

if ($enterpriseEnable) {
	$result = reporting_enterprise_actions_DB($action, $activeTab, $idReport);
	if ($result !== null) {
		$resultOperationDB = $result;
	}
}

$buttons = array(
	'list_reports' => array('active' => false,
		'text' => '<a href="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&pure='.$pure.'">' . 
			html_print_image("images/report_list.png", true, array ("title" => __('Reports list'))) .'</a>'),
	'main' => array('active' => false,
		'text' => '<a href="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=main&action=edit&id_report=' . $idReport . '&pure='.$pure.'">' . 
			html_print_image("images/op_reporting.png", true, array ("title" => __('Main data'))) .'</a>'),
	'list_items' => array('active' => false,
		'text' => '<a href="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=edit&id_report=' . $idReport . '&pure='.$pure.'">' . 
			html_print_image("images/list.png", true, array ("title" => __('List items'))) .'</a>'),
	'item_editor' => array('active' => false,
		'text' => '<a href="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=item_editor&action=new&id_report=' . $idReport . '&pure='.$pure.'">' . 
			html_print_image("images/pen.png", true, array ("title" => __('Item editor'))) .'</a>')
	);

if ($enterpriseEnable) {
	$buttons = reporting_enterprise_add_Tabs($buttons, $idReport);
}

$buttons['view'] = array('active' => false,
	'text' => '<a href="index.php?sec=reporting&sec2=operation/reporting/reporting_viewer&id=' . $idReport . '&pure='.$pure.'">' . 
		html_print_image("images/operation.png", true, array ("title" => __('View report'))) .'</a>');

$buttons[$activeTab]['active'] = true;

if ($idReport != 0) {
	$textReportName = " &raquo; " . $reportName;
}
else {
	$temp = $buttons['main'];
	$buttons = null;
	$buttons = array(
		'main' => array('active' => true,
			'text' => '<a href="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=main&action=new&pure=' .$pure.'">' .
				html_print_image("images/op_reporting.png", true, array ("title" => __('Main data'))) .'</a>'));
	$textReportName = '';
}

// Page header for metaconsole
if ($enterpriseEnable and defined('METACONSOLE')) {
	// Bread crumbs
	ui_meta_add_breadcrumb(
		array('link' =>
			'index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&pure=' . $pure,
		'text' => __('Reporting')));
	
	ui_meta_print_page_header($nav_bar);
	
	//Print header
	ui_meta_print_header(__('Reporting'). $textReportName, "", $buttons);
}
else {
	ui_print_page_header(__('Reporting') . $textReportName,
		"images/op_reporting.png", false,
		"reporting_" . $activeTab . "_tab", false, $buttons);
}

if ($resultOperationDB !== null) {
	$err = '';
	switch ($_POST['type']) {
		case 'custom_graph':
			$err.='You must enter custom graph';
			break;
		case 'SLA':
			$err.='You must enter some character in SLA limit field';
		default:
			$err.='';
			break;
	}
	ui_print_result_message ($resultOperationDB, __('Successfull action'), __('Unsuccessfull action<br><br>'.$err));
}

switch ($activeTab) {
	case 'main':
		require_once($config['homedir'] .
			'/godmode/reporting/reporting_builder.main.php');
		break;
	case 'list_items':
		require_once($config['homedir'] .
			'/godmode/reporting/reporting_builder.list_items.php');
		break;
	case 'item_editor':
		require_once($config['homedir'] .
			'/godmode/reporting/reporting_builder.item_editor.php');
		break;
	default:
		reporting_enterprise_select_tab($activeTab);
		break;
}

enterprise_hook('close_meta_frame');
?>
