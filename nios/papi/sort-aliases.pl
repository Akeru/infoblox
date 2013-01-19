#!/usr/bin/perl

use strict;
use warnings;
use utf8;
use Data::Dumper;
use Infoblox;

require Credentials;

sub handleError {
	my $session = $_[0];
	my $message = $_[1];
	my $detail = $session->status_detail();
	my $code = $session->status_code();
	print "$message : $detail ($code)\n";
}

print "Connecting to grid master $Credentials::data->{master}\n";

my $session = Infoblox::Session->new(master => $Credentials::data->{master}, username => $Credentials::data->{username}, password => $Credentials::data->{password});

if ($session->status_code()) {
	handleError $session,"Connection error";
} else {
	print "Connected successfully\n";
	if ($#ARGV != 0) {
		print "Usage: sort-aliases host\n";
		print "eg: sort-aliases server1.example.org\n";
		print "Warning, this is done by removing all the aliases to add them back sorted, so for a few seconds, none of the aliases are available\n";
		exit;
	} else {
		my $hostName = $ARGV[0];
		print "Searching for host $hostName\n";
		my @hosts = $session->search(object => 'Infoblox::DNS::Host', 'name' => $hostName);
		if ($session->status_code()) {
			if($session->status_code() == 1003) {
				print "Could not find host $hostName\n"
			} else {
				handleError $session, "Host record search failed";
			}
		} else {
			my $host = $hosts[0];
			if(!defined($host->ttl())) {
				print "Warning: source host $hostName TTL not overriden, you might experience caching issue\n";
			}
			my $aliases = $host->aliases();
			my @sortedAliases = sort(@$aliases);
			$host->aliases(undef);
			my $modified = $session->modify($host);
			if(!$modified) {
				handleError $session, "Could not remove aliases on $hostName";
			} else {
				$host->aliases([@sortedAliases]);
				my $modified = $session->modify($host);
				if(!$modified) {
					handleError $session, "Host $hostName modification failed";
				} else {
					print "Alias sorted on $hostName\n";
				}
			}			
		}		
	}
}
