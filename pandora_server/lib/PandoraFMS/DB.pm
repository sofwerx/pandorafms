package PandoraFMS::DB;
##########################################################################
# Database Package
# Pandora FMS. the Flexible Monitoring System. http://www.pandorafms.org
##########################################################################
# Copyright (c) 2005-2011 Artica Soluciones Tecnologicas S.L
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public License
# as published by the Free Software Foundation; version 2
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
##########################################################################

use strict;
use warnings;
use DBI;
use PandoraFMS::Tools;

#use Data::Dumper;

require Exporter;

our @ISA = ("Exporter");
our %EXPORT_TAGS = ( 'all' => [ qw( ) ] );
our @EXPORT_OK = ( @{ $EXPORT_TAGS{'all'} } );
our @EXPORT = qw(
		add_address
		add_new_address_agent
		db_concat
		db_connect
		db_delete_limit
		db_disconnect
		db_do
		db_get_lock
		db_insert
		db_insert_get_values
		db_process_insert
		db_process_update
		db_release_lock
		db_string
		db_text
		db_update
		db_update_get_values
		get_action_id
		get_addr_id
		get_address_agent
		get_agent_addr_id
		get_agent_id
		get_agent_address
		get_agent_group
		get_agent_name
		get_agent_module_id
		get_alert_template_module_id
		get_alert_template_name
		get_db_rows
		get_db_rows_limit
		get_db_single_row
		get_db_value
		get_db_value_limit
		get_first_server_name
		get_group_id
		get_group_name
		get_module_agent_id
		get_module_group_id
		get_module_group_name
		get_module_id
		get_module_name
		get_nc_profile_name
		get_os_id
		get_plugin_id
		get_profile_id
		get_priority_name
		get_server_id
		get_tag_id
		get_group_name
		get_template_id
		get_template_module_id
		get_user_disabled
		get_user_exists
		get_user_profile_id
		is_agent_address
		is_group_disabled
		get_agent_status
		get_agent_modules
		get_agentmodule_status
		get_agentmodule_status_str
		get_agentmodule_data
		$RDBMS
		$RDBMS_QUOTE
		$RDBMS_QUOTE_STRING
	);

# Relational database management system in use
our $RDBMS = '';

# For fields, character used to quote reserved words in the current RDBMS
our $RDBMS_QUOTE = '';

# For strings, Character used to quote in the current RDBMS
our $RDBMS_QUOTE_STRING = '';

##########################################################################
## Connect to the DB.
##########################################################################
sub db_connect ($$$$$$) {
	my ($rdbms, $db_name, $db_host, $db_port, $db_user, $db_pass) = @_;
	
	if ($rdbms eq 'mysql') {
		$RDBMS = 'mysql';
		$RDBMS_QUOTE = '`';
		$RDBMS_QUOTE_STRING = '"';
		
		# Connect to MySQL
		my $dbh = DBI->connect("DBI:mysql:$db_name:$db_host:$db_port", $db_user, $db_pass, { RaiseError => 1, AutoCommit => 1 });
		return undef unless defined ($dbh);
		
		# Enable auto reconnect
		$dbh->{'mysql_auto_reconnect'} = 1;
		
		# Enable character semantics
		$dbh->{'mysql_enable_utf8'} = 1;
		
        # Tell the server to return UTF-8 strings.
		$dbh->do("SET NAMES 'utf8';") if ($^O eq 'MSWin32');

		return $dbh;
	}
	elsif ($rdbms eq 'postgresql') {
		$RDBMS = 'postgresql';
		$RDBMS_QUOTE = '"';
		$RDBMS_QUOTE_STRING = "'";
		
		# Connect to PostgreSQL
		my $dbh = DBI->connect("DBI:Pg:dbname=$db_name;host=$db_host;port=$db_port", $db_user, $db_pass, { RaiseError => 1, AutoCommit => 1 });
		return undef unless defined ($dbh);
		
		return $dbh;
	}
	elsif ($rdbms eq 'oracle') {
		$RDBMS = 'oracle';
		$RDBMS_QUOTE = '"';
		$RDBMS_QUOTE_STRING = '\'';
		
		# Connect to Oracle
		my $dbh = DBI->connect("DBI:Oracle:dbname=$db_name;host=$db_host;port=$db_port;sid=$db_name", $db_user, $db_pass, { RaiseError => 1, AutoCommit => 1 });
		return undef unless defined ($dbh);
		
		# Set date format
		$dbh->do("ALTER SESSION SET NLS_TIMESTAMP_FORMAT='YYYY-MM-DD HH24:MI:SS'");
		$dbh->do("ALTER SESSION SET NLS_NUMERIC_CHARACTERS='.,'");
		
		# Configuration to avoid errors when working with CLOB columns
		$dbh->{'LongReadLen'} = 66000;
		$dbh->{'LongTruncOk'} = 1;
		
		return $dbh;
	}
	
	return undef;
}

########################################################################
## Disconnect from the DB. 
########################################################################
sub db_disconnect ($) {
	my $dbh = shift;

	$dbh->disconnect();
}

########################################################################
## Return action ID given the action name.
########################################################################
sub get_action_id ($$) {
	my ($dbh, $action_name) = @_;

	my $rc = get_db_value ($dbh, "SELECT id FROM talert_actions WHERE name = ?", $action_name);
	return defined ($rc) ? $rc : -1;
}

########################################################################
## Return agent ID given the agent name.
########################################################################
sub get_agent_id ($$) {
	my ($dbh, $agent_name) = @_;

	my $rc = get_db_value ($dbh, "SELECT id_agente FROM tagente WHERE nombre = ? OR direccion = ?", safe_input($agent_name), $agent_name);
	return defined ($rc) ? $rc : -1;
}

########################################################################
## Return server ID given the name of server.
########################################################################
sub get_server_id ($$$) {
	my ($dbh, $server_name, $server_type) = @_;

	my $rc = get_db_value ($dbh, "SELECT id_server FROM tserver
					WHERE name = ? AND server_type = ?",
					$server_name, $server_type);
	return defined ($rc) ? $rc : -1;
}

########################################################################
## Return the ID of a tag given the tag name.
########################################################################
sub get_tag_id ($$) {
	my ($dbh, $tag_name) = @_;

	my $rc = get_db_value ($dbh, "SELECT id_tag FROM ttag
					WHERE name = ?",
					safe_input($tag_name));
	return defined ($rc) ? $rc : -1;
}

########################################################################
## Return the first enabled server name found.
########################################################################
sub get_first_server_name ($) {
	my ($dbh) = @_;

	my $rc = get_db_value ($dbh, "SELECT name FROM tserver");
					
	return defined ($rc) ? $rc : "";
}

########################################################################
## Return group ID given the group name.
########################################################################
sub get_group_id ($$) {
	my ($dbh, $group_name) = @_;

	my $rc = get_db_value ($dbh, 'SELECT id_grupo FROM tgrupo WHERE ' . db_text ('nombre') . ' = ?', safe_input($group_name));
	return defined ($rc) ? $rc : -1;
}

########################################################################
## Return OS ID given the OS name.
########################################################################
sub get_os_id ($$) {
	my ($dbh, $os_name) = @_;

	my $rc = get_db_value ($dbh, "SELECT id_os FROM tconfig_os WHERE name = ?", $os_name);
	return defined ($rc) ? $rc : -1;
}

##########################################################################
## SUB get_agent_name (agent_id)
## Return agent group id, given "agent_id"
##########################################################################
sub get_agent_group ($$) {
	my ($dbh, $agent_id) = @_;
	
	my $group_id = get_db_value ($dbh, "SELECT id_grupo
		FROM tagente
		WHERE id_agente = ?", $agent_id);
	return 0 unless defined ($group_id);
	
	return $group_id;
}

########################################################################
## SUB get_agent_name (agent_id)
## Return agent name, given "agent_id"
########################################################################
sub get_agent_name ($$) {
	my ($dbh, $agent_id) = @_;
	
	return get_db_value ($dbh, "SELECT nombre
		FROM tagente
		WHERE id_agente = ?", $agent_id);
}

########################################################################
## SUB agents_get_modules (agent_id, fields, filters)
## Return the list of modules, given "agent_id"
########################################################################
sub get_agent_modules ($$$$$) {
	my ($pa_config, $dbh, $agent_id, $fields, $filters) = @_;
	
	my $str_filter = '';
	
	foreach my $key (keys %$filters) {
		$str_filter .= ' AND ' . $key . " = " . $filters->{$key};
	}
	
	my @rows = get_db_rows($dbh, "SELECT *
		FROM tagente_modulo
		WHERE id_agente = ?" . $str_filter, $agent_id);
	
	return @rows;
}

########################################################################
## SUB get_agentmodule_data (id_agent_module, period, date)
## Return The data for module in a period of time.
########################################################################

sub get_agentmodule_data ($$$$$) {
	my ($pa_config, $dbh, $id_agent_module, $period, $date) = @_;
	if ($date < 1) {
		# Get current timestamp
		$date = time ();
	}
	
	my $datelimit = $date - $period;
	
	my @rows = get_db_rows($dbh,
		"SELECT datos AS data, utimestamp
		FROM tagente_datos
		WHERE id_agente_modulo = ?
			AND utimestamp > ? AND utimestamp <= ?
		ORDER BY utimestamp ASC",
		$id_agent_module, $datelimit, $date);
	
	#logger($pa_config, "SELECT datos AS data, utimestamp
	#	FROM tagente_datos
	#	WHERE id_agente_modulo = " . $id_agent_module . "
	#		AND utimestamp > " . $datelimit . " AND utimestamp <= " . $date . "
	#	ORDER BY utimestamp ASC", 1);
	
	return @rows;
}

########################################################################
## SUB get_agentmodule_status (agent_module_id)
## Return agent module status. given "agent_module_id"
########################################################################
sub get_agentmodule_status($$$) {
	my ($pa_config, $dbh, $agent_module_id) = @_;
	
	my $status = get_db_value($dbh, 'SELECT estado
			FROM tagente_estado
			WHERE id_agente_modulo = ?', $agent_module_id);
	
	return $status;
}

########################################################################
## Return the status of an agent module as a string.
########################################################################
sub get_agentmodule_status_str($$$) {
	my ($pa_config, $dbh, $agent_module_id) = @_;
	
	my $status = get_db_value($dbh, 'SELECT estado
			FROM tagente_estado
			WHERE id_agente_modulo = ?', $agent_module_id);
	
	return 'Normal' if ($status == 0);
	return 'Critical' if ($status == 1);
	return 'Warning' if ($status == 2);
	return 'Unknown' if ($status == 3);
	return 'Not init' if ($status == 4);
	return 'N/A';
}

########################################################################
## SUB get_get_status (agent_id)
## Return agent status, given "agent_id"
########################################################################
sub get_agent_status ($$$) {
	my ($pa_config, $dbh, $agent_id) = @_;
	my %status_count = (
		STATUS_CRITICAL() => 0,	# Highest priority status.
		STATUS_NORMAL() => 0,
		STATUS_WARNING() => 0,
		STATUS_UNKNOWN() => 0,
		STATUS_NOTINIT() => 0	# Lowest priority status.
	);  
	
	my @modules = get_agent_modules ($pa_config, $dbh, $agent_id, 'id_agente_modulo', {'disabled' => 0});
	foreach my $module (@modules) { 
		my $module_status = get_agentmodule_status($pa_config, $dbh, $module->{'id_agente_modulo'});
		return STATUS_CRITICAL if ($module_status == STATUS_CRITICAL);

		$status_count{$module_status} += 1;
	}   

	return STATUS_WARNING if ($status_count{STATUS_WARNING()} > 0);
	return STATUS_UNKNOWN if ($status_count{STATUS_UNKNOWN()} > 0);
	return STATUS_NORMAL if ($status_count{STATUS_NORMAL()} > 0);
	return STATUS_NOTINIT;
}


########################################################################
## SUB get_module_agent_id (agent_module_id)
## Return agent id, given "agent_module_id"
########################################################################
sub get_module_agent_id ($$) {
	my ($dbh, $agent_module_id) = @_;
	
	return get_db_value ($dbh, "SELECT id_agente FROM tagente_modulo WHERE id_agente_modulo = ?", $agent_module_id);
}

########################################################################
## SUB get_agent_address (id_agente)
## Return agent address, given "agent_id"
########################################################################
sub get_agent_address ($$) {
	my ($dbh, $agent_id) = @_;
	
	return get_db_value ($dbh, "SELECT direccion FROM tagente WHERE id_agente = ?", $agent_id);
}

########################################################################
## SUB get_module_name(module_id)
## Return the module name, given "module_id"
########################################################################
sub get_module_name ($$) {
	my ($dbh, $module_id) = @_;
	
	return get_db_value ($dbh, "SELECT nombre FROM tagente_modulo WHERE id_agente_modulo = ?", $module_id);
}

########################################################################
## Return module id given the module name and agent id.
########################################################################
sub get_agent_module_id ($$$) {
	my ($dbh, $module_name, $agent_id) = @_;
	
	my $rc = get_db_value ($dbh, "SELECT id_agente_modulo FROM tagente_modulo WHERE delete_pending = 0 AND nombre = ? AND id_agente = ?", safe_input($module_name), $agent_id);
	return defined ($rc) ? $rc : -1;
}

##########################################################################
## Return template id given the template name.
##########################################################################
sub get_template_id ($$) {
	my ($dbh, $template_name) = @_;
	
	my $rc = get_db_value ($dbh, "SELECT id FROM talert_templates WHERE name = ?", safe_input($template_name));
	return defined ($rc) ? $rc : -1;
}

##########################################################################
## Return the module template id given the module id and the template id.
##########################################################################
sub get_template_module_id ($$$) {
	my ($dbh, $module_id, $template_id) = @_;
	
	my $rc = get_db_value ($dbh, "SELECT id FROM talert_template_modules WHERE id_agent_module = ? AND id_alert_template = ?", $module_id, $template_id);
	return defined ($rc) ? $rc : -1;
}

##########################################################################
## Returns true if the given group is disabled, false otherwise.
##########################################################################
sub is_group_disabled ($$) {
	my ($dbh, $group_id) = @_;
	
	return get_db_value ($dbh, "SELECT disabled FROM tgrupo WHERE id_grupo = ?", $group_id);
}

##########################################################################
## Return module ID given the module name.
##########################################################################
sub get_module_id ($$) {
	my ($dbh, $module_name) = @_;
	
	my $rc = get_db_value ($dbh, "SELECT id_tipo FROM ttipo_modulo WHERE nombre = ?", safe_input($module_name));
	return defined ($rc) ? $rc : -1;
}

##########################################################################
## Return disabled bit frin a user.
##########################################################################
sub get_user_disabled ($$) {
	my ($dbh, $user_id) = @_;
	
	my $rc = get_db_value ($dbh, "SELECT disabled FROM tusuario WHERE id_user = ?", safe_input($user_id));
	return defined ($rc) ? $rc : -1;
}

##########################################################################
## Return 1 if user exists or -1 if not
##########################################################################
sub get_user_exists ($$) {
	my ($dbh, $user_id) = @_;
	
	my $rc = get_db_value ($dbh, "SELECT id_user FROM tusuario WHERE id_user = ?", safe_input($user_id));
	return defined ($rc) ? 1 : -1;
}

##########################################################################
## Return plugin ID given the plugin name.
##########################################################################
sub get_plugin_id ($$) {
	my ($dbh, $plugin_name) = @_;
	
	my $rc = get_db_value ($dbh, "SELECT id FROM tplugin WHERE name = ?", safe_input($plugin_name));
	return defined ($rc) ? $rc : -1;
}

##########################################################################
## Return module group ID given the module group name.
##########################################################################
sub get_module_group_id ($$) {
	my ($dbh, $module_group_name) = @_;
	
	if (!defined($module_group_name) || $module_group_name eq '') {
		return 0;
	}
	
	my $rc = get_db_value ($dbh, "SELECT id_mg FROM tmodule_group WHERE name = ?", safe_input($module_group_name));
	return defined ($rc) ? $rc : -1;
}

##########################################################################
## Return module group name given the module group id.
##########################################################################
sub get_module_group_name ($$) {
	my ($dbh, $module_group_id) = @_;
	
	return get_db_value ($dbh, "SELECT name FROM tmodule_group WHERE id_mg = ?", $module_group_id);
}

##########################################################################
## Return a network component's profile name given its ID.
##########################################################################
sub get_nc_profile_name ($$) {
	my ($dbh, $nc_id) = @_;
	
	return get_db_value ($dbh, "SELECT * FROM tnetwork_profile WHERE id_np = ?", $nc_id);
}

##########################################################################
## Return user profile ID given the user id, group id and profile id.
##########################################################################
sub get_user_profile_id ($$$$) {
	my ($dbh, $user_id, $profile_id, $group_id) = @_;
	
	my $rc = get_db_value ($dbh, "SELECT id_up FROM tusuario_perfil
	                              WHERE id_usuario = ?
								  AND id_perfil = ?
								  AND id_grupo = ?",
								  safe_input($user_id),
								  $profile_id,
								  $group_id);
	return defined ($rc) ? $rc : -1;
}

##########################################################################
## Return profile ID given the profile name.
##########################################################################
sub get_profile_id ($$) {
	my ($dbh, $profile_name) = @_;
	
	my $rc = get_db_value ($dbh, "SELECT id_perfil FROM tperfil WHERE name = ?", safe_input($profile_name));
	return defined ($rc) ? $rc : -1;
}

##########################################################################
## Return a group's name given its ID.
##########################################################################
sub get_group_name ($$) {
	my ($dbh, $group_id) = @_;
	
	return get_db_value ($dbh, "SELECT nombre FROM tgrupo WHERE id_grupo = ?", $group_id);
}

########################################################################
## Get a single column returned by an SQL query as a hash reference.
########################################################################
sub get_db_value ($$;@) {
	my ($dbh, $query, @values) = @_;
	
	# Cache statements
	my $sth = $dbh->prepare_cached($query);
	
	$sth->execute(@values);
	
	# Save returned rows
	while (my $row = $sth->fetchrow_arrayref()) {
		$sth->finish();
		return defined ($row->[0]) ? $row->[0] : undef;
	}
	
	$sth->finish();
	
	return undef;
}

########################################################################
## Get a single column returned by an SQL query with a LIMIT statement
## as a hash reference.
########################################################################
sub get_db_value_limit ($$$;@) {
	my ($dbh, $query, $limit, @values) = @_;
	
	# Cache statements
	my $sth;
	if ($RDBMS ne 'oracle') {
		$sth = $dbh->prepare_cached($query . ' LIMIT ' . int($limit));
	} else {
		$sth = $dbh->prepare_cached('SELECT * FROM (' . $query . ') WHERE ROWNUM <= ' . int($limit));
	}

	$sth->execute(@values);

	# Save returned rows
	while (my $row = $sth->fetchrow_arrayref()) {
		$sth->finish();
		return defined ($row->[0]) ? $row->[0] : undef;
	}
	
	$sth->finish();
	
	return undef;
}

##########################################################################
## Get a single row returned by an SQL query as a hash reference. Returns
## -1 on error.
##########################################################################
sub get_db_single_row ($$;@) {
	my ($dbh, $query, @values) = @_;
	#my @rows;
	
	# Cache statements
	my $sth = $dbh->prepare_cached($query);
	
	$sth->execute(@values);
	
	# Save returned rows
	while (my $row = $sth->fetchrow_hashref()) {
		$sth->finish();
		return {map { lc ($_) => $row->{$_} } keys (%{$row})} if ($RDBMS eq 'oracle');
		return $row;
	}
	
	$sth->finish();
	
	return undef;
}

##########################################################################
## Get all rows returned by an SQL query as a hash reference array.
##########################################################################
sub get_db_rows ($$;@) {
	my ($dbh, $query, @values) = @_;
	my @rows;
	
	# Cache statements
	my $sth = $dbh->prepare_cached($query);
	
	$sth->execute(@values);
	
	# Save returned rows
	while (my $row = $sth->fetchrow_hashref()) {
		if ($RDBMS eq 'oracle') {
			push (@rows, {map { lc ($_) => $row->{$_} } keys (%{$row})});
		}
		else {
			push (@rows, $row);
		}
	}
	
	$sth->finish();
	return @rows;
}

########################################################################
## Get all rows (with a limit clause) returned by an SQL query
## as a hash reference array.
########################################################################
sub get_db_rows_limit ($$$;@) {
	my ($dbh, $query, $limit, @values) = @_;
	my @rows;
	
	# Cache statements
	my $sth;
	if ($RDBMS ne 'oracle') {
		$sth = $dbh->prepare_cached($query . ' LIMIT ' . $limit);
	} else {
		$sth = $dbh->prepare_cached('SELECT * FROM (' . $query . ') WHERE ROWNUM <= ' . $limit);
	}
	
	$sth->execute(@values);
	
	# Save returned rows
	while (my $row = $sth->fetchrow_hashref()) {
		if ($RDBMS eq 'oracle') {
			push (@rows, {map { lc ($_) => $row->{$_} } keys (%{$row})});
		}
		else {
			push (@rows, $row);
		}
	}
	
	$sth->finish();
	return @rows;
}

##########################################################################
## SQL delete with a LIMIT clause.
##########################################################################
sub db_delete_limit ($$$$;@) {
	my ($dbh, $from, $where, $limit, @values) = @_;
	my $sth;

	# MySQL
	if ($RDBMS eq 'mysql') {
		$sth = $dbh->prepare_cached("DELETE FROM $from WHERE $where LIMIT " . int($limit));
	}
	# PostgreSQL
	elsif ($RDBMS eq 'postgresql') {
		$sth = $dbh->prepare_cached("DELETE FROM $from WHERE $where LIMIT " . int($limit));
	}
	# Oracle
	elsif ($RDBMS eq 'oracle') {
		$sth = $dbh->prepare_cached("DELETE FROM (SELECT * FROM $from WHERE $where) WHERE ROWNUM <= " . int($limit));
	}

	$sth->execute(@values);
}

##########################################################################
## SQL insert. Returns the ID of the inserted row.
##########################################################################
sub db_insert ($$$;@) {
	my ($dbh, $index, $query, @values) = @_;
	my $insert_id = undef;
	
	
	# MySQL
	if ($RDBMS eq 'mysql') {
		$dbh->do($query, undef, @values);
		$insert_id = $dbh->{'mysql_insertid'};
	}
	# PostgreSQL
	elsif ($RDBMS eq 'postgresql') {
		$insert_id = get_db_value ($dbh, $query . ' RETURNING ' . $RDBMS_QUOTE . $index . $RDBMS_QUOTE, @values); 
	}
	# Oracle
	elsif ($RDBMS eq 'oracle') {
		my $sth = $dbh->prepare($query . ' RETURNING ' . $RDBMS_QUOTE . (uc ($index)) . $RDBMS_QUOTE . ' INTO ?');
		for (my $i = 0; $i <= $#values; $i++) {
			$sth->bind_param ($i+1, $values[$i]);
		}
		$sth->bind_param_inout($#values + 2, \$insert_id, 99);
		$sth->execute ();
	}
	
	return $insert_id;
}

##########################################################################
## SQL update. Returns the number of updated rows.
##########################################################################
sub db_update ($$;@) {
	my ($dbh, $query, @values) = @_;
	
	my $rows = $dbh->do($query, undef, @values);
	
	return $rows;
}

##########################################################################
## Return alert template-module ID given the module and template ids.
##########################################################################
sub get_alert_template_module_id ($$$) {
	my ($dbh, $id_module, $id_template) = @_;
	
	my $rc = get_db_value ($dbh, "SELECT id FROM talert_template_modules WHERE id_agent_module = ? AND id_alert_template = ?", $id_module, $id_template);
	return defined ($rc) ? $rc : -1;
}

########################################################################
## SQL insert. Returns the ID of the inserted row.
########################################################################
sub db_process_insert($$$$;@) {
	my ($dbh, $index, $table, $parameters, @values) = @_;
	
	my @columns_array = keys %$parameters;
	my @values_array = values %$parameters;
	
	if (!defined($table) || $#columns_array == -1) {
		return -1;
		exit;
	}
	
	# Generate the '?' simbols to the Query like '(?,?,?,?,?)'
	my $wildcards = '';
	for (my $i=0; $i<=$#values_array; $i++) {
		if (!defined($values_array[$i])) {
			$values_array[$i] = '';
		}
		if ($i > 0 && $i <= $#values_array) {
			$wildcards = $wildcards.',';
		}
		$wildcards = $wildcards.'?';
	}
	$wildcards = '('.$wildcards.')';
	
	# Escape column names that are reserved words
	for (my $i = 0; $i < scalar(@columns_array); $i++) {
		if ($columns_array[$i] eq 'interval') {
			$columns_array[$i] = "${RDBMS_QUOTE}interval${RDBMS_QUOTE}";
		}
	}
	my $columns_string = join(',', @columns_array);
	
	my $res = db_insert ($dbh,
		$index,
		"INSERT INTO $table ($columns_string) VALUES " . $wildcards, @values_array);
	
	
	return $res;
}

########################################################################
## SQL update.
########################################################################
sub db_process_update($$$$) {
	my ($dbh, $table, $parameters, $conditions) = @_;
	
	my @columns_array = keys %$parameters;
	my @values_array = values %$parameters;
	my @where_columns = keys %$conditions;
	my @where_values = values %$conditions;
	
	if (!defined($table) || $#columns_array == -1 || $#where_columns == -1) {
		return -1;
		exit;
	}
	
	# VALUES...
	my $fields = '';
	for (my $i = 0; $i <= $#values_array; $i++) {
		if (!defined($values_array[$i])) {
			$values_array[$i] = '';
		}
		if ($i > 0 && $i <= $#values_array) {
			$fields = $fields.',';
		}
		
		# Avoid the use of quotes on the column names in oracle, cause the quotes
		# force the engine to be case sensitive and the column names created without
		# quotes are stores in uppercase.
		# The quotes should be introduced manually for every item created with it.
		if ($RDBMS eq 'oracle') {
			$fields = $fields . " " . $columns_array[$i] . " = ?";
		}
		else {
			$fields = $fields . " " . $RDBMS_QUOTE . "$columns_array[$i]" . $RDBMS_QUOTE . " = ?";
		}
	}

	# WHERE...
	my $where = '';
	for (my $i = 0; $i <= $#where_columns; $i++) {
		if (!defined($where_values[$i])) {
			$where_values[$i] = '';
		}
		if ($i > 0 && $i <= $#where_values) {
			$where = $where.' AND ';
		}
		
		# Avoid the use of quotes on the column names in oracle, cause the quotes
		# force the engine to be case sensitive and the column names created without
		# quotes are stores in uppercase.
		# The quotes should be introduced manually for every item created with it.
		if ($RDBMS eq 'oracle') {
			$where = $where . " " . $where_columns[$i] . " = ?";
		}
		else {
			$where = $where . " " . $RDBMS_QUOTE . "$where_columns[$i]" . $RDBMS_QUOTE . " = ?";
		}
	}

	my $res = db_update ($dbh, "UPDATE $table
		SET $fields
		WHERE $where", @values_array, @where_values);
	
	return $res;
}

########################################################################
# Add the given address to taddress.
########################################################################
sub add_address ($$) {
	my ($dbh, $ip_address) = @_;
	
	return db_insert ($dbh, 'id_a', 'INSERT INTO taddress (ip) VALUES (?)', $ip_address);
}

########################################################################
# Assign the new address to the agent
########################################################################
sub add_new_address_agent ($$$) {
	my ($dbh, $addr_id, $agent_id) = @_;
	
	db_do ($dbh, 'INSERT INTO taddress_agent (id_a, id_agent)
	              VALUES (?, ?)', $addr_id, $agent_id);
}

########################################################################
# Return an aggent-address relationship given the respective IDs.
########################################################################
sub get_address_agent ($$$) {
	my ($dbh, $addr_id, $agent_id) = @_;

	return get_db_single_row ($dbh, 'SELECT * FROM taddress_agent WHERE id_a = ? AND id_agent = ?', $addr_id, $agent_id);
}

########################################################################
# Return the ID of the given address, -1 if it does not exist.
########################################################################
sub get_addr_id ($$) {
	my ($dbh, $addr) = @_;
	
	my $addr_id = get_db_value ($dbh,
		'SELECT id_a
		FROM taddress
		WHERE ip = ?', $addr);
	
	return (defined ($addr_id) ? $addr_id : -1);
}

##########################################################################
# Return the agent address ID for the given agent ID and address ID, -1 if
# it does not exist.
##########################################################################
sub get_agent_addr_id ($$$) {
	my ($dbh, $addr_id, $agent_id) = @_;
	
	my $agent_addr_id = get_db_value ($dbh,
		'SELECT id_ag
		FROM taddress_agent
		WHERE id_a = ?
			AND id_agent = ?', $addr_id, $agent_id);
	
	return (defined ($agent_addr_id) ? $agent_addr_id : -1);
}

########################################################################
## Generic SQL sentence. 
########################################################################
sub db_do ($$;@) {
	my ($dbh, $query, @values) = @_;
	
	#DBI->trace( 3, '/tmp/dbitrace.log' );
	$dbh->do($query, undef, @values);
}

########################################################################
# Return the ID of the taddress agent with the given IP.
########################################################################
sub is_agent_address ($$$) {
	my ($dbh, $id_agent, $id_addr) = @_;
	
	my $id_ag = get_db_value ($dbh, 'SELECT id_ag
		FROM taddress_agent 
		WHERE id_a = ?
			AND id_agent = ?', $id_addr, $id_agent);
	
	return (defined ($id_ag)) ? $id_ag : 0;
}

########################################################################
## Quote the given string. 
########################################################################
sub db_string ($) {
	my $string = shift;
	
	# MySQL and PostgreSQL
	#return "'" . $string . "'" if ($RDBMS eq 'mysql' || $RDBMS eq 'postgresql' || $RDBMS eq 'oracle');
	
	return "'" . $string . "'";
}

########################################################################
## Convert TEXT to string when necessary
########################################################################
sub db_text ($) {
	my $string = shift;
	
	#return $string;
	return " dbms_lob.substr(" . $string . ", 4000, 1)" if ($RDBMS eq 'oracle');
	
	return $string;
}

########################################################################
## SUB get_alert_template_name(alert_id)
## Return the alert template name, given "alert_id"
########################################################################
sub get_alert_template_name ($$) {
	my ($dbh, $alert_id) = @_;
	
	return get_db_value ($dbh, "SELECT name
		FROM talert_templates, talert_template_modules
		WHERE talert_templates.id = talert_template_modules.id_alert_template
			AND talert_template_modules.id = ?", $alert_id);
}

########################################################################
## Concat two strings
########################################################################
sub db_concat ($$) {
	my ($element1, $element2) = @_;
	
	return " " . $element1 . " || ' ' || " . $element2 . " " if ($RDBMS eq 'oracle' or $RDBMS eq 'postgresql');
	return " concat(" . $element1 . ", ' '," . $element2 . ") ";
}

########################################################################
## Get priority/severity name from the associated ID
########################################################################
sub get_priority_name ($) {
	my ($priority_id) = @_;
	
	if ($priority_id == 0) {
		return 'Maintenance';
	}
	elsif ($priority_id == 1) {
		return 'Informational';
	}
	elsif ($priority_id == 2) {
		return 'Normal';
	}
	elsif ($priority_id == 3) {
		return 'Warning';
	}
	elsif ($priority_id == 4) {
		return 'Critical';
	}
	elsif ($priority_id == 5) {
		return 'Minor';
	}
	elsif ($priority_id == 6) {
		return 'Major';
	}
	
	return '';
}

########################################################################
## Get the set string and array of values to perform un update from a hash.
########################################################################
sub db_update_get_values ($) {
	my ($set_ref) = @_;
	
	my $set = '';
	my @values;
	while (my ($key, $value) = each (%{$set_ref})) {
		
		# Not value for the given column
		next if (! defined ($value));
		
		$set .= "$key = ?,";
		push (@values, $value);
	}
	
	# Remove the last ,
	chop ($set);
	
	return ($set, \@values);
}

########################################################################
## Get the string and array of values to perform an insert from a hash.
########################################################################
sub db_insert_get_values ($) {
	my ($insert_ref) = @_;
	
	my $columns = '(';
	my @values;
	while (my ($key, $value) = each (%{$insert_ref})) {
		
		# Not value for the given column
		next if (! defined ($value));
		
		$columns .= $key . ",";
		push (@values, $value);
	}
	
	# Remove the last , and close the parentheses
	chop ($columns);
	$columns .= ')';
	
	# No columns
	if ($columns eq '()') {
		return;
	}
	
	# Add placeholders for the values
	$columns .= ' VALUES (' . ("?," x ($#values + 1));
	
	# Remove the last , and close the parentheses
	chop ($columns);
	$columns .= ')';
	
	return ($columns, \@values);
}

########################################################################
## Try to obtain the given lock.
########################################################################
sub db_get_lock($$;$) {
	my ($dbh, $lock_name, $lock_timeout) = @_;

	# Only supported in MySQL.
	return 1 unless ($RDBMS eq 'mysql');

	# Set a default lock timeout of 1 second
	$lock_timeout = 1 if (! defined ($lock_timeout));
	
	# Attempt to get the lock!
	my $sth = $dbh->prepare('SELECT GET_LOCK(?, ?)');
	$sth->execute($lock_name, $lock_timeout);
	my ($lock) = $sth->fetchrow;
	
	# Something went wrong
	return 0 if (! defined ($lock));
	
	return $lock;
}

########################################################################
## Release the given lock.
########################################################################
sub db_release_lock($$) {
	my ($dbh, $lock_name) = @_;
	
	# Only supported in MySQL.
	return unless ($RDBMS eq 'mysql');

	my $sth = $dbh->prepare('SELECT RELEASE_LOCK(?)');
	$sth->execute($lock_name);
	my ($lock) = $sth->fetchrow;
}

# End of function declaration
# End of defined Code

1;
__END__
