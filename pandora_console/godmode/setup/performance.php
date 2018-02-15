<?php 

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2009 Artica Soluciones Tecnologicas
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

if (! check_acl ($config['id_user'], 0, "PM") && ! is_user_admin ($config['id_user'])) {
	db_pandora_audit("ACL Violation", "Trying to access Setup Management");
	require ("general/noaccess.php");
	return;
}
// Load enterprise extensions
//~ enterprise_include_once ('godmode/setup/setup.php');

/*
NOTICE FOR DEVELOPERS:

Update operation is done in config_process.php
This is done in that way so the user can see the changes inmediatly.
If you added a new token, please check config_update_config() in functions_config.php
to add it there.
*/

$table = new StdClass();
$table->width = '100%';
$table->class = "databox filters";
$table->data = array ();
$table->style[0] = "font-weight: bold";

$table->size[0] = '70%';
$table->size[1] = '30%';

enterprise_hook('enterprise_warnings_history_days');

$table->data[1][0] = __('Max. days before delete events') . ui_print_help_tip(__('If the compaction or purge of the data is more frequent than the events deletion, anomalies in module graphs could appear'), true);
$table->data[1][1] = html_print_input_text ('event_purge', $config["event_purge"], '', 5, 5, true);

$table->data[2][0] = __('Max. days before delete traps');
$table->data[2][1] = html_print_input_text ('trap_purge', $config["trap_purge"], '', 5, 5, true);

$table->data[3][0] = __('Max. days before delete audit events');
$table->data[3][1] = html_print_input_text ('audit_purge', $config["audit_purge"], '', 5, 5, true);

$table->data[4][0] = __('Max. days before delete string data');
$table->data[4][1] = html_print_input_text ('string_purge', $config["string_purge"], '', 5, 5, true);

$table->data[5][0] = __('Max. days before delete GIS data');
$table->data[5][1] = html_print_input_text ('gis_purge', $config["gis_purge"], '', 5, 5, true);

$table->data[6][0] = __('Max. days before purge') . ui_print_help_tip(__('Configure a purge period more frequent than a compact data period has no sense'), true);
$table->data[6][1] = html_print_input_text ('days_purge', $config["days_purge"], '', 5, 5, true);

$table->data[7][0] = __('Max. days before compact data');
$table->data[7][1] = html_print_input_text ('days_compact', $config["days_compact"], '', 5, 5, true);

$table->data[8][0] = __('Max. days before delete unknown modules');
$table->data[8][1] = html_print_input_text ('days_delete_unknown', $config["days_delete_unknown"], '', 5, 5, true);

$table_other = new stdClass();
$table_other->width = '100%';
$table_other->class = 'databox filters';
$table_other->data = array ();
$table_other->style[0] = "font-weight: bold";

$table_other->size[0] = '70%';
$table_other->size[1] = '30%';

$table_other->data[1][0] = __('Item limit for realtime reports') . ui_print_help_tip(__('Set a value too high cause a slowdown on console and a performance penalty in the system.'), true);
$table_other->data[1][1] = html_print_input_text ('report_limit',
	$config["report_limit"], '', 5, 5, true);
	
$table_other->data[2][0] = __('Compact interpolation in hours (1 Fine-20 bad)') . ui_print_help_tip(__('Data will be compacted in intervals of the specified length.'), true);
$table_other->data[2][1] = html_print_input_text ('step_compact',
	$config["step_compact"], '', 5, 5, true);

$intervals = array ();
$intervals[SECONDS_1HOUR] = __('1 hour');
$intervals[SECONDS_12HOURS] = __('12 hours');
$intervals[SECONDS_1DAY] = __('Last day');
$intervals[SECONDS_2DAY] = __('2 days');
$intervals[SECONDS_10DAY] = __('10 days');
$intervals[SECONDS_1WEEK] = __('Last week');
$intervals[SECONDS_2WEEK] = __('2 weeks');
$intervals[SECONDS_1MONTH] = __('Last month');

$table_other->data[3][0] = __('Default hours for event view');
$table_other->data[3][1] = html_print_input_text ('event_view_hr', $config["event_view_hr"], '', 5, 5, true);

$table_other->data[5][0] = __('Use realtime statistics');
$table_other->data[5][1] = __('Yes').'&nbsp;'.html_print_radio_button ('realtimestats', 1, '', $config["realtimestats"], true).'&nbsp;&nbsp;';
$table_other->data[5][1] .= __('No').'&nbsp;'.html_print_radio_button ('realtimestats', 0, '', $config["realtimestats"], true);

$table_other->data[6][0] = __('Batch statistics period (secs)') . ui_print_help_tip(__('If realtime statistics are disabled, statistics interval resfresh will be set here.'), true);
$table_other->data[6][1] = html_print_input_text ('stats_interval', $config["stats_interval"], '', 5, 5, true);

$table_other->data[7][0] = __('Use agent access graph') . ui_print_help_icon("agent_access", true);
$table_other->data[7][1] = __('Yes').'&nbsp;'.html_print_radio_button ('agentaccess', 1, '', $config["agentaccess"], true).'&nbsp;&nbsp;';
$table_other->data[7][1] .= __('No').'&nbsp;'.html_print_radio_button ('agentaccess', 0, '', $config["agentaccess"], true);

$table_other->data[8][0] = __('Max. recommended number of files in attachment directory') . ui_print_help_tip(__('This number is the maximum number of files in attachment directory. If this number is reached then a warning message will appear in the header notification space.'), true);
$table_other->data[8][1] = html_print_input_text ('num_files_attachment', $config["num_files_attachment"], '', 5, 5, true);

$table_other->data[9][0] = __('Delete not init modules');
$table_other->data[9][1] = __('Yes').'&nbsp;'.html_print_radio_button ('delete_notinit', 1, '', $config["delete_notinit"], true).'&nbsp;&nbsp;';
$table_other->data[9][1] .= __('No').'&nbsp;'.html_print_radio_button ('delete_notinit', 0, '', $config["delete_notinit"], true);

echo '<form id="form_setup" method="post">';
echo "<fieldset>";
echo "<legend>" . __('Database maintenance options') . "</legend>";
html_print_input_hidden ('update_config', 1);
html_print_table ($table);
echo "</fieldset>";

echo "<fieldset>";
echo "<legend>" . __('Others') . "</legend>";
html_print_input_hidden ('update_config', 1);
html_print_table ($table_other);
echo "</fieldset>";
echo '<div class="action-buttons" style="width: '.$table->width.'">';
html_print_submit_button (__('Update'), 'update_button', false, 'class="sub upd"');
echo '</div>';
echo '</form>';
?>


