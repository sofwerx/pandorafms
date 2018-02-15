<?php

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2011 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the  GNU Lesser General Public License
// as published by the Free Software Foundation; version 2

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

/**
 * Generates a trap
 *
 * @param string Destiny host address.
 * @param string Snmp community.
 * @param string Snmp OID.
 * @param string Snmp agent.
 * @param string Data of the trap.
 * @param string Snmp especific OID.
 */
function snmp_generate_trap($snmp_host_address, $snmp_community, $snmp_oid, $snmp_agent, $snmp_data, $snmp_type) {
	// Call snmptrap
	if (empty($config['snmptrap'])) {
		switch (PHP_OS) {
			case "FreeBSD":
				$snmptrap_bin = '/usr/local/bin/snmptrap';
				break;
			case "NetBSD":
				$snmptrap_bin = '/usr/pkg/bin/snmptrap';
				break;
			default:
				$snmptrap_bin = 'snmptrap';
				break;
		}
	}
	else {
		$snmptrap_bin = $config['snmptrap'];
	}

	$command = "$snmptrap_bin -v 1 -c " . escapeshellarg($snmp_community) . " " . escapeshellarg($snmp_host_address) . " " . escapeshellarg($snmp_oid) . " "
			. escapeshellarg($snmp_agent) . " " . escapeshellarg($snmp_type) . " " . escapeshellarg($snmp_data) . " 0 2>&1";
	
	$output = null;
	exec($command, $output, $return);
	
	if ($return == 0) {
		return true;
	}
	else {
		return implode(' ', $output);
	}
}

?>
