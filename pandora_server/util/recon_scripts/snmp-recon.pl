#!/usr/bin/perl
# (c) Ártica ST 2014 <info@artica.es>
# SNMP Recon script for network topology discovery.

use strict;
use warnings;

# Default lib dir for RPM and DEB packages
use lib '/usr/lib/perl5';

use POSIX qw/strftime/;
use Socket qw/inet_aton/;
use NetAddr::IP;

use PandoraFMS::Tools;
use PandoraFMS::DB;
use PandoraFMS::Core;
use PandoraFMS::Config;
use PandoraFMS::NmapParser;

#######################################################################
# Do not change code below this line.
#######################################################################

# IP aliases.
my %ALIASES;

# If set to '-a' all network interfaces will be added (the default is to only add interfaces that are up).
my $ALLIFACES = '';

# Keep our own ARP cache to connect hosts to switches/routers.
my %ARP_CACHE;

# IP address of a host given the MAC of one of its interfaces.
my %IF_CACHE;

# The default gateway for this host.
my $DEFAULT_GW = undef;

# Default configuration values.
my %CONF;
if ($^O eq "freebsd") {
	%CONF = (
		'nmap' => '/usr/local/bin/nmap',
		'pandora_path' => '/usr/local/etc/pandora/pandora_server.conf',
		'icmp_checks' => 1,
		'icmp_packets' => 1,
		'networktimeout' => 2,
		'snmp_checks' => 2,
		'snmp_timeout' => 2,
		'recon_timing_template' => 3,
		'PID' => '',
		'quiet' => 1,
	);
} else {
	%CONF = (
		'nmap' => '/usr/bin/nmap',
		'pandora_path' => '/etc/pandora/pandora_server.conf',
		'icmp_checks' => 1,
		'icmp_packets' => 1,
		'networktimeout' => 2,
		'snmp_timeout' => 2,
		'recon_timing_template' => 3,
		'PID' => '',
		'quiet' => 1,
	);
}

# Connections between devices.
my %CONNECTIONS;

# Working SNMP community for each device.
my %COMMUNITIES;

# Create an incident when the scan finishes.
my $CREATE_INCIDENT;

# Database connection handler.
my $DBH;

# Pandora FMS group where agents will be placed.
my $GROUP_ID;

# Found hosts.
my @HOSTS;

# Found IP routes.
my @ROUTE_CACHE;

# Switch to switch connections. Used to properly connect hosts
# that are connected to a switch wich is in turn connected to another switch,
# since the hosts will show up in the latter's switch AFT too.
my %SWITCH_TO_SWITCH;

# MAC addresses.
my %MAC;

# Parent-child relationships (in Pandora).
my %PARENTS;

# SNMP query cache.
my %SNMP_CACHE;

# Comma separated list of sub-nets to scan.
my @SUBNETS;

# Comma separated list of SNMP communities to try.
my @SNMP_COMMUNITIES;

# Current recon task.
my $TASK_ID;

# Visited devices (initially empty).
my %VISITED_DEVICES;

# Per-device VLAN cache.
my %VLAN_CACHE;

# Some useful OID.
my $DOT1DBASEBRIDGEADDRESS = ".1.3.6.1.2.1.17.1.1.0";
my $DOT1DBASEPORTIFINDEX = ".1.3.6.1.2.1.17.1.4.1.2";
my $DOT1DTPFDBADDRESS = ".1.3.6.1.2.1.17.4.3.1.1";
my $DOT1DTPFDBPORT = ".1.3.6.1.2.1.17.4.3.1.2";
my $IFDESC = ".1.3.6.1.2.1.2.2.1.2";
my $IFINDEX = ".1.3.6.1.2.1.2.2.1.1";
my $IFINOCTECTS = ".1.3.6.1.2.1.2.2.1.10";
my $IFOPERSTATUS = ".1.3.6.1.2.1.2.2.1.8";
my $IFOUTOCTECTS = ".1.3.6.1.2.1.2.2.1.16";
my $IPENTADDR = ".1.3.6.1.2.1.4.20.1.1";
my $IFNAME = ".1.3.6.1.2.1.31.1.1.1.1";
my $IPNETTOMEDIAPHYSADDRESS = ".1.3.6.1.2.1.4.22.1.2";
my $IFPHYSADDRESS = ".1.3.6.1.2.1.2.2.1.6";
my $IPADENTIFINDEX = ".1.3.6.1.2.1.4.20.1.2";
my $IPROUTEIFINDEX = ".1.3.6.1.2.1.4.21.1.2";
my $IPROUTENEXTHOP = ".1.3.6.1.2.1.4.21.1.7";
my $IPROUTETYPE = ".1.3.6.1.2.1.4.21.1.8";
my $PRTMARKERINDEX = ".1.3.6.1.2.1.43.10.2.1.1";
my $SYSDESCR = ".1.3.6.1.2.1.1.1";
my $SYSSERVICES = ".1.3.6.1.2.1.1.7";
my $SYSUPTIME = ".1.3.6.1.2.1.1.3";
my $VTPVLANIFINDEX = ".1.3.6.1.4.1.9.9.46.1.3.1.1.18.1";

#######################################################################
# Print log messages.
#######################################################################
sub message($) {
	my $message = shift;
	
	logger(\%CONF, "[SNMP L2 Recon] $message", 10);
}

########################################################################################
# Return the numeric representation of the given IP address.
########################################################################################
sub ip_to_long($) {
	my $ip_address = shift;

	return unpack('N', inet_aton($ip_address));
}

########################################################################################
# Convert a MAC address to decimal dotted notation.
########################################################################################
sub mac_to_dec($) {
	my $mac = shift;

	my $dec_mac = '';
	my @elements = split(/:/, $mac);
	foreach my $element (@elements) {
        $dec_mac .= unpack('s', pack 's', hex($element)) .  '.'
	}
	chop($dec_mac);

	return $dec_mac;
}

########################################################################################
# Make sure all MAC addresses are in the same format (00 11 22 33 44 55 66).
########################################################################################
sub parse_mac($) {
    my ($mac) = @_;

    # Remove leading and trailing whitespaces.
    $mac =~ s/(^\s+)|(\s+$)//g;

    # Replace whitespaces and dots with colons.
    $mac =~ s/\s+|\./:/g;

    # Convert hex digits to uppercase.
    $mac =~ s/([a-f])/\U$1/g;

    # Add a leading 0 to single digits.
    $mac =~ s/^([0-9A-F]):/0$1:/g;
    $mac =~ s/:([0-9A-F]):/:0$1:/g;
    $mac =~ s/:([0-9A-F])$/:0$1/g;

    return $mac;
}

########################################################################################
# Returns 1 if the two given MAC addresses are the same.
########################################################################################
sub mac_matches($$) {
    my ($mac_1, $mac_2) = @_;

    if (parse_mac($mac_1) eq parse_mac($mac_2)) {
        return 1;
    }

    return 0;
}

########################################################################################
# Returns 1 if the device belongs to one of the scanned subnets.
########################################################################################
sub in_subnet($) {
	my ($device) = @_;

	$device = ip_to_long($device);

	# No subnets specified.
	return 1 if ($#SUBNETS < 0);

	foreach my $subnet (@SUBNETS) {
		if (subnet_matches($device, $subnet)) {
			return 1;
		}
	}

	return 0;
}

########################################################################################
# Returns undef if the device does not respond to SNMP queries.
########################################################################################
sub responds_to_snmp($) {
	my ($target) = @_;

	foreach my $community (@SNMP_COMMUNITIES) {
		`snmpwalk -M/dev/null -r$CONF{'snmp_checks'} -t$CONF{'snmp_timeout'} -v1 -On -Oe -c $community $target .0 2>/dev/null`;
		if ($? == 0) {
			$COMMUNITIES{$target} = $community;
			return $community;
		}
	}

	return undef;
}

########################################################################################
# Performs an SNMP WALK and returns the response as an array.
########################################################################################
sub snmp_get($$$) {
	my ($target, $community, $oid) = @_;
	my @output;

	# Check the SNMP query cache first.
	if (defined($SNMP_CACHE{"${target}_${oid}"})) {
		return @{$SNMP_CACHE{"${target}_${oid}"}};
	}

	my $vlans = defined($VLAN_CACHE{$target}) ? $VLAN_CACHE{$target} : [];
	if (!defined($vlans->[0])) {
		@output = `snmpwalk -M/dev/null -r$CONF{'snmp_checks'} -t$CONF{'snmp_timeout'}  -v1 -On -Oe -c $community $target $oid 2>/dev/null`;
	} else {
		# Handle duplicate lines.
		my %output_hash;
		foreach my $vlan (@{$vlans}) {
			foreach my $line (`snmpwalk -M/dev/null -r$CONF{'snmp_checks'} -t$CONF{'snmp_timeout'}  -v1 -On -Oe -c $community\@$vlan $target $oid 2>/dev/null`) {
				$output_hash{$line} = 1;
			}
		}
		push(@output, keys(%output_hash));
	}

	# Update the SNMP query cache.
	$SNMP_CACHE{"${target}_${oid}"} = [@output];

	return @output;
}

########################################################################################
# Performs an SNMP WALK and returns an array of values.
########################################################################################
sub snmp_get_value_array($$$) {
	my ($target, $community, $oid) = @_;
	my @values;

	my @output = snmp_get($target, $community, $oid);
	foreach my $line (@output) {
		chomp ($line);
		push(@values, $1) if ($line =~ /^$oid\S*\s+=\s+\S+:\s+(.*)$/);
	}

	return @values;
}

########################################################################################
# Performs an SNMP WALK and returns a hash of values.
########################################################################################
sub snmp_get_value_hash($$$) {
	my ($target, $community, $oid) = @_;
	my %values;

	my @output = snmp_get_value_array($target, $community, $oid);
	foreach my $line (@output) {
		$values{$line} = '';
	}

	return %values;
}

########################################################################################
# Returns 1 if the given IP address belongs to the given subnet.
########################################################################################
sub subnet_matches($$;$) {
	my ($ipaddr, $subnet, $mask) = @_;
	my ($netaddr, $netmask);

	# Decimal dot notation mask.
	if (defined($mask)) {
		$netaddr = $subnet;
		$netmask = ip_to_long($mask);
	}
	# CIDR notation.
	else {
		($netaddr, $netmask) = split('/', $subnet);
		return 0 unless defined($netmask);

		# Convert the netmask to a numeric format.
		$netmask = -1 << (32 - $netmask);
	}

	if ((ip_to_long($ipaddr) & $netmask) == (ip_to_long($netaddr) & $netmask)) {
		return 1;
	}

	return 0;
}

########################################################################################
# Performs an SNMP WALK and returns the value of the given OID. Returns undef
# on error.
########################################################################################
sub snmp_get_value($$$) {
	my ($target, $community, $oid) = @_;

	my @output = snmp_get($target, $community, $oid);
	foreach my $line (@output) {
		chomp ($line);
		return $1 if ($line =~ /^$oid\s+=\s+\S+:\s+(.*)$/);
	}

	return undef;
}

########################################################################################
# Get an interface name from an IP address.
########################################################################################
sub get_if_from_ip($$$) {
	my ($device, $community, $ip_addr) = @_;

	# Get the port associated to the IP address.
	my $if_index = snmp_get_value($device, $community, "$IPROUTEIFINDEX.$ip_addr");
	return '' unless defined ($if_index);

	# Get the name of the interface associated to the port.
	my $if_name = snmp_get_value($device, $community, "$IFNAME.$if_index");
	return '' unless defined ($if_name);
	
	$if_name =~ s/"//g;
	return $if_name;
}

########################################################################################
# Get an interface name from a MAC address.
########################################################################################
sub get_if_from_mac($$$) {
	my ($device, $community, $mac) = @_;

	# Get the port associated to the IP address.
	my @output = snmp_get($device, $community, $IFPHYSADDRESS);
	foreach my $line (@output) {
		chomp($line);
		next unless $line =~ /^$IFPHYSADDRESS.(\S+)\s+=\s+\S+:\s+(.*)$/;
		my ($if_index, $if_mac) = ($1, $2);
		next unless (mac_matches($mac, $if_mac) == 1);

		# Get the name of the interface associated to the port.
		my $if_name = snmp_get_value($device, $community, "$IFNAME.$if_index");
		return '' unless defined ($if_name);
		
		$if_name =~ s/"//g;
		return $if_name;
	}
	
	return '';
}

##########################################################################
# Connect the given hosts to its gateway.
##########################################################################
sub gateway_connectivity($) {
	my ($host) = @_;
	my $parent_id;

	# Find a gateway for the host.
	my $gw = get_gateway($host);
	return unless defined($gw);

	# Get the agent for the host.
	my $agent = get_agent_from_addr($DBH, $host);
	if (!defined($agent)) {
		$agent = get_agent_from_name($DBH, $host);
	}
	return unless defined($agent);

	# Check if the parent agent exists.
	my $agent_parent = get_agent_from_addr($DBH, $gw);
	if (!defined($agent_parent)) {
		$agent_parent = get_agent_from_name($DBH, $gw);
	}
	if (defined ($agent_parent)) {
		$parent_id = $agent_parent->{'id_agente'};
	} else {
		$parent_id = create_pandora_agent($gw);
	}

	# Connect the host to its parent.
	if ($parent_id > 0) {
		message("Host $host is connected to gateway $gw.");
		connect_pandora_agents($host, '', $gw, '');
		$VISITED_DEVICES{$host}->{'connected'} = 1 if defined($VISITED_DEVICES{$host});
	}
}

########################################################################################
# Get the gateway to reach the given host.
########################################################################################
sub get_gateway($) {
	my ($host) = @_;

	# Look for a specific route to the given host.
	foreach my $route (@ROUTE_CACHE) {
		if (subnet_matches($host, $route->{'dest'}, $route->{'mask'})) {
			return $route->{'gw'};
		}
	}

	# Return the default gateway.
	return $DEFAULT_GW if defined($DEFAULT_GW);

	# Ops!
	return undef;
}

########################################################################################
# Get an interface name from an AFT entry. Returns undef on error.
########################################################################################
sub get_if_from_aft($$$) {
	my ($switch, $community, $mac) = @_;

	# Get the port associated to the MAC.
	my $port = snmp_get_value($switch, $community, "$DOT1DTPFDBPORT." . mac_to_dec($mac));
	return '' unless defined($port);

	# Get the interface index associated to the port.
	my $if_index = snmp_get_value($switch, $community, "$DOT1DBASEPORTIFINDEX.$port");
	return '' unless defined($if_index);

	# Get the interface name.
	my $if_name = snmp_get_value($switch, $COMMUNITIES{$switch}, "$IFNAME.$if_index");
	return "if$if_index" unless defined($if_name);

	$if_name =~ s/"//g;
	return $if_name;

}

########################################################################################
# Returns the IP address of the given interface (by index).
########################################################################################
sub get_if_ip($$$) {
	my ($device, $community, $if_index) = @_;
	
	my @output = snmp_get($device, $community, $IPADENTIFINDEX);
	foreach my $line (@output) {
		chomp ($line);
		return $1 if ($line =~ m/^$IPADENTIFINDEX.(\S+)\s+=\s+\S+:\s+$if_index$/);
	}
	
	return '';
}

########################################################################################
# Returns the MAC address of the given interface (by index).
########################################################################################
sub get_if_mac($$$) {
	my ($device, $community, $if_index) = @_;

	my $mac = snmp_get_value($device, $community, "$IFPHYSADDRESS.$if_index");
	return '' unless defined($mac);

	# Clean-up the MAC address.
	$mac = parse_mac($mac);

	return $mac;
}

########################################################################################
# Find devices using ARP caches.
########################################################################################
sub arp_cache_discovery {
	my $device = shift;

	# Check if the device has already been visited.
	return if (defined($VISITED_DEVICES{$device}));

	# The device does not belong to one of the scanned sub-nets.
	return if (in_subnet($device) == 0);
		
	# Set a default device type.
	my $device_type = 'host';

	# Mark the device as visited.
	$VISITED_DEVICES{$device} = { 'addr' => { $device => '' },
	                              'connected' => 0,
	                              'type' => $device_type };

	# Check if the device responds to SNMP.
	my $community = defined($COMMUNITIES{$device}) ? $COMMUNITIES{$device} : responds_to_snmp($device);
	if (defined ($community)) {

		# Find VLANs and populate the VLAN cache.
		find_vlans($device, $community);

		# Guess device type.
		if ($device_type ne 'router') {
			$device_type = guess_device_type($device, $community);
			$VISITED_DEVICES{$device}->{'type'} = $device_type;
		}

		# Find aliases for the device.
		find_aliases($device, $community);
		
		# Get ARP cache.
		my @output = snmp_get($device, $community, $IPNETTOMEDIAPHYSADDRESS);
		foreach my $line (@output) {
			next unless ($line =~ /^$IPNETTOMEDIAPHYSADDRESS.\d+.(\S+)\s+=\s+\S+:\s+(.*)$/);
			my ($ip_addr, $mac_addr) = ($1, $2);
			next if ($ip_addr =~ m/\.255$|\.0$|127\.0\.0\.1$/);

			$mac_addr = parse_mac($mac_addr);

			# Save the mac to connect hosts to switches/routers.
			$ARP_CACHE{$mac_addr} = $ip_addr;

			# Recursively visit found devices.
			arp_cache_discovery($ip_addr);
		}
	}

	# Save the found host.
	push(@HOSTS, $device);

	# Create a Pandora FMS agent for the device.
	create_pandora_agent($device);
}

########################################################################################
# Find IP address aliases for the given device.
########################################################################################
sub find_aliases($$) {
	my ($device, $community) = @_;

	# Get ARP cache.
	my @ip_addresses = snmp_get_value_array($device, $community, $IPENTADDR);
	foreach my $ip_address (@ip_addresses) {
		next if ($ip_address =~ m/\.255$|\.0$|127\.0\.0\.1$/);

		# Sometimes we find the same IP address we had.
		next if ($ip_address eq $device);

		# Link both devices.
		$VISITED_DEVICES{$device}->{'addr'}->{$ip_address} = '';
		$VISITED_DEVICES{$ip_address} = $VISITED_DEVICES{$device};
		$COMMUNITIES{$ip_address} = $community;

		# Add it to the list of hosts to be scanned if it belongs to any of the
		# scanned subnets.
		if (in_subnet($device)) {
			push(@HOSTS, $ip_address);
		}
	}
}

########################################################################################
# Fill the route cache.
########################################################################################
sub find_routes() {

	# Parse route's output.
	my @output = `route -n 2>/dev/null`;
	foreach my $line (@output) {
		chomp($line);
		if ($line =~ /^0\.0\.0\.0\s+(\d+\.\d+\.\d+\.\d+).*/) {
			$DEFAULT_GW = $1;
		} elsif ($line =~ /^(\d+\.\d+\.\d+\.\d+)\s+(\d+\.\d+\.\d+\.\d+)\s+(\d+\.\d+\.\d+\.\d+).*/) {
			push(@ROUTE_CACHE, { dest => $1, gw => $2, mask => $3 });
		}
	}

	# Replace 0.0.0.0 with the default gateway's IP.
	return unless defined ($DEFAULT_GW);
	foreach my $route (@ROUTE_CACHE) {
		$route->{gw} = $DEFAULT_GW if ($route->{'gw'} eq '0.0.0.0');
	}
}

########################################################################################
# Find the device's VLANs and fill the VLAN cache.
########################################################################################
sub find_vlans ($$) {
	my ($device, $community) = @_;
	my %vlan_hash;

	foreach my $vlan (snmp_get_value_array($device, $community, $VTPVLANIFINDEX)) {
		next if $vlan eq '0';
		$vlan_hash{$vlan} = 1;
	}
	my @vlans = keys(%vlan_hash);

	$VLAN_CACHE{$device} = [];
	push(@{$VLAN_CACHE{$device}}, @vlans) if (scalar(@vlans) > 0);
}

########################################################################################
# Guess the type of the given device.
########################################################################################
sub guess_device_type($$) {
	my ($device, $community) = @_;
	my $device_type = 'host';

	# Get the value of sysServices.
	my $services = snmp_get_value($device, $community, "$SYSSERVICES.0");
	return $device_type unless defined($services);
	my @service_bits = split('', unpack('b8', pack('C', $services)));

	# Check for L2 connectivity support.
	my $bridge_mib = snmp_get_value($device, $community, $DOT1DBASEBRIDGEADDRESS);

	# L2?
	if ($service_bits[1] == 1) {
		# L3?
		if ($service_bits[2] == 1) {
			# Bridge MIB?
			if (defined($bridge_mib)) {
				return 'switch';
			} else {
				# L7?
				if ($service_bits[6] == 1) {
					return 'host';
				} else {
					return 'router';
				}
			}
		}
		else {
			# Bridge MIB?
			if (defined($bridge_mib)) {
				return 'switch';
			} else {
				return 'host';
			}
		}
	}
	else {
		# L3?
		if ($service_bits[2] == 1) {
			# L4?
			if ($service_bits[3] == 1) {
				return 'switch';
			} else {
				# L7?
				if ($service_bits[6] == 1) {
					return 'host';
				} else {
					return 'router';
				}
			}
		}
		else {
			# Printer MIB?
			my $printer_mib = snmp_get_value($device, $community, $PRTMARKERINDEX);
			if (defined($printer_mib)) {
				return 'printer';
			} else {
				return 'host';
			}
		}
	}
}

########################################################################################
# Discover switch to switch connectivity.
########################################################################################
sub switch_to_switch_connectivity($$) {
	my ($switch_1, $switch_2) = @_;
	my (%mac_temp, @aft_temp);

	# Make sure both switches respond to SNMP.
	return unless defined($COMMUNITIES{$switch_1} && $COMMUNITIES{$switch_2});

	# Get the list of MAC addresses of each switch.
	my %mac_1;
	%mac_temp = snmp_get_value_hash($switch_1, $COMMUNITIES{$switch_1}, $IFPHYSADDRESS);
	foreach my $mac (keys(%mac_temp)) {
		$mac_1{parse_mac($mac)} = '';
	}
	my %mac_2;
	%mac_temp = snmp_get_value_hash($switch_2, $COMMUNITIES{$switch_2}, $IFPHYSADDRESS);
	foreach my $mac (keys(%mac_temp)) {
		$mac_2{parse_mac($mac)} = '';
	}

	# Get the address forwarding table (AFT) of each switch.
	my @aft_1;
	@aft_temp = snmp_get_value_array($switch_1, $COMMUNITIES{$switch_1}, $DOT1DTPFDBADDRESS);
	foreach my $mac (@aft_temp) {
		push(@aft_1, parse_mac($mac));
	}
	my @aft_2;
	@aft_temp = snmp_get_value_array($switch_2, $COMMUNITIES{$switch_2}, $DOT1DTPFDBADDRESS);
	foreach my $mac (@aft_temp) {
		push(@aft_2, parse_mac($mac));
	}

	# Search for matching entries.
	foreach my $aft_mac_1 (@aft_1) {
		if (defined($mac_2{$aft_mac_1})) {
			foreach my $aft_mac_2 (@aft_2) {
				if (defined($mac_1{$aft_mac_2})) {
					my $if_name_1 = get_if_from_aft($switch_1, $COMMUNITIES{$switch_1}, $aft_mac_1);
					next unless ($if_name_1) ne '';
					my $if_name_2 = get_if_from_aft($switch_2, $COMMUNITIES{$switch_2}, $aft_mac_2);
					next unless ($if_name_2) ne '';

					message("Switch $switch_1 (if $if_name_1) is connected to switch $switch_2 (if $if_name_2).");
					connect_pandora_agents($switch_1, $if_name_1, $switch_2, $if_name_2);

					# Mark switch to switch connections.
					$SWITCH_TO_SWITCH{"$switch_1$if_name_1"} = 1;
					$SWITCH_TO_SWITCH{"$switch_2$if_name_2"} = 1;
				}
			}
		}
	}
}

########################################################################################
# Discover router to switch connectivity.
########################################################################################
sub router_to_switch_connectivity($$) {
	my ($router, $switch) = @_;
	my (%mac_temp, @aft_temp);

	# Make sure both devices respond to SNMP.
	return unless defined($COMMUNITIES{$router} && $COMMUNITIES{$switch});

	# Get the list of MAC addresses of the router.
	my %mac_router;
	%mac_temp = snmp_get_value_hash($router, $COMMUNITIES{$router}, $IFPHYSADDRESS);
	foreach my $mac (keys(%mac_temp)) {
		$mac_router{parse_mac($mac)} = '';
	}

	# Get the address forwarding table (AFT) of the switch.
	my @aft;
	@aft_temp = snmp_get_value_array($switch, $COMMUNITIES{$switch}, $DOT1DTPFDBADDRESS);
	foreach my $mac (@aft_temp) {
		push(@aft, parse_mac($mac));
	}

	# Search for matching entries in the AFT.
	foreach my $aft_mac (@aft) {
		if (defined($mac_router{$aft_mac})) {

			# Get the router interface.
			my $router_if_name = get_if_from_mac($router, $COMMUNITIES{$router}, $aft_mac);

			# Get the switch interface.
			my $switch_if_name = get_if_from_aft($switch, $COMMUNITIES{$switch}, $aft_mac);
			next unless ($switch_if_name ne '');

			message("Router $router (if $router_if_name) is connected to switch $switch (if $switch_if_name).");
			connect_pandora_agents($router, $router_if_name, $switch, $switch_if_name);

			# Mark connections in case the routers are switches too.
			$SWITCH_TO_SWITCH{"$switch$switch_if_name"} = 1;
			$SWITCH_TO_SWITCH{"$router$router_if_name"} = 1;
		}
	}
}

########################################################################################
# Discover router to router connectivity.
########################################################################################
sub router_to_router_connectivity($$) {
	my ($router_1, $router_2) = @_;

	# Make sure both routers respond to SNMP.
	return unless defined($COMMUNITIES{$router_1} && $COMMUNITIES{$router_2});

	# Get the list of next hops of the routers.
	my %next_hops_1 = snmp_get_value_hash($router_1, $COMMUNITIES{$router_1}, $IPROUTENEXTHOP);
	my %next_hops_2 = snmp_get_value_hash($router_2, $COMMUNITIES{$router_2}, $IPROUTENEXTHOP);

	# Search for matching entries.
	foreach my $ip_addr_1 (keys(%{$VISITED_DEVICES{$router_1}->{'addr'}})) {
		if (defined($next_hops_2{$ip_addr_1})) {
			foreach my $ip_addr_2 (keys(%{$VISITED_DEVICES{$router_2}->{'addr'}})) {
				if (defined($next_hops_1{$ip_addr_2})) {
					my $if_1 = get_if_from_ip($router_1, $COMMUNITIES{$router_1}, $ip_addr_2);
					my $if_2 = get_if_from_ip($router_2, $COMMUNITIES{$router_2}, $ip_addr_1);
					message("Router $ip_addr_1 (if $if_2) is connected to router $ip_addr_2 (if $if_2).");
					connect_pandora_agents($router_1, $if_1, $router_2, $if_2);
					
					# Mark connections in case the routers are switches too.
					$SWITCH_TO_SWITCH{"$router_1$if_1"} = 1;
					$SWITCH_TO_SWITCH{"$router_2$if_2"} = 1;
				}
			}
		}
	}
}

########################################################################################
# Discover host connectivity.
########################################################################################
sub host_connectivity($) {
	my ($device) = @_;

	# Make sure the device respond to SNMP.
	return unless defined($COMMUNITIES{$device});

	# Get the address forwarding table (AFT) of the device.
	my @aft = snmp_get_value_array($device, $COMMUNITIES{$device}, $DOT1DTPFDBADDRESS);
	foreach my $mac (@aft) {
		$mac = parse_mac($mac);
		my $host;
		if (defined ($ARP_CACHE{$mac})) {
			$host = $ARP_CACHE{$mac};
		} elsif (defined ($IF_CACHE{$mac})) {
			$host = $IF_CACHE{$mac};
		} else {
			next;
		}
		next unless defined ($VISITED_DEVICES{$host});
		my $device_if_name = get_if_from_aft($device, $COMMUNITIES{$device}, $mac);
		next unless ($device_if_name ne '');
		my $host_if_name = defined($COMMUNITIES{$host}) ? get_if_from_mac($host, $COMMUNITIES{$host}, $mac) : '';
		if ($VISITED_DEVICES{$device}->{'type'} eq 'router') {
			message("Host $host " . ($host_if_name ne '' ? "(if $host_if_name)" : '') . " is connected to router $device (if $device_if_name).");
		}
		elsif ($VISITED_DEVICES{$device}->{'type'} eq 'switch') {
			message("Host $host " . ($host_if_name ne '' ? "(if $host_if_name)" : '') . " is connected to switch $device (if $device_if_name).");
		}
		else {
			message("Host $host " . ($host_if_name ne '' ? "(if $host_if_name)" : '') . " is connected to host $device (if $device_if_name).");
		}
		connect_pandora_agents($host, $host_if_name, $device, $device_if_name);
	}
}

##########################################################################
# Create an agent for the given device. Returns the ID of the new (or
# existing) agent, undef on error.
##########################################################################
sub create_pandora_agent($) {
	my ($device) = @_;
	my $agent_id;

	# Does the agent already exist?
	my $agent = get_agent_from_addr($DBH, $device);
	if (!defined($agent)) {
		$agent = get_agent_from_name($DBH, $device);
	}
	
	# Create it.
	if (!defined($agent)) {
		my $id_os = 10; # Other.
		my $device_type = defined($VISITED_DEVICES{$device}) ? $VISITED_DEVICES{$device}->{'type'} : 'host';
		if ($device_type eq 'router') {
			$id_os = 17;
		}
		elsif ($device_type eq 'switch') {
			$id_os = 18;
		}
	
		$agent_id = pandora_create_agent(\%CONF, $CONF{'servername'}, $device, $device, $GROUP_ID, 0, $id_os, '', 300, $DBH);
		return undef unless defined ($agent_id) and ($agent_id > 0);

		pandora_event(\%CONF, "[RECON] New $device_type found (" . join(',', defined($VISITED_DEVICES{$device}) ? keys(%{$VISITED_DEVICES{$device}->{'addr'}}) : $device) . ").", $GROUP_ID, $agent_id, 2, 0, 0, 'recon_host_detected', 0, $DBH);
	}
	# Update it.
	else {
		$agent_id = $agent->{'id_agente'};
	}

	# Add found IP addresses to the agent.
	foreach my $ip_addr (defined($VISITED_DEVICES{$device}) ? keys(%{$VISITED_DEVICES{$device}->{'addr'}}) : ($device)) {
		my $addr_id = get_addr_id ($DBH, $ip_addr);

		# Create the address if it does not exist.
		$addr_id = add_address ($DBH, $ip_addr) unless ($addr_id > 0);
		next unless ($addr_id > 0);

		# Assign the new address to the agent
		if (!defined(get_address_agent($DBH, $addr_id, $agent_id))) {
			add_new_address_agent($DBH, $addr_id, $agent_id);
			message("Assigning address $ip_addr to agent $agent->{'nombre'}");
		}
	}

	# Create a ping module.
	my $module_id = get_agent_module_id($DBH, "ping", $agent_id);
	if ($module_id <= 0) {
		my %module = ('id_tipo_modulo' => 6,
			       'id_modulo' => 2,
		           'nombre' => "ping",
		           'descripcion' => '',
		           'id_agente' => $agent_id,
		           'ip_target' => $device);
		pandora_create_module_from_hash (\%CONF, \%module, $DBH);
	}

	# Add interfaces to the agent if it responds to SNMP.
	return $agent_id unless defined($COMMUNITIES{$device});
	my @output = snmp_get_value_array($device, $COMMUNITIES{$device}, $IFINDEX);
	foreach my $if_index (@output) {

		# Check the status of the interface.
		if ($ALLIFACES ne '-a') {
			my $if_status = snmp_get_value($device, $COMMUNITIES{$device}, "$IFOPERSTATUS.$if_index");
			next unless $if_status == 1;
		}

		# Fill the module description with the IP and MAC addresses.
		my $mac = get_if_mac($device, $COMMUNITIES{$device}, $if_index);
		my $ip = get_if_ip($device, $COMMUNITIES{$device}, $if_index);
		my $if_desc = ($mac ne '' ? "MAC $mac " : '') . ($ip ne '' ? "IP $ip" : '');

		# Fill the interface cache.
		$IF_CACHE{$mac} = $ip;

		# Get the name of the network interface.
		my $if_name = snmp_get_value($device, $COMMUNITIES{$device}, "$IFNAME.$if_index");
		$if_name = "if$if_index" unless defined ($if_name);
		$if_name =~ s/"//g;

		# Check whether the module already exists.
		my $module_id = get_agent_module_id($DBH, "ifOperStatus_${if_name}", $agent_id);
		next if ($module_id > 0);
	
		# Encode problematic characters.
		$if_name = safe_input($if_name);
		$if_desc = safe_input($if_desc);

		# Interface status module.
		my %module = ('id_tipo_modulo' => 18,
		           'id_modulo' => 2,
		           'nombre' => "ifOperStatus_${if_name}",
		           'descripcion' => $if_desc,
		           'id_agente' => $agent_id,
		           'ip_target' => $device,
		           'tcp_send' => 1,
		           'snmp_community' => $COMMUNITIES{$device},
		           'snmp_oid' => "$IFOPERSTATUS.$if_index");
		pandora_create_module_from_hash (\%CONF, \%module, $DBH);

		# Incoming traffic module.
		%module = ('id_tipo_modulo' => 16,
		           'id_modulo' => 2,
		           'nombre' => "ifInOctets_${if_name}",
		           'descripcion' => 'The total number of octets received on the interface, including framing characters.',
		           'id_agente' => $agent_id,
		           'ip_target' => $device,
		           'tcp_send' => 1,
		           'snmp_community' => $COMMUNITIES{$device},
		           'snmp_oid' => "$IFINOCTECTS.$if_index");
		pandora_create_module_from_hash (\%CONF, \%module, $DBH);

		# Outgoing traffic module.
		%module = ('id_tipo_modulo' => 16,
		           'id_modulo' => 2,
		           'nombre' => "ifOutOctets_${if_name}",
		           'descripcion' => 'The total number of octets received on the interface, including framing characters.',
		           'id_agente' => $agent_id,
		           'ip_target' => $device,
		           'tcp_send' => 1,
		           'snmp_community' => $COMMUNITIES{$device},
		           'snmp_oid' => "$IFOUTOCTECTS.$if_index");
		pandora_create_module_from_hash (\%CONF, \%module, $DBH);
	}

	return $agent_id;
}

##########################################################################
# Check for switches that are connected to other switches/routers and show
# up in a switche/router's port.
##########################################################################
sub switch_already_connected ($$$$) {
	my ($dev_1, $if_1, $dev_2, $if_2) = @_;

	return unless defined($VISITED_DEVICES{$dev_1}) and defined($VISITED_DEVICES{$dev_2});

	if ($VISITED_DEVICES{$dev_1}->{'type'} eq 'router' ||
	    $VISITED_DEVICES{$dev_1}->{'type'} eq 'switch') {
		return 1 if defined ($SWITCH_TO_SWITCH{"$dev_1$if_1"}); # The switch is probably connected to another router/switch.
	}
	elsif ($VISITED_DEVICES{$dev_2}->{'type'} eq 'router' ||
	    $VISITED_DEVICES{$dev_2}->{'type'} eq 'switch') {
		return 1 if defined ($SWITCH_TO_SWITCH{"$dev_2$if_2"}); # The switch is probably connected to another router/switch.
	}

	return 0;
}

##########################################################################
# Connect the given devices in the Pandora FMS database.
##########################################################################
sub connect_pandora_agents($$$$) {
	my ($dev_1, $if_1, $dev_2, $if_2) = @_;

	# Check switch connectivy.
	return if (switch_already_connected($dev_1, $if_1, $dev_2, $if_2) == 1);

	# Get the agent for the first device.
	my $agent_1 = get_agent_from_addr($DBH, $dev_1);
	if (!defined($agent_1)) {
		$agent_1 = get_agent_from_name($DBH, $dev_1);
	}
	return unless defined($agent_1);

	# Get the agent for the second device.
	my $agent_2 = get_agent_from_addr($DBH, $dev_2);
	if (!defined($agent_2)) {
		$agent_2 = get_agent_from_name($DBH, $dev_2);
	}
	return unless defined($agent_2);

	# Check whether the modules exists.
	my $module_name_1 = safe_input($if_1 eq '' ? 'ping' : "ifOperStatus_$if_1");
	my $module_name_2 = safe_input($if_2 eq '' ? 'ping' : "ifOperStatus_$if_2");
	my $module_id_1 = get_agent_module_id($DBH, $module_name_1, $agent_1->{'id_agente'});
	if ($module_id_1 <= 0) {
		message("ERROR: Module " . safe_output($module_name_1) . " does not exist for agent $dev_1.");
		return;
	}
	my $module_id_2 = get_agent_module_id($DBH, $module_name_2, $agent_2->{'id_agente'});
	if ($module_id_2 <= 0) {
		message("ERROR: Module " . safe_output($module_name_2) . " does not exist for agent $dev_2.");
		return;
	}

	# Make sure the modules are not already connected.
	if (defined($CONNECTIONS{"${module_id_1}_${module_id_2}"}) ||
	    defined($CONNECTIONS{"${module_id_2}_${module_id_1}"})) {
			message("Devices $dev_1 and $dev_2 are already connected.");
			return;
	}

	# Mark the two devices as connected.
	$CONNECTIONS{"${module_id_1}_${module_id_2}"} = 1;
	$VISITED_DEVICES{$dev_1}->{'connected'} = 1 if defined($VISITED_DEVICES{$dev_1});
	$VISITED_DEVICES{$dev_2}->{'connected'} = 1 if defined($VISITED_DEVICES{$dev_2});

	# Connect the modules if they are not already connected.
	my $connection_id = get_db_value($DBH, 'SELECT id FROM tmodule_relationship WHERE (module_a = ? AND module_b = ?) OR (module_b = ? AND module_a = ?)', $module_id_1, $module_id_2, $module_id_1, $module_id_2);
	if (! defined($connection_id)) {
		db_do($DBH, 'INSERT INTO tmodule_relationship (`module_a`, `module_b`, `id_rt`) VALUES(?, ?, ?)', $module_id_1, $module_id_2, $TASK_ID);
	}

	# Update parents.
	if (!defined($PARENTS{$agent_2->{'id_agente'}}) &&
	    (!defined($PARENTS{$agent_1->{'id_agente'}}) ||
	    $PARENTS{$agent_1->{'id_agente'}} != $agent_2->{'id_agente'})) {
		$PARENTS{$agent_2->{'id_agente'}} = $agent_1->{'id_agente'};
		db_do($DBH, 'UPDATE tagente SET id_parent=? WHERE id_agente=?', $agent_1->{'id_agente'}, $agent_2->{'id_agente'});
	} elsif (!defined($PARENTS{$agent_1->{'id_agente'}}) &&
	    (!defined($PARENTS{$agent_2->{'id_agente'}}) ||
	    $PARENTS{$agent_2->{'id_agente'}} != $agent_1->{'id_agente'})) {
		$PARENTS{$agent_1->{'id_agente'}} = $agent_2->{'id_agente'};
		db_do($DBH, 'UPDATE tagente SET id_parent=? WHERE id_agente=?', $agent_2->{'id_agente'}, $agent_1->{'id_agente'});
	}
}


##########################################################################
# Delete unused connections.
##########################################################################
sub delete_unused_connections($$$$) {
    my ($conf, $dbh, $task_id, $connections) = @_;

    message("Deleting unused connections...");
    my @relations = get_db_rows($dbh, 'SELECT * FROM tmodule_relationship WHERE disable_update=0 AND id_rt=?', $task_id);
    foreach my $relation (@relations) {
        my $module_a = $relation->{'module_a'};
        my $module_b = $relation->{'module_b'};
        if (!defined($connections->{"${module_a}_${module_b}"}) && !defined($connections->{"${module_b}_${module_a}"})) {
            db_do($dbh, 'DELETE FROM tmodule_relationship WHERE id=?', $relation->{'id'});
        }
    }
}

##########################################################################
# Update recon task status.
##########################################################################
sub update_recon_task ($$$) {
	my ($dbh, $id_task, $status) = @_;

	db_do ($dbh, 'UPDATE trecon_task SET utimestamp = ?, status = ? WHERE id_rt = ?', time (), $status, $id_task);
}

##########################################################################
# Show help
##########################################################################
sub show_help {
	print "\nPandora FMS SNMP Recon Plugin for level 2 network topology discovery.\n";
	print "(c) Artica ST 2014 <info\@artica.es>\n\n";
	print "Usage:\n\n";
	print "   $0 <task_id> <group_id> <create_incident> <custom_field1> <custom_field2> [custom_field3] [custom_field4]\n\n";
	print " * custom_field1 = comma separated list of networks (i.e.: 192.168.1.0/24,192.168.2.0/24)\n";
	print " * custom_field2 = comma separated list of snmp communities to try.\n";
	print " * custom_field3 = a router in the network. Optional but recommended.\n\n";
	print " * custom_field4 = set to -a to add all network interfaces (by default only interfaces that are up are added).\n\n";
	print " Additional information:\nWhen the script is called from a recon task the task_id, group_id and create_incident";
	print " parameters are automatically filled by the Pandora FMS Server.\n";
	exit;
}

##########################################################################
# Connect the given hosts to its parent using traceroute.
##########################################################################
sub traceroute_connectivity($) {
	my ($host) = @_;

	# Get the agent for the first device.
	my $agent = get_agent_from_addr($DBH, $host);
	if (!defined($agent)) {
		$agent = get_agent_from_name($DBH, $host);
	}
	return unless defined($agent);

	# Perform a traceroute.
	my $nmap_args  = '-nsP -PE --traceroute --max-retries '.$CONF{'icmp_checks'}.' --host-timeout '.$CONF{'networktimeout'}.'s -T'.$CONF{'recon_timing_template'};
	my $np = new PandoraFMS::NmapParser;
	eval {
		$np->parsescan($CONF{'nmap'}, $nmap_args, ($host));
	};
	return if ($@);
	
	# Get hops to the host.
	my ($h) = $np->all_hosts ();
	return unless defined ($h);
	my @hops = $h->all_trace_hops ();

	# Skip the target host.
	pop(@hops);
	
	# Reverse the host order (closest hosts first).
	@hops = reverse(@hops);

	# Look for parents.
	my ($parent_id, $parent_address) = (0, undef);
	my ($child_id, $child_address) = ($agent->{'id_agente'}, $host);
	foreach my $hop (@hops) {
		$parent_address = $hop->ipaddr();

		# Check if the parent agent exists.
		my $agent_parent = get_agent_from_addr($DBH, $parent_address);
		if (!defined($agent_parent)) {
			$agent_parent = get_agent_from_name($DBH, $parent_address);
		}
		if (defined ($agent_parent)) {
			$parent_id = $agent_parent->{'id_agente'};
		} else {
			$parent_id = create_pandora_agent ($parent_address);
		}

		# Connect the host to its parent.
		if ($parent_id > 0) {
			message("Host $child_address is connected to host $parent_address.") if (defined($VISITED_DEVICES{$child_address}));
			connect_pandora_agents($child_address, '', $parent_address, '');
			$VISITED_DEVICES{$child_address}->{'connected'} = 1 if defined($VISITED_DEVICES{$child_address});

			# Move on to the next hop.
			($child_id, $child_address) = ($parent_id, $parent_address);
		}
	}
}

##########################################################################
##########################################################################
## Main.
##########################################################################
##########################################################################
if ($#ARGV < 3 ) {
	show_help();
}

$TASK_ID = $ARGV[0];
$GROUP_ID = $ARGV[1]; # Defined by user
$CREATE_INCIDENT = $ARGV[2]; # Defined by user
@SUBNETS = split(',', $ARGV[3]);
@SNMP_COMMUNITIES = split(',', $ARGV[4]) if defined($ARGV[4]);
#$ROUTER = $ARGV[5] if defined($ARGV[5]); # Not used anymore.
$ALLIFACES = $ARGV[6] if defined($ARGV[6]);

# Read config filea and start logging.
pandora_load_config(\%CONF);
pandora_start_log(\%CONF);

# Connect to the DB
$DBH = db_connect ('mysql', $CONF{'dbname'}, $CONF{'dbhost'}, $CONF{'dbport'}, $CONF{'dbuser'}, $CONF{'dbpass'});

# 0%
update_recon_task($DBH, $TASK_ID, 1);

# Find devices.
message("[1/7] Searching for switches and end hosts...");
foreach my $subnet (@SUBNETS) {
    my $net_addr = new NetAddr::IP ($subnet);
	if (!defined($net_addr)) {
		message("Invalid network: $subnet");
		exit 1;
	}

	my @hosts = map { (split('/', $_))[0] } $net_addr->hostenum;
	foreach my $host (@hosts) {

		message("Scanning host: $host");

		# Check if the device has already been visited.
		next if (defined($VISITED_DEVICES{$host}));

		# Check if the host is up.
		next if (pandora_ping(\%CONF, $host, 1, 1) == 0);
	
		arp_cache_discovery($host);
	}
}
update_recon_task($DBH, $TASK_ID, 30);

# Find switch to switch connections.
message("[2/7] Finding switch to switch connectivity...");
for (my $i = 0; defined($HOSTS[$i]); $i++) {
	my $switch_1 = $HOSTS[$i];
	for (my $j = $i + 1; defined($HOSTS[$j]); $j++) {
		my $switch_2 = $HOSTS[$j];
		switch_to_switch_connectivity($switch_1, $switch_2) if ($switch_1 ne $switch_2);
	}
}
update_recon_task($DBH, $TASK_ID, 45);

# Find router to switch connections.
message("[3/7] Finding router to switch connectivity...");
for (my $i = 0; defined($HOSTS[$i]); $i++) {
	my $router = $HOSTS[$i];
	for (my $j = $i + 1; defined($HOSTS[$j]); $j++) {
		my $switch = $HOSTS[$j];
		router_to_switch_connectivity($router, $switch) if ($router ne $switch);
	}
}
update_recon_task($DBH, $TASK_ID, 60);

# Find router to router connections.
message("[4/7] Finding router to router connectivity...");
for (my $i = 0; defined($HOSTS[$i]); $i++) {
	my $router_1 = $HOSTS[$i];
	for (my $j = $i + 1; defined($HOSTS[$j]); $j++) {
		my $router_2 = $HOSTS[$j];
		router_to_router_connectivity($router_1, $router_2) if ($router_1 ne $router_2);
	}
}
update_recon_task($DBH, $TASK_ID, 75);

# Find switch/router to host connections.
message("[5/7] Finding switch/router to end host connectivity...");
foreach my $device (@HOSTS) {
	host_connectivity($device);
}

# Connect hosts that are still unconnected using configured routes.
message("[6/7] Finding traceroute connectivity...");
foreach my $host (@HOSTS) {
	next unless ($VISITED_DEVICES{$host}->{'connected'} == 0); # Skip already connected hosts.
	traceroute_connectivity($host);
}
update_recon_task($DBH, $TASK_ID, 90);

# Connect hosts that are still unconnected using traceroute.
message("[6/7] Finding gateway connectivity...");
find_routes();
foreach my $host (@HOSTS) {
	next unless ($VISITED_DEVICES{$host}->{'connected'} == 0); # Skip already connected hosts.
	gateway_connectivity($host);
}
update_recon_task($DBH, $TASK_ID, -1);

# Print debug information on found devices.
message("[Summary]");
foreach my $device (values(%VISITED_DEVICES)) {
	if (ref($device) eq 'HASH') {
		my $dev_info = "Device: " . $device->{'type'} . " ("; 
		foreach my $ip_address (keys(%{$device->{'addr'}})) {
			$dev_info .= "$ip_address,";
		}
		chop($dev_info);
		$dev_info .= ')';
		message($dev_info);
	}
}

# Do not delete unused connections unless at least one connection has been found
# (prevents the script from deleting connections if there has been a network outage).
delete_unused_connections(\%CONF, $DBH, $TASK_ID,\%CONNECTIONS) if (scalar(keys(%CONNECTIONS)) > 0);
