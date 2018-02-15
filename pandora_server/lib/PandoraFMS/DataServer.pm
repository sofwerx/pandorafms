package PandoraFMS::DataServer;
##########################################################################
# Pandora FMS Data Server.
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

use threads;
use threads::shared;
use Thread::Semaphore;

use Time::Local;
use XML::Parser::Expat;
use XML::Simple;
use POSIX qw(setsid strftime);

# For Reverse Geocoding
use LWP::Simple;

# Default lib dir for RPM and DEB packages
use lib '/usr/lib/perl5';

use PandoraFMS::Tools;
use PandoraFMS::DB;
use PandoraFMS::Core;
use PandoraFMS::ProducerConsumerServer;

# Inherits from PandoraFMS::ProducerConsumerServer
our @ISA = qw(PandoraFMS::ProducerConsumerServer);

# Global variables
my @TaskQueue :shared;
my %PendingTasks :shared;
my %Agents :shared;
my $Sem :shared;
my $TaskSem :shared;
my $AgentSem :shared;
my $ModuleSem :shared;

########################################################################################
# Data Server class constructor.
########################################################################################
sub new ($$;$) {
	my ($class, $config, $dbh) = @_;

	return undef unless $config->{'dataserver'} == 1;

	# Initialize semaphores and queues
	@TaskQueue = ();
	%PendingTasks = ();
	%Agents = ();
	$Sem = Thread::Semaphore->new;
	$TaskSem = Thread::Semaphore->new (0);
	$AgentSem = Thread::Semaphore->new (1);
	$ModuleSem = Thread::Semaphore->new (1);
	
	# Call the constructor of the parent class
	my $self = $class->SUPER::new($config, DATASERVER, \&PandoraFMS::DataServer::data_producer, \&PandoraFMS::DataServer::data_consumer, $dbh);

	# Load external .enc files for XML::Parser.
	if ($config->{'enc_dir'} ne '') {
		if (opendir(my $dh, $config->{'enc_dir'})) {
			while (my $enc_file = readdir($dh)) {

				# Ignore unknown files.
				next unless ($enc_file =~ m/.enc$/);

				# Load the .enc file.
				eval {
					local $SIG{__DIE__} = {};
					XML::Parser::Expat::load_encoding($config->{'enc_dir'} . '/' . $enc_file);
				};
				if ($@) {
					print_message ($config, " [WARNING] Error loading encoding file: $enc_file", 1);
				}
			}
			closedir($dh);
		} else {
			print_message($config, " [WARNING] Error opening directory " . $config->{'enc_dir'} . ": $!", 1);
		}
	}

	bless $self, $class;
	return $self;
}

###############################################################################
# Run.
###############################################################################
sub run ($) {
	my $self = shift;
	my $pa_config = $self->getConfig ();

	print_message ($pa_config, " [*] Starting Pandora FMS Data Server.", 1);
	$self->setNumThreads ($pa_config->{'dataserver_threads'});
	$self->SUPER::run (\@TaskQueue, \%PendingTasks, $Sem, $TaskSem);
}

###############################################################################
# Data producer.
###############################################################################
sub data_producer ($) {
	my $self = shift;
	my $pa_config = $self->getConfig ();

	my @tasks;
	my @files;
	my @sorted;

	# Open the incoming directory
	opendir (DIR, $pa_config->{'incomingdir'})
		|| die "[FATAL] Cannot open Incoming data directory at " . $pa_config->{'incomingdir'} . ": $!";

	# Do not read more than max_queue_files files
 	my $file_count = 0;
 	while (my $file = readdir (DIR)) {

		# Data files must have the extension .data
		next if ($file !~ /^.*[\._]\d+\.data$/);

		# Do not queue more than max_queue_files files
		if ($file_count >= $pa_config->{"max_queue_files"}) {
			last;
		}

		push (@files, $file);
		$file_count++;
	}
	closedir(DIR);

	# Sort the queue
	{
		# Temporarily disable warnings (some files may have been deleted)
		no warnings;
		if ($pa_config->{'dataserver_lifo'} == 0) {
			@sorted = sort { -M $pa_config->{'incomingdir'} . "/$b" <=> -M $pa_config->{'incomingdir'} . "/$a" || $a cmp $b } (@files);
		} else {
			@sorted = sort { -M $pa_config->{'incomingdir'} . "/$a" <=> -M $pa_config->{'incomingdir'} . "/$b" || $b cmp $a } (@files);
		}
	}

	# Do not process more than one XML from the same agent at the same time
	foreach my $file (@sorted) {

		next if ($file !~ /^(.*)[\._]\d+\.data$/);
		my $agent_name = $1;

		$AgentSem->down ();
		if (defined ($Agents{$agent_name})) {
			$AgentSem->up ();
			next;
		}
		$Agents{$agent_name} = 1;
		$AgentSem->up ();

		push (@tasks, $file);
	}

	return @tasks;
}

###############################################################################
# Data consumer.
###############################################################################
sub data_consumer ($$) {
	my ($self, $task) = @_;
	my ($pa_config, $dbh) = ($self->getConfig (), $self->getDBH ());

	return unless ($task =~ /^(.*)[\._]\d+\.data$/);
	my $agent_name = $1;		
	my $file_name = $pa_config->{'incomingdir'};
	my $xml_err;
	
	# Fix path
	$file_name .= "/" unless (substr ($file_name, -1, 1) eq '/');	
	$file_name .= $task;

	# Double check that the file exists
	if (! -f $file_name) {
		$AgentSem->down ();
		delete ($Agents{$agent_name});
		$AgentSem->up ();
		return;
	}

	# Try to parse the XML 2 times, with a delay between tries of 2 seconds
	my $xml_data;

	for (0..1) {
		eval {
			threads->yield;
			$xml_data = XMLin ($file_name, forcearray => 'module');
		};
	
		# Invalid XML
		if ($@ || ref($xml_data) ne 'HASH') {
			if ($@) {
				$xml_err = $@;
			} else {
				$xml_err = "Invalid XML format.";
			}
			sleep (2);
			next;
		}

		# Ignore the timestamp in the XML and use the file timestamp instead
		$xml_data->{'timestamp'} = strftime ("%Y-%m-%d %H:%M:%S", localtime((stat($file_name))[9])) if ($pa_config->{'use_xml_timestamp'} eq '1' || ! defined ($xml_data->{'timestamp'}));

		# Double check that the file exists
		if (! -f $file_name) {
			$AgentSem->down ();
			delete ($Agents{$agent_name});
			$AgentSem->up ();
			return;
		}

		unlink ($file_name);
		if (defined($xml_data->{'server_name'})) {
			process_xml_server ($self->getConfig (), $file_name, $xml_data, $self->getDBH ());
		} else {
			process_xml_data ($self->getConfig (), $file_name, $xml_data, $self->getServerID (), $self->getDBH ());
		}
		$AgentSem->down ();
		delete ($Agents{$agent_name});
		$AgentSem->up ();
		return;	
	}

	rename($file_name, $file_name . '_BADXML');
	pandora_event ($pa_config, "Unable to process XML data file '$file_name': $xml_err", 0, 0, 0, 0, 0, 'error', 0, $dbh);
	$AgentSem->down ();
	delete ($Agents{$agent_name});
	$AgentSem->up ();
}

###############################################################################
# Process XML data coming from an agent.
###############################################################################
sub process_xml_data ($$$$$) {
	my ($pa_config, $file_name, $data, $server_id, $dbh) = @_;

	my ($agent_name, $agent_version, $timestamp, $interval, $os_version, $timezone_offset, $custom_id, $url_address) =
		($data->{'agent_name'}, $data->{'version'}, $data->{'timestamp'},
		$data->{'interval'}, $data->{'os_version'}, $data->{'timezone_offset'},
		$data->{'custom_id'}, $data->{'url_address'});

	# Timezone offset must be an integer beween -12 and +12
	if (!defined($timezone_offset) || $timezone_offset !~ /[-+]?[0-9,11,12]/) {
		$timezone_offset = 0;
	}
	
	# Parent Agent Name
	my $parent_id = 0; # Default value for unknown parent
	my $parent_agent_name = $data->{'parent_agent_name'};
	if (defined ($parent_agent_name) && $parent_agent_name ne '') {
		$parent_id = get_agent_id ($dbh, $parent_agent_name);
		if ($parent_id < 1)	{ # Unknown parent
			$parent_id = 0;
		}
	}

	# Unknown agent!
	if (! defined ($agent_name) || $agent_name eq '') {
		logger($pa_config, "$file_name has data from an unnamed agent", 3);
		return;
	}

	# Get current datetime from system if value AUTO is coming in the XML
	if ( $data->{'timestamp'} =~ /AUTO/ ){
		$timestamp = strftime ("%Y/%m/%d %H:%M:%S", localtime());
	}
	# Apply an offset to the timestamp
	elsif ($timezone_offset != 0) {
			
		# Modify the timestamp with the timezone_offset
		logger($pa_config, "Applied a timezone offset of $timestamp to agent " . $data->{'agent_name'}, 10);
		
		# Calculate the start date to add the offset
		my $utimestamp = 0;
		eval {
			if ($timestamp =~ /(\d+)[\/|\-](\d+)[\/|\-](\d+) +(\d+):(\d+):(\d+)/) {
				$utimestamp = timelocal($6, $5, $4, $3, $2 -1 , $1 - 1900);
			}
		};
		
		# Apply the offset if there were no errors
		if (! $@ && $utimestamp != 0) {
			$timestamp = strftime ("%Y-%m-%d %H:%M:%S", localtime($utimestamp + ($timezone_offset * 3600)));
		}
	}
	
	# Check some variables
	$interval = 300 if (! defined ($interval) || $interval eq '');
	$os_version = 'N/A' if (! defined ($os_version) || $os_version eq '');
	
	# Get agent address from the XML if available
	my $address = '' ;
	my @address_list;
	if (defined ($data->{'address'}) && $data->{'address'} ne '') {
		@address_list = split (',', $data->{'address'});

		# Trim addresses
		for (my $i = 0; $i <= $#address_list; $i++) {
			$address_list[$i] =~ s/^\s+|\s+$//g ;
		}
		
		# Save the first address as the main address
		$address = $address_list[0];
		$address =~ s/^\s+|\s+$//g ;
		shift (@address_list);
}
	
	# Get agent id
	my $agent_id = get_agent_id ($dbh, $agent_name);
	if ($agent_id < 1) {
		if ($pa_config->{'autocreate'} == 0) {
			logger($pa_config, "ERROR: There is no agent defined with name $agent_name", 3);
			return;
		}
		
		# Get OS, group and description
		my $os = pandora_get_os ($dbh, $data->{'os_name'});
		my $group_id = $pa_config->{'autocreate_group'};
		if (! defined (get_group_name ($dbh, $group_id))) {
			if (defined ($data->{'group'}) && $data->{'group'} ne '') {
				$group_id = get_group_id ($dbh, $data->{'group'});
				if (! defined (get_group_name ($dbh, $group_id))) {
					pandora_event ($pa_config, "Unable to create agent '$agent_name': group '" . $data->{'group'} . "' does not exist.", 0, 0, 0, 0, 0, 'error', 0, $dbh);
					logger($pa_config, "Group " . $data->{'group'} . " does not exist.", 3);
					return;
				}
			} else {
					pandora_event ($pa_config, "Unable to create agent '$agent_name': autocreate_group $group_id does not exist. Edit the pandora_server.conf file and change it.", 0, 0, 0, 0, 0, 'error', 0, $dbh);
					logger($pa_config, "Group id $group_id does not exist (check autocreate_group config token).", 3);
					return;
			}
		}

		# Check the group password.
		my $rc = enterprise_hook('check_group_password', [$dbh, $group_id, $data->{'group_password'}]);
		if (defined($rc) && $rc != 1) {
			logger($pa_config, "Agent $agent_name did not send a valid password for group id $group_id.", 10);
			return;
		}

		my $description = '';
		$description = $data->{'description'} if (defined ($data->{'description'}));
		
		$agent_id = pandora_create_agent($pa_config, $pa_config->{'servername'}, $agent_name, $address, $group_id, $parent_id, $os, 
						$description, $interval, $dbh, $timezone_offset, undef, undef, undef, undef, $custom_id, $url_address);
												 
		if (! defined ($agent_id)) {
			return;
		}
		
		# Add the main address to the address list
		if ($address ne '') {
			pandora_add_agent_address($pa_config, $agent_id, $agent_name, $address, $dbh);
		}

		# Process custom fields
		if(defined($data->{'custom_fields'})) {
			foreach my $custom_fields (@{$data->{'custom_fields'}}) {
				foreach my $custom_field (@{$custom_fields->{'field'}}) {
					my $cf_name = get_tag_value ($custom_field, 'name', '');
					logger($pa_config, "Processing custom field '" . $cf_name . "'", 10);
					
					# Check if the custom field exists
					my $custom_field_info = get_db_single_row ($dbh, 'SELECT * FROM tagent_custom_fields WHERE name = ?', safe_input($cf_name));
					
					# If it exists add the value to the agent
					if (defined ($custom_field_info)) {
						my $cf_value = get_tag_value ($custom_field, 'value', '');

						my $field_agent;
						
						$field_agent->{'id_agent'} = $agent_id;
						$field_agent->{'id_field'} = $custom_field_info->{'id_field'};
						$field_agent->{'description'} = $cf_value;
						
						db_process_insert($dbh, 'id_field', 'tagent_custom_data', $field_agent);
					}
					else {
						logger($pa_config, "The custom field '" . $cf_name . "' does not exist. Discarded from XML", 5);
					}
				}
			}
		}
	}

	# Get the data of the agent, if fail return
	my $agent = get_db_single_row ($dbh, 'SELECT * FROM tagente WHERE id_agente = ?', $agent_id);
	if (!defined ($agent)) {
		logger($pa_config, "Error retrieving information for agent ID $agent_id",10);
		return;
	}
	
	# Check if agent is disabled and return if it's disabled. Disabled agents doesnt process data
	# in order to avoid not only events, also possible invalid data coming from agents.
	return if ($agent->{'disabled'} == 1);
	
	# Do not overwrite agent parameters if the agent is in normal mode
	if ($agent->{'modo'} == 0) {;
		$interval = $agent->{'intervalo'};
		$os_version = $agent->{'os_version'};
		$agent_version = $agent->{'agent_version'};
		$timezone_offset = $agent->{'timezone_offset'};
		$parent_id = $agent->{'id_parent'};
	}
	# Learning mode
	else { 
	
		# Update the main address
		if ($address ne '' && $address ne $agent->{'direccion'}) {
			pandora_update_agent_address ($pa_config, $agent_id, $agent_name, $address, $dbh);
			pandora_add_agent_address($pa_config, $agent_id, $agent_name, $address, $dbh);
		}
		
		# Update additional addresses
		foreach my $address (@address_list) {
			pandora_add_agent_address($pa_config, $agent_id, $agent_name, $address, $dbh);
		}
		
		# Update parent if is allowed and is valid
		if ($pa_config->{'update_parent'} == 1 && $parent_id != 0) {
			logger($pa_config, "Updating agent $agent_name parent_id: $parent_id", 5);
		}
		else {
			$parent_id = $agent->{'id_parent'};
		}

                # Process custom fields for update
                if(defined($data->{'custom_fields'})) {
                        foreach my $custom_fields (@{$data->{'custom_fields'}}) {
                                foreach my $custom_field (@{$custom_fields->{'field'}}) {
                                        my $cf_name = get_tag_value ($custom_field, 'name', '');
                                        logger($pa_config, "Processing custom field '" . $cf_name . "'", 10);

                                        # Check if the custom field exists
                                        my $custom_field_info = get_db_single_row ($dbh, 'SELECT * FROM tagent_custom_fields WHERE name = ?', safe_input($cf_name));

                                        # If it exists add the value to the agent
                                        if (defined ($custom_field_info)) {

						my $custom_field_data = get_db_single_row($dbh, 'SELECT * FROM tagent_custom_data WHERE id_field = ? AND id_agent = ?',
											$custom_field_info->{"id_field"}, $agent->{"id_agente"});

                                                my $cf_value = get_tag_value ($custom_field, 'value', '');

						#If not defined we must create if defined just updated
						if(!defined($custom_field_data)) {
						
	                                                my $field_agent;

	                                                $field_agent->{'id_agent'} = $agent_id;
	                                                $field_agent->{'id_field'} = $custom_field_info->{'id_field'};
	                                                $field_agent->{'description'} = $cf_value;

        	                                        db_process_insert($dbh, 'id_field', 'tagent_custom_data', $field_agent);
						} else {
							
							db_update ($dbh, "UPDATE tagent_custom_data SET description = ? WHERE id_field = ? AND id_agent = ?",
									$cf_value ,$custom_field_info->{"id_field"}, $agent->{'id_agente'});
						}
                                        }
                                        else {
                                                logger($pa_config, "The custom field '" . $cf_name . "' does not exist. Discarded from XML", 5);
                                        }
                                }
                        }
                }

	}
	
	# Update agent information
	pandora_update_agent($pa_config, $timestamp, $agent_id, $os_version, $agent_version, $interval, $dbh, $timezone_offset, $parent_id);

	# Update GIS data
	if ($pa_config->{'activate_gis'} != 0 && $agent->{'update_gis_data'} == 1) {
		pandora_update_gis_data ($pa_config, $dbh, $agent_id, $agent_name, $data->{'longitude'}, $data->{'latitude'}, $data->{'altitude'}, $data->{'position_description'}, $timestamp);
	}
	
	# Update keep alive modules
	pandora_module_keep_alive ($pa_config, $agent_id, $agent_name, $server_id, $dbh);
	
	# Process modules
	foreach my $module_data (@{$data->{'module'}}) {

		my $module_name = get_tag_value ($module_data, 'name', '');

		# Clean module_name because sometimes due to errors or problems 
		# creating XMLs it could contain carriage returns and later they
		# are a pain when you update module configuration because the name won't
		# save the carriage return.
		$module_name =~ s/\r//g;
		$module_name =~ s/\n//g;
		
		# Unnamed module
		next if ($module_name eq '');

		my $module_type = get_tag_value ($module_data, 'type', 'generic_data');

		# Single data
		if (! defined ($module_data->{'datalist'})) {
			my $data_timestamp = get_tag_value ($module_data, 'timestamp', $timestamp);
			process_module_data ($pa_config, $module_data, $server_id, $agent_name, $module_name, $module_type, $interval, $data_timestamp, $dbh);
			next;
		}

		# Data list
		foreach my $list (@{$module_data->{'datalist'}}) {
			
			# Empty list
			next unless defined ($list->{'data'});
						
			foreach my $data (@{$list->{'data'}}) {
				
				# No value
				next unless defined ($data->{'value'});
							
				$module_data->{'data'} = $data->{'value'};
				my $data_timestamp = get_tag_value ($data, 'timestamp', $timestamp);
				process_module_data ($pa_config, $module_data, $server_id, $agent_name, $module_name,
									 $module_type, $interval, $data_timestamp, $dbh);
			}
		}
	}

	# Process inventory modules
	enterprise_hook('process_inventory_data', [$pa_config, $data, $server_id, $agent_name,
							 $interval, $timestamp, $dbh]);

	# Process log modules
	enterprise_hook('process_log_data', [$pa_config, $data, $server_id, $agent_name,
							 $interval, $timestamp, $dbh]);
}

##########################################################################
# Process module data, creating module if necessary.
##########################################################################
sub process_module_data ($$$$$$$$$) {
	my ($pa_config, $data, $server_id, $agent_name,
		$module_name, $module_type, $interval, $timestamp,
		$dbh) = @_;

	# Get agent data
	my $agent = get_db_single_row ($dbh, 'SELECT * FROM tagente WHERE nombre = ?', safe_input($agent_name));
	if (! defined ($agent)) {
		logger($pa_config, "Invalid agent '$agent_name' for module '$module_name'.", 3);
		return;
	}

	# Get module parameters, matching column names in tagente_modulo
	my $module_conf;
	
	# Supported tags
	my $tags = {'name' => 0, 'data' => 0, 'type' => 0, 'description' => 0, 'max' => 0,
	            'min' => 0, 'descripcion' => 0, 'post_process' => 0, 'module_interval' => 0, 'min_critical' => 0,
	            'max_critical' => 0, 'min_warning' => 0, 'max_warning' => 0, 'disabled' => 0, 'min_ff_event' => 0,
	            'datalist' => 0, 'status' => 0, 'unit' => 0, 'timestamp' => 0, 'module_group' => 0, 'custom_id' => '', 
	            'str_warning' => '', 'str_critical' => '', 'critical_instructions' => '', 'warning_instructions' => '',
	            'unknown_instructions' => '', 'tags' => '', 'critical_inverse' => 0, 'warning_inverse' => 0, 'quiet' => 0,
				'module_ff_interval' => 0, 'alert_template' => '', 'crontab' =>	'', 'min_ff_event_normal' => 0,
				'min_ff_event_warning' => 0, 'min_ff_event_critical' => 0, 'ff_timeout' => 0, 'each_ff' => 0};
	
	# Other tags will be saved here
	$module_conf->{'extended_info'} = '';
	
	# Read tags
	while (my ($tag, $value) = each (%{$data})) {
		if (defined ($tags->{$tag})) {
			$module_conf->{$tag} = get_tag_value ($data, $tag, '');
		} else {
			$module_conf->{'extended_info'} .= "$tag: " . get_tag_value ($data, $tag, '') . '<br/>';
		}
	}
	
	# Reload alert_template to get all alerts like an array
	$module_conf->{'alert_template'} = get_tag_value ($data, 'alert_template', '', 1);
	
	# Description XML tag and column name don't match
	$module_conf->{'descripcion'} = $module_conf->{'description'};
	$module_conf->{'descripcion'} = '' unless defined ($module_conf->{'descripcion'});
	delete $module_conf->{'description'};
	
	# Name XML tag and column name don't match
	$module_conf->{'nombre'} = safe_input($module_name);
	delete $module_conf->{'name'};
	
	# Calculate the module interval in seconds
	$module_conf->{'module_interval'} = 1 unless defined ($module_conf->{'module_interval'});
	$module_conf->{'module_interval'} *= $interval if (defined ($module_conf->{'module_interval'}));
	
	# Allow , as a decimal separator
	$module_conf->{'post_process'} =~ s/,/./ if (defined ($module_conf->{'post_process'}));

	# avoid NULL columns
	$module_conf->{'critical_instructions'} = '' unless defined ($module_conf->{'critical_instructions'});
	$module_conf->{'warning_instructions'} = '' unless defined ($module_conf->{'warning_instructions'});
	$module_conf->{'unknown_instructions'} = '' unless defined ($module_conf->{'unknown_instructions'});
	$module_conf->{'disabled_types_event'} = '' unless defined ($module_conf->{'disabled_types_event'});
	$module_conf->{'module_macros'} = '' unless defined ($module_conf->{'module_macros'});
	
	# Get module data or create it if it does not exist
	$ModuleSem->down ();
	my $module = get_db_single_row ($dbh, 'SELECT * FROM tagente_modulo WHERE id_agente = ? AND ' . db_text ('nombre') . ' = ?', $agent->{'id_agente'}, safe_input($module_name));
	if (! defined ($module)) {
		
		# Do not auto create modules
		if ($pa_config->{'autocreate'} ne '1') {
			logger($pa_config, "Module '$module_name' not found for agent '$agent_name' and module auto-creation disabled.", 10);
			$ModuleSem->up ();
			return;
		}
		
		# Is the agent learning?
		if ($agent->{'modo'} ne '1') {
			logger($pa_config, "Learning mode disabled. Skipping module '$module_name' agent '$agent_name'.", 10);
			$ModuleSem->up ();
			return;
		}
		
		# Get the module type
		$module_conf->{'id_tipo_modulo'} = get_module_id ($dbh, $module_type);
		if ($module_conf->{'id_tipo_modulo'} <= 0) {
			logger($pa_config, "Invalid module type '$module_type' for module '$module_name' agent '$agent_name'.", 3);
			$ModuleSem->up ();
			return;
		}
		
		# The group name has to be translated to a group ID
		if (defined $module_conf->{'module_group'}) {
			my $id_group_module = get_module_group_id ($dbh, $module_conf->{'module_group'});
			if ( $id_group_module >= 0) {
				$module_conf->{'id_module_group'} = $id_group_module;
			}
			delete $module_conf->{'module_group'};
		}
		
		$module_conf->{'id_modulo'} = 1;
		$module_conf->{'id_agente'} = $agent->{'id_agente'};
		
		my $module_tags = undef;
		if(defined ($module_conf->{'tags'})) {
			$module_tags = $module_conf->{'tags'};
			delete $module_conf->{'tags'};
		}

		my $initial_alert_template = undef;
		if(defined ($module_conf->{'alert_template'})) {
			$initial_alert_template = $module_conf->{'alert_template'};
			delete $module_conf->{'alert_template'};
		}
		
		if(cron_check_syntax ($module_conf->{'crontab'})) {
			$module_conf->{'cron_interval'} = $module_conf->{'crontab'};
		}
		delete $module_conf->{'crontab'};

		# Create the module
		my $module_id = pandora_create_module_from_hash ($pa_config, $module_conf, $dbh);
		
		$module = get_db_single_row ($dbh, 'SELECT * FROM tagente_modulo WHERE id_agente = ? AND ' . db_text('nombre') . ' = ?', $agent->{'id_agente'}, safe_input($module_name));
		if (! defined ($module)) {
			logger($pa_config, "Could not create module '$module_name' for agent '$agent_name'.", 3);
			$ModuleSem->up ();
			return;
		}
		
		# Assign the tags on module if the specified tags exist
		if(defined ($module_tags)) {
			logger($pa_config, "Processing module tags '$module_tags' in module '$module_name' for agent '$agent_name'.", 10);
			my @module_tags = split(/,/, $module_tags);
			for(my $i=0;$i<=$#module_tags;$i++) {
				my $tag_info = get_db_single_row ($dbh, 'SELECT * FROM ttag WHERE name = ?', safe_input($module_tags[$i]));
				if (defined ($tag_info)) {
					my $tag_module;
					
					$tag_module->{'id_tag'} = $tag_info->{'id_tag'};
					$tag_module->{'id_agente_modulo'} = $module->{'id_agente_modulo'};
					
					db_process_insert($dbh, 'id_tag', 'ttag_module', $tag_module);
				}
			}
		}

		#  Assign alert-templates if exist
		if( $initial_alert_template ) {
			foreach my $individual_template (@{$initial_alert_template}){
				my $id_alert_template = get_db_value ($dbh,
						'SELECT id FROM talert_templates WHERE talert_templates.name = ?',
						safe_input($individual_template) );

				if( defined($id_alert_template) ) {
					pandora_create_template_module ($pa_config, $dbh, $module->{'id_agente_modulo'}, $id_alert_template);
				}
			}
		}
	}
	else {
		# Control NULL columns
		$module->{'descripcion'} = '' unless defined ($module->{'descripcion'});
		$module->{'extended_info'} = '' unless defined ($module->{'extended_info'});
		
		# Set default values
		$module_conf->{'descripcion'} = $module->{'descripcion'} unless defined ($module_conf->{'descripcion'});
		$module_conf->{'extended_info'} = $module->{'extended_info'} unless defined ($module_conf->{'extended_info'});
		$module_conf->{'module_interval'} = $module->{'module_interval'} unless defined ($module_conf->{'module_interval'});
	}
	
	# Check if the module is policy linked to update it or not
	my $policy_linked = 0;
	if ($module->{'id_policy_module'} != 0) {
		if ($module->{'policy_adopted'} == 0 || ($module->{'policy_adopted'} == 1 && $module->{'policy_linked'} == 1)) {
			$policy_linked = 1;
		}
	}
	
	# Update module configuration if in learning mode and not a policy module
	if ($agent->{'modo'} eq '1' && $policy_linked == 0) {
		update_module_configuration ($pa_config, $dbh, $module, $module_conf);
	}
	
	$ModuleSem->up ();
	
	# Module disabled!
	if ($module->{'disabled'} eq '1') {
		logger($pa_config, "Skipping disabled module '$module_name' agent '$agent_name'.", 10);
		return;
	}
	
	# Parse the timestamp and process the module
	if ($timestamp !~ /(\d+)\/(\d+)\/(\d+) +(\d+):(\d+):(\d+)/ &&
		$timestamp !~ /(\d+)\-(\d+)\-(\d+) +(\d+):(\d+):(\d+)/) {
		logger($pa_config, "Invalid timestamp '$timestamp' from module '$module_name' agent '$agent_name'.", 3);
		return;
	}
	my $utimestamp;
	eval {
 		$utimestamp = timelocal($6, $5, $4, $3, $2 - 1, $1 - 1900);
	};
	if ($@) {
		logger($pa_config, "Invalid timestamp '$timestamp' from module '$module_name' agent '$agent_name'.", 3);
		return;
	}
	#my $value = get_tag_value ($data, 'data', '');		
	my $data_object = get_module_data($data, $module_type);
	my $extra_macros = get_macros_for_data($data, $module_type);
	
	# Get module status from XML data file if available
	$module->{'status'} = get_tag_value ($data, 'status', undef);
	
	pandora_process_module ($pa_config, $data_object, $agent, $module, $module_type, $timestamp, $utimestamp, $server_id, $dbh, $extra_macros);
}

##########################################################################
# Retrieve module data from the XML tree.
##########################################################################
sub get_module_data($$){
	my ($data, $module_type) = @_;	

	my %data_object;

	# Log4x modules hava extended information
	if ($module_type eq 'log4x') {
		foreach my $attr ('severity','message', 'stacktrace'){
			$data_object{$attr} = get_tag_value ($data, $attr, '');
		}
	} else {
		$data_object{'data'} = get_tag_value ($data, 'data', '');
	}

	return \%data_object;
}

##########################################################################
# Retrieve module data from the XML tree.
##########################################################################
sub get_macros_for_data($$){
	my ($data, $module_type) = @_;

	my %macros;

	if ($module_type eq 'log4x') {
		foreach my $attr ('severity','message', 'stacktrace') {
			$macros{'_' . $attr . '_'} = get_tag_value ($data, $attr, '');
		}
	}

	return \%macros;
}

##########################################################################
# Update module configuration in tagente_modulo if necessary.
##########################################################################
sub update_module_configuration ($$$$) {
	my ($pa_config, $dbh, $module, $module_conf) = @_;

	# Update if at least one of the configuration tokens has changed
	foreach my $conf_token ('descripcion', 'extended_info', 'module_interval') {
		if ($module->{$conf_token} ne $module_conf->{$conf_token}) {
			logger ($pa_config, "Updating configuration for module '" . safe_output($module->{'nombre'})	. "'.", 10);

			db_do ($dbh, 'UPDATE tagente_modulo SET descripcion = ?, extended_info = ?, module_interval = ?
				WHERE id_agente_modulo = ?', $module_conf->{'descripcion'} eq '' ? $module->{'descripcion'} : $module_conf->{'descripcion'},
				$module_conf->{'extended_info'}, $module_conf->{'module_interval'}, $module->{'id_agente_modulo'});
			last;
		}
	}
	
	# Update module hash
	$module->{'extended_info'} = $module_conf->{'extended_info'} if (defined($module_conf->{'extended_info'})) ;
	$module->{'descripcion'} = ($module_conf->{'descripcion'} eq '') ? $module->{'descripcion'} : $module_conf->{'descripcion'};
	$module->{'module_interval'} = ($module_conf->{'module_interval'} eq '') ? $module->{'module_interval'} : $module_conf->{'module_interval'};
}

###############################################################################
# Process XML data coming from a server.
###############################################################################
sub process_xml_server ($$$$) {
	my ($pa_config, $file_name, $data, $dbh) = @_;

	my ($server_name, $server_type, $version, $threads, $modules) = ($data->{'server_name'}, $data->{'server_type'}, $data->{'version'}, $data->{'threads'}, $data->{'modules'});

	# Unknown server!
	if (! defined ($server_name) || $server_name eq '') {
		logger($pa_config, "$file_name has data from an unnamed server", 3);
		return;
	}

	logger($pa_config, "Processing XML from server: $server_name", 10);

	# Set some default values
	$server_type = SATELLITESERVER unless defined($server_type);
	$modules = 0 unless defined($modules);
	$threads = 0 unless defined($threads);
	$version = '' unless defined($version);
	
	# Update server information
	pandora_update_server ($pa_config, $dbh, $data->{'server_name'}, 0, 1, $server_type, $threads, $modules, $version);
}

1;
__END__
