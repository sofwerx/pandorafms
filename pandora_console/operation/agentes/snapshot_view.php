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


if (! isset($_SESSION['id_usuario'])) {
	session_start();
	//session_write_close();
}

require_once ('../../include/config.php');
require_once ($config['homedir'] . '/include/auth/mysql.php');
require_once ($config['homedir'] . '/include/functions.php');
require_once ($config['homedir'] . '/include/functions_db.php');
require_once ($config['homedir'] . '/include/functions_reporting.php');
require_once ($config['homedir'] . '/include/functions_graph.php');
require_once ($config['homedir'] . '/include/functions_modules.php');
require_once ($config['homedir'] . '/include/functions_agents.php');
require_once ($config['homedir'] . '/include/functions_tags.php');

check_login ();

$user_language = get_user_language ($config['id_user']);
if (file_exists ('../../include/languages/'.$user_language.'.mo')) {
	$l10n = new gettext_reader (new CachedFileReader ('../../include/languages/'.$user_language.'.mo'));
	$l10n->load_tables();
}

$id = get_parameter('id');
$label = get_parameter ("label");

// TODO - Put ACL here
?>
<html>
	<head>
		<?php
		// Parsing the refresh before sending any header
		$refresh = (int) get_parameter ("refr", -1);
		if ($refresh > 0) {
			$query = ui_get_url_refresh (false);
			echo '<meta http-equiv="refresh" content="'.$refresh.'; URL='.$query.'" />';
		}
		?>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>Pandora FMS Snapshot data view for module (<?php echo $label; ?>)</title>
		<script type='text/javascript' src='../../include/javascript/jquery-1.7.1.js'></script>
	</head>
	<body style='background:#000; color: #ccc;'>
		<?php
		$row = db_get_row_sql("SELECT *
			FROM tagente_estado
			WHERE id_agente_modulo = $id");
		
		echo "<h2 style='text-align:center;' id='title_snapshot_view'>";
		echo __("Current data at");
		echo " ";
		echo $row["timestamp"];
		echo "</h2>";
		$datos = io_safe_output($row["datos"]);
		if (is_image_data($datos)) {
			echo '<center><img src="' . $datos . '" alt="image"/></center>';
		}
		else {
			$datos = preg_replace ('/</', '&lt;', $datos);
			$datos = preg_replace ('/>/', '&gt;', $datos);
			$datos = preg_replace ('/\n/i','<br>',$datos);
			$datos = preg_replace ('/\s/i','&nbsp;',$datos);
			echo "<div id='result_div' style='width: 100%; height: 100%; overflow: scroll; padding: 10px; font-size: 14px; line-height: 16px; font-family: mono,monospace; text-align: left'>";
			echo $datos;
			echo "</div>";
		?>
		<script type="text/javascript">
			function getScrollbarWidth() {
				var div = $('<div style="width:50px;height:50px;overflow:hidden;position:absolute;top:-200px;left:-200px;"><div style="height:100px;"></div></div>');
				$('body').append(div);
				var w1 = $('div', div).innerWidth();
				div.css('overflow-y', 'auto');
				var w2 = $('div', div).innerWidth();
				$(div).remove();
				
				return (w1 - w2);
			}
			
			$(document).ready(function() {
				width = $("#result_div").css("width");
				width = width.replace("px", "");
				width = parseInt(width);
				$("#result_div").css("width", (width - getScrollbarWidth()) + "px");
				
				height = $("#result_div").css("height");
				height = height.replace("px", "");
				height = parseInt(height);
				$("#result_div").css("height", (height - getScrollbarWidth() - $("#title_snapshot_view").height() - 16) + "px");
			});
		</script>
		<?php
		}
		?>
	</body>
</html>
