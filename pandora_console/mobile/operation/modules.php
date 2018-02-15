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

class Modules {
	private $correct_acl = false;
	private $acl = "AR";
	
	private $default = true;
	private $default_filters = array();
	
	private $group = 0;
	private $status = AGENT_MODULE_STATUS_NOT_NORMAL;
	private $free_search = '';
	private $module_group = -1;
	private $tag = '';
	private $id_agent = 0;
	private $all_modules = false;
	
	private $list_status = null;
	
	private $columns = null;
	
	function __construct() {
		$system = System::getInstance();
		
		$this->list_status = array(
			-1 => __('All'),
			AGENT_MODULE_STATUS_NORMAL => __('Normal'),
			AGENT_MODULE_STATUS_WARNING => __('Warning'),
			AGENT_MODULE_STATUS_CRITICAL_BAD => __('Critical'),
			AGENT_MODULE_STATUS_UNKNOWN => __('Unknown'),
			AGENT_MODULE_STATUS_NOT_NORMAL => __('Not normal'), //default
			AGENT_MODULE_STATUS_NOT_INIT => __('Not init'));
		
		$this->columns = array('agent' => 1);
		
		if ($system->checkACL($this->acl)) {
			$this->correct_acl = true;
		}
		else {
			$this->correct_acl = false;
		}
	}
	
	public function ajax($parameter2 = false) {
		$system = System::getInstance();
		
		if (!$this->correct_acl) {
			return;
		}
		else {
			switch ($parameter2) {
				case 'get_modules':
					$this->getFilters();
					$page = $system->getRequest('page', 0);
					$modules = array();
					$end = 1;
					
					$listModules = $this->getListModules($page, true);
					
					if (!empty($listModules['modules'])) {
						$end = 0;
						$modules = $listModules['modules'];
					}
					
					echo json_encode(array('end' => $end, 'modules' => $modules));
					break;
			}
		}
	}
	
	public function setFilters($filters) {
		if (isset($filters['id_agent'])) {
			$this->id_agent = $filters['id_agent'];
		}
		if (isset($filters['all_modules'])) {
			$this->all_modules = $filters['all_modules'];
		}
		if (isset($filters['status'])) {
			$this->status = $filters['status'];
		}
	}
	
	public function disabledColumns($columns = null) {
		if (!empty($columns)) {
			foreach ($columns as $column) {
				$this->columns[$column] = 0;
			}
		}
	}
	
	private function getFilters() {
		$system = System::getInstance();
		$user = User::getInstance();
		
		$this->default_filters['module_group'] = true;
		$this->default_filters['group'] = true;
		$this->default_filters['status'] = true;
		$this->default_filters['free_search'] = true;
		$this->default_filters['tag'] = true;
	
		$this->free_search = $system->getRequest('free_search', '');
		if ($this->free_search != '') {
			$this->default = false;
			$this->default_filters['free_search'] = false;
		}
		
		$this->status = $system->getRequest('status', __("Status"));
		if (($this->status === __("Status")) || ($this->status == AGENT_MODULE_STATUS_NOT_NORMAL)) {
			$this->status = AGENT_MODULE_STATUS_NOT_NORMAL;
		}
		else {
			$this->default = false;
			$this->default_filters['status'] = false;
		}
		
		$this->group = (int)$system->getRequest('group', __("Group"));
		if (!$user->isInGroup($this->acl, $this->group)) {
			$this->group = 0;
		}
		if (($this->group === __("Group")) || ($this->group == 0)) {
			$this->group = 0;
		}
		else {
			$this->default = false;
			$this->default_filters['group'] = false;
		}
		
		$this->module_group = (int)$system->getRequest('module_group', __("Module group"));
		if (($this->module_group === __("Module group")) || ($this->module_group === -1)
			|| ($this->module_group == 0)) {
			$this->module_group = -1;
		}
		else {
			$this->default = false;
			$this->module_group = (int) $this->module_group;
			$this->default_filters['module_group'] = false;
		}
		
		$this->tag = (int)$system->getRequest('tag', __("Tag"));
		if (($this->tag === __("Tag")) || ($this->tag == 0)) {
			$this->tag = 0;
		}
		else {
			$this->default = false;
			$this->default_filters['tag'] = false;
		}
	}
	
	public function show() {
		if (!$this->correct_acl) {
			$this->show_fail_acl();
		}
		else {
			$this->getFilters();
			$this->show_modules();
		}
	}
	
	private function show_fail_acl() {
		$error['type'] = 'onStart';
		$error['title_text'] = __('You don\'t have access to this page');
		$error['content_text'] = __('Access to this page is restricted to authorized users only, please contact system administrator if you need assistance. <br><br>Please know that all attempts to access this page are recorded in security logs of Pandora System Database');
		if (class_exists("HomeEnterprise"))
			$home = new HomeEnterprise();
		else
			$home = new Home();
		$home->show($error);
	}
	
	private function show_modules() {
		$ui = Ui::getInstance();
		
		$ui->createPage();
		$ui->createDefaultHeader(__("Modules"),
			$ui->createHeaderButton(
				array('icon' => 'back',
					'pos' => 'left',
					'text' => __('Back'),
					'href' => 'index.php?page=home')));
		$ui->showFooter(false);
		$ui->beginContent();
			$filter_title = sprintf(__('Filter Modules by %s'),
				$this->filterEventsGetString());
			$ui->contentBeginCollapsible($filter_title);
				$ui->beginForm("index.php?page=modules");
					$system = System::getInstance();
					$groups = users_get_groups_for_select(
						$system->getConfig('id_user'), "AR", true, true, false, 'id_grupo');
					$options = array(
						'name' => 'group',
						'title' => __('Group'),
						'label' => __('Group'),
						'items' => $groups,
						'selected' => $this->group
						);
					$ui->formAddSelectBox($options);
					
					$options = array(
						'name' => 'status',
						'title' => __('Status'),
						'label' => __('Status'),
						'items' => $this->list_status,
						'selected' => $this->status
						);
					$ui->formAddSelectBox($options);
					
					
					$module_groups = db_get_all_rows_sql("SELECT *
						FROM tmodule_group
						ORDER BY name");
					$module_groups = io_safe_output($module_groups);
					
					array_unshift($module_groups, array("id_mg" => 0, "name" => __("All")));

					$options = array(
						'name' => 'module_group',
						'title' => __('Module group'),
						'label' => __('Module group'),
						'item_id' => 'id_mg',
						'item_value' => 'name',
						'items' => $module_groups,
						'selected' => $this->module_group
						);
					$ui->formAddSelectBox($options);
					
					$tags = tags_get_user_tags();
					
					array_unshift($tags, __("All"));
					
					$options = array(
						'name' => 'tag',
						'title' => __('Tag'),
						'label' => __('Tag'),
						'items' => $tags,
						'selected' => $this->tag
						);
					$ui->formAddSelectBox($options);
					
					$options = array(
						'name' => 'free_search',
						'value' => $this->free_search,
						'placeholder' => __('Free search')
						);
					$ui->formAddInputSearch($options);
					
					$options = array(
						'icon' => 'refresh',
						'icon_pos' => 'right',
						'text' => __('Apply Filter')
						);
					$ui->formAddSubmitButton($options);
				$html = $ui->getEndForm();
				$ui->contentCollapsibleAddItem($html);
			$ui->contentEndCollapsible();
			$this->listModulesHtml();
		$ui->endContent();
		$ui->showPage();
	}
	
	private function getListModules($page = 0, $ajax = false) {
		$system = System::getInstance();
		$user = User::getInstance();
		
		$id_type_web_content_string = db_get_value('id_tipo',
			'ttipo_modulo', 'nombre', 'web_content_string');
		
		$total = 0;
		$modules = array();
		$modules_db = array();
		
		$sql_conditions_base = " WHERE tagente.id_agente = tagente_modulo.id_agente 
			AND tagente_estado.id_agente_modulo = tagente_modulo.id_agente_modulo";
		
		
		// Part SQL for the id_agent
		$sql_conditions_agent = '';
		if ($this->id_agent != 0) {
			$sql_conditions_agent = " AND tagente_modulo.id_agente = " . $this->id_agent;
		}
		
		// Part SQL for the Group 
		if ($this->group != 0) {
			$sql_conditions_group = " AND tagente.id_grupo = " . $this->group;
		}
		else {
			$user_groups = implode(',', $user->getIdGroups($this->acl));
			$sql_conditions_group = " AND tagente.id_grupo IN (" . $user_groups . ")";
		}
		
		
		// Part SQL for the Tag
		$sql_conditions_tags = tags_get_acl_tags($user->getIdUser(),
			$user->getIdGroups($this->acl), $this->acl, 'module_condition', 'AND', 'tagente_modulo');
		
		
		$sql_conditions = " AND tagente_modulo.disabled = 0 AND tagente.disabled = 0";
		
		// Part SQL for the module_group
		if ($this->module_group > -1) {
			$sql_conditions .= sprintf (" AND tagente_modulo.id_module_group = '%d'",
				$this->module_group);
		}
		
		// Part SQL for the free search
		if ($this->free_search != "") {
			$sql_conditions .= sprintf (" AND (tagente.nombre LIKE '%%%s%%'
				OR tagente_modulo.nombre LIKE '%%%s%%'
				OR tagente_modulo.descripcion LIKE '%%%s%%')",
				$this->free_search, $this->free_search, $this->free_search);
		}
		
		// Part SQL fro Status
		if ($this->status == AGENT_MODULE_STATUS_NORMAL) { //Normal
			$sql_conditions .= " AND tagente_estado.estado = 0 
			AND (utimestamp > 0 OR (tagente_modulo.id_tipo_modulo IN(21,22,23,100))) ";
		}
		elseif ($this->status == AGENT_MODULE_STATUS_CRITICAL_BAD) { //Critical
			$sql_conditions .= " AND tagente_estado.estado = 1 AND utimestamp > 0";
		}
		elseif ($this->status == AGENT_MODULE_STATUS_WARNING) { //Warning
			$sql_conditions .= " AND tagente_estado.estado = 2 AND utimestamp > 0";
		}
		elseif ($this->status == AGENT_MODULE_STATUS_NOT_NORMAL) { //Not normal
			$sql_conditions .= " AND tagente_estado.estado <> 0";
		} 
		elseif ($this->status == AGENT_MODULE_STATUS_UNKNOWN) { //Unknown
			$sql_conditions .= " AND tagente_estado.estado = 3 AND tagente_estado.utimestamp <> 0";
		}
		elseif ($this->status == AGENT_MODULE_STATUS_NOT_INIT) { //Not init
			$sql_conditions .= " AND tagente_estado.utimestamp = 0
				AND tagente_modulo.id_tipo_modulo NOT IN (21,22,23,100)";
		}
		
		if ($this->status != AGENT_MODULE_STATUS_NOT_INIT) { //When filter is not "not init"
			// Not show not init modules. It's only operation view
			$sql_conditions .= " AND (tagente_estado.utimestamp != 0
				OR tagente_modulo.id_tipo_modulo IN (21,22,23,100))";
		}
				
		if ($this->tag > 0) {
			$sql_conditions .= " AND tagente_modulo.id_agente_modulo IN (
				SELECT ttag_module.id_agente_modulo
				FROM ttag_module
				WHERE ttag_module.id_tag = " . $this->tag . ")";
		}
		
		$sql_conditions_all = $sql_conditions_base . $sql_conditions_agent . $sql_conditions .
			$sql_conditions_group . $sql_conditions_tags;
		
		
		
		$sql_select = "SELECT
			(SELECT GROUP_CONCAT(ttag.name SEPARATOR ',')
				FROM ttag
				WHERE ttag.id_tag IN (
					SELECT ttag_module.id_tag
					FROM ttag_module
					WHERE ttag_module.id_agente_modulo = tagente_modulo.id_agente_modulo))
			AS tags, 
			tagente_modulo.id_agente_modulo,
			tagente.intervalo AS agent_interval,
			tagente.nombre AS agent_name, 
			tagente_modulo.nombre AS module_name,
			tagente_modulo.history_data,
			tagente_modulo.flag AS flag,
			tagente.id_grupo AS id_group, 
			tagente.id_agente AS id_agent, 
			tagente_modulo.id_tipo_modulo AS module_type,
			tagente_modulo.module_interval, 
			tagente_estado.datos, 
			tagente_estado.estado,
			tagente_modulo.min_warning,
			tagente_modulo.max_warning,
			tagente_modulo.str_warning,
			tagente_modulo.unit,
			tagente_modulo.min_critical,
			tagente_modulo.max_critical,
			tagente_modulo.str_critical,
			tagente_modulo.extended_info,
			tagente_modulo.critical_inverse,
			tagente_modulo.warning_inverse,
			tagente_modulo.critical_instructions,
			tagente_modulo.warning_instructions,
			tagente_modulo.unknown_instructions,
			tagente_estado.utimestamp AS utimestamp";
		
		$sql_total = "SELECT count(*)";
		
		$sql = " FROM tagente, tagente_modulo, tagente_estado" . 
			$sql_conditions_all;
		
		$sql_limit = "ORDER BY tagente.nombre ASC ";
		if (!$this->all_modules) {
			$sql_limit = " LIMIT " . (int)($page * $system->getPageSize()) . "," . (int)$system->getPageSize();
		}
		
		if ($system->getConfig('metaconsole')) {
			$servers = db_get_all_rows_sql ('SELECT *
				FROM tmetaconsole_setup
				WHERE disabled = 0');
			if ($servers === false)
				$servers = array();
			
			//$modules_db = array();
			$total = 0;
			foreach ($servers as $server) {
				if (metaconsole_connect($server) != NOERR)
					continue;
				
				$temp_modules = db_get_all_rows_sql($sql_select . $sql . $sql_limit);
				
				foreach ($temp_modules as $result_element_key => $result_element_value) {
					array_push($modules_db, $result_element_value);
				}
				$total += db_get_value_sql($sql_total. $sql);
				
				metaconsole_restore_db();
			}
			
		}
		else {
			$total = db_get_value_sql($sql_total. $sql);
			$modules_db = db_get_all_rows_sql($sql_select . $sql . $sql_limit);
		}
		
		if (empty($modules_db)) {
			$modules_db = array();
		}
		else {
			$modules = array();
			foreach ($modules_db as $module) {
				$row = array();
				
				$row[0] =
				$row[__('Module name')] =
					'<span class="data module_name">' .
					ui_print_truncate_text($module['module_name'], 30, false) .
					"</span>";
				
				if ($this->columns['agent']) {
					$row[1] = $row[__('Agent name')] =
						'<span class="data"><span class="show_collapside" style="display: none; font-weight: bolder;">' . __('Agent') . ' </span>' .
						ui_print_truncate_text($module['agent_name'], 50, false) .
						'</span>';
				}
				if ($module['utimestamp'] == 0 && (($module['module_type'] < 21 ||
					$module['module_type'] > 23) && $module['module_type'] != 100)) {
					$row[5] = $row[__('Status')] = ui_print_status_image(STATUS_MODULE_NO_DATA,
						__('NOT INIT'), true);
				}
				elseif ($module["estado"] == 0) {
					$row[5] = $row[__('Status')] = ui_print_status_image(STATUS_MODULE_OK,
						__('NORMAL') . ": " . $module["datos"], true);
				}
				elseif ($module["estado"] == 1) {
					$row[5] = $row[__('Status')] = ui_print_status_image(STATUS_MODULE_CRITICAL,
						__('CRITICAL') . ": " . $module["datos"], true);
				}
				elseif ($module["estado"] == 2) {
					$row[5] = $row[__('Status')] = ui_print_status_image(STATUS_MODULE_WARNING,
						__('WARNING') . ": " . $module["datos"], true);
				}
				else {
					$last_status =  modules_get_agentmodule_last_status(
						$module['id_agente_modulo']);
					switch($last_status) {
						case 0:
							$row[5] = $row[__('Status')] = ui_print_status_image(STATUS_MODULE_UNKNOWN,
								__('UNKNOWN') . " - " . __('Last status') . " " .
								__('NORMAL') . ": " . $module["datos"], true);
							break;
						case 1:
							$row[5] = $row[__('Status')] = ui_print_status_image(STATUS_MODULE_UNKNOWN,
								__('UNKNOWN') . " - " . __('Last status') ." " .
								__('CRITICAL') . ": " . $module["datos"], true);
							break;
						case 2:
							$row[5] = $row[__('Status')] = ui_print_status_image(STATUS_MODULE_UNKNOWN,
								__('UNKNOWN') . " - " . __('Last status') . " " .
								__('WARNING') . ": " . $module["datos"], true);
							break;
					}
				}
			
				$row[__('Interval')] =
					($module['module_interval'] == 0) ? human_time_description_raw($module['agent_interval'], false, 'tiny') : human_time_description_raw($module['module_interval'], false, 'tiny');
				
				$row[4] = $row[__('Interval')] = '<span class="data"><span class="show_collapside" style="display: none; font-weight: bolder;">' . __('Interval.') . ' </span>' .
					$row[__('Interval')] .
					'</span>';
				
				
				$row[6] = $row[__('Timestamp')] =
					'<span class="data"><span class="show_collapside" style="display: none; font-weight: bolder;">' . __('Last update.') . ' </span>' .
					ui_print_timestamp($module["utimestamp"], true, array('units' => 'tiny')) . '</span>';
				if (is_numeric($module["datos"])) {
					$output = format_numeric($module["datos"]);
					
					// Show units ONLY in numeric data types
					if (isset($module["unit"])) {
						$output .= "&nbsp;" .
							'<i>'. io_safe_output($module["unit"]) . '</i>';
					}
				}
				else {
					$is_web_content_string =
						(bool)db_get_value_filter('id_agente_modulo',
						'tagente_modulo',
						array('id_agente_modulo' => $module['id_agente_modulo'],
							'id_tipo_modulo' => $id_type_web_content_string));
					
					//Fixed the goliat sends the strings from web
					//without HTML entities
					if ($is_web_content_string) {
						$module['datos'] = io_safe_input($module['datos']);
					}
					
					//Fixed the data from Selenium Plugin
					if ($module['datos'] != strip_tags($module['datos'])) {
						$module['datos'] = io_safe_input($module['datos']);
					}
					
					if ($is_web_content_string) {
						$module_value = $module["datos"];
					}
					else {
						$module_value = io_safe_output($module["datos"]);
					}
					
					$sub_string = substr(io_safe_output($module["datos"]), 0, 12);
					if ($module_value == $sub_string) {
						$output = $module_value;
					}
					else {
						$output = $sub_string;
					}
				}
				
				
				$is_snapshot = is_snapshot_data($module["datos"]);
	
				$handle = "snapshot" . "_" . $module["id_agente_modulo"];
				$url = 'include/procesos.php?agente=' . $module["id_agente_modulo"];
		
				$link = "window.open('../operation/agentes/snapshot_view.php?" .
				"id=" . $module["id_agente_modulo"] .
				"&refr=" . $module["module_interval"]."','".$handle."','width=700, height=480')";
	
				if ($is_snapshot) {
				
				if (is_image_data($module["datos"])) {	
					$row[7] = $row[__('Data')] = '<a href="javascript:' . $link . '">' .
					html_print_image("images/photo.png", true,
					array("border" => '0',
						"alt" => "",
						"title" => __("Snapshot view"))) . '</a> &nbsp;&nbsp;';
				}
				else {
					$row[7] = $row[__('Data')] = '<a href="javascript:' . $link . '">' .
                                html_print_image("images/default_list.png", true,
                                        array("border" => '0',
                                                "alt" => "",
                                                "title" => __("Snapshot view"))) . '</a> &nbsp;&nbsp;';

																							}
		   }			 
			 else{
				 
				$row[7] = $row[__('Data')] = '<span style="white-space: nowrap;">' .
	 			'<span style="display: none;" class="show_collapside">' .
	 				$row[__('Status')] . '&nbsp;&nbsp;</span>' .
	 			'<a data-ajax="false" class="ui-link" ' .
	 				'href="index.php?page=module_graph&id=' . 
	 			$module['id_agente_modulo'] . '&id_agent=' . 
	 			$this->id_agent . '">' . $output . '</a>' . '</span>';
				 
			 }
				
				
		/*
		
			'<span style="white-space: nowrap;">' .
			'<span style="display: none;" class="show_collapside">' .
				$row[__('Status')] . '&nbsp;&nbsp;</span>' .
			'<a data-ajax="false" class="ui-link" ' .
				'href="index.php?page=module_graph&id=' . 
			$module['id_agente_modulo'] . '&id_agent=' . 
			$this->id_agent . '">' . $output . '</a>' . '</span>';*/
				
				if (!$ajax) {
					if ($this->columns['agent']) {
						unset($row[0]);
					}
					unset($row[1]);
					unset($row[2]);
					unset($row[4]);
					unset($row[5]);
					unset($row[6]);
					unset($row[7]);
				}
				
				$modules[$module['id_agente_modulo']] = $row;
			}
		}
		
		return array('modules' => $modules, 'total' => $total);
	}
	
	public function listModulesHtml($page = 0, $return = false) {
		$system = System::getInstance();
		$ui = Ui::getInstance();
		
		$listModules = $this->getListModules($page);
		//$ui->debug($listModules, true);
		
		if ($listModules['total'] == 0) {
			$html = '<p class="empty_advice">' . __('No modules') . '</p>';
			if (!$return) {
				$ui->contentAddHtml($html);
			}
			else {
				return $html;
			}
		}
		else {
			if (!$return) {
				$table = new Table();
				$table->id = 'list_Modules';
				$table->importFromHash($listModules['modules']);
				
				$ui->contentAddHtml($table->getHTML());
			}
			else {
				foreach ($listModules['modules'] as $key => $module) {
					$listModules['modules'][$key][__('Status')] .=
						'<span style="display: none;" class="show_collapside">' .
						$listModules['modules'][$key][__('Data')] . 
						'</span>';
				}
				
				$table = new Table();
				$table->id = 'list_agent_Modules';
				
				$table->importFromHash($listModules['modules']);
				
				$html = $table->getHTML();
				
				return $html;
			}
			
			if (!$this->all_modules) {
				if ($system->getPageSize() < $listModules['total']) {
					$ui->contentAddHtml('<div id="loading_rows">' .
							html_print_image('images/spinner.gif', true, false, false, false, false, true) .
							' ' . __('Loading...') .
						'</div>');
					
					$this->addJavascriptAddBottom();
				}
			}
		}
		
		$ui->contentAddLinkListener('list_Modules');
	}
	
	private function addJavascriptAddBottom() {
		$ui = Ui::getInstance();
		
		$ui->contentAddHtml("<script type=\"text/javascript\">
				var load_more_rows = 1;
				var page = 1;
				
				function custom_scroll() {
					if (load_more_rows) {
						if ($(this).scrollTop() + $(this).height()
							>= ($(document).height() - 100)) {
							
							load_more_rows = 0;
							
							postvars = {};
							postvars[\"action\"] = \"ajax\";
							postvars[\"parameter1\"] = \"modules\";
							postvars[\"parameter2\"] = \"get_modules\";
							postvars[\"group\"] = $(\"select[name='group']\").val();
							postvars[\"status\"] = $(\"select[name='status']\").val();
							postvars[\"type\"] = $(\"select[name='module_group']\").val();
							postvars[\"tag\"] = $(\"select[name='tag']\").val();
							postvars[\"free_search\"] = $(\"input[name='free_search']\").val();
							postvars[\"page\"] = page;
							page++;
							
							$.post(\"index.php\",
								postvars,
								function (data) {
									if (data.end) {
										$(\"#loading_rows\").hide();
									}
									else {
										$.each(data.modules, function(key, module) {
											$(\"table#list_Modules tbody\").append(\"<tr>\" +
													\"<th class='head_vertical'></th>\" +
													\"<td class='cell_1'><b class='ui-table-cell-label'>" . __('Module name') . "</b>\" + module[0] + \"</td>\" +
													\"<td class='cell_0'><b class='ui-table-cell-label'>" . __('Agent name') . "</b>\" + module[1] + \"</td>\" +
													\"<td class='cell_2'><b class='ui-table-cell-label'>" . __('Status') . "</b>\" + module[5] + \"</td>\" +
													\"<td class='cell_3'><b class='ui-table-cell-label'>" . __('Interval') . "</b>\" + module[4] + \"</td>\" +
													\"<td class='cell_4'><b class='ui-table-cell-label'>" . __('Timestamp') . "</b>\" + module[6] + \"</td>\" +
													\"<td class='cell_5'><b class='ui-table-cell-label'>" . __('Data') . "</b>\" + module[7] + \"</td>\" +
												\"</tr>\");
											});
										
										load_more_rows = 1;
										refresh_link_listener_list_Modules()
									}
									
									
								},
								\"json\");
						}
					}
				}
				
				$(document).ready(function() {
					$(window).bind(\"scroll\", function () {
						custom_scroll();
					});
					
					$(window).on(\"touchmove\", function(event) {
						custom_scroll();
					});
				});
			</script>");
	}
	
	private function filterEventsGetString() {
		if ($this->default) {
			return __("(Default)");
		}
		else {
			
			$filters_to_serialize = array();
			
			if (!$this->default_filters['group']) {
				$filters_to_serialize[] = sprintf(__("Group: %s"),
					groups_get_name($this->group, true));
			}
			if (!$this->default_filters['module_group']) {
				$module_group = db_get_value('name',
					'tmodule_group', 'id_mg', $this->module_group);
				$module_group = io_safe_output($module_group);
				
				$filters_to_serialize[] = sprintf(__("Module group: %s"),
					$module_group);
			}
			if (!$this->default_filters['status']) {
				$filters_to_serialize[] = sprintf(__("Status: %s"),
					$this->list_status[$this->status]);
			}
			if (!$this->default_filters['free_search']) {
				$filters_to_serialize[] = sprintf(__("Free Search: %s"),
					$this->free_search);
			}
			if (!$this->default_filters['tag']) {
				$tag_name = tags_get_name($this->tag);
					$filters_to_serialize[] = sprintf(__("Tag: %s"),
					$tag_name);
			}
			
			$string = '(' . implode(' - ', $filters_to_serialize) . ')';
			
			
			
			//~ $status = $this->list_status[$this->status];
			//~ 
			//~ $group = groups_get_name($this->group, true);
			//~ 
			//~ $module_group = db_get_value('name',
				//~ 'tmodule_group', 'id_mg', $this->module_group);
			//~ $module_group = io_safe_output($module_group);
			//~ 
			//~ $string = sprintf(
				//~ __("(Status: %s - Group: %s - Module group: %s - Free Search: %s)"),
				//~ $status, $group, $module_group, $this->free_search);
			
			return $string;
		}
	}
}

?>
