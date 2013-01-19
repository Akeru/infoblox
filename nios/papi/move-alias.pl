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
	if ($#ARGV != 2 ) {
		print "Usage: move-alias alias source destination\n";
		print "eg: move-alias www.example.org server1.example.org server2.example.org\n";
		exit;
	} else {
		my $alias = $ARGV[0];
		my $source = $ARGV[1];
		my $destination = $ARGV[2];
		print "Searching for source host $source\n";
		my @hosts = $session->search(object => 'Infoblox::DNS::Host', 'name' => $source);
		if ($session->status_code()) {
			if($session->status_code() == 1003) {
				print "Could not find source host $source\n"
			} else {
				handleError $session, "Host record search failed";
			}
		} else {
			my $sourceHost = $hosts[0];
			if(!defined($sourceHost->ttl())) {
				print "Warning: source host $source TTL not overriden, you might experience caching issue\n"
			}
			my $sourceAliases = $sourceHost->aliases();
			my $found = 0;
			my @newSourceAliases = ();
			print "Searching for alias $alias on host $source\n";
			foreach my $sourceAlias (@$sourceAliases) {
				if ($sourceAlias eq $alias) {
					$found = 1
				} else {
					push(@newSourceAliases, $sourceAlias);
				}
			}			
			if($found == 0) {
				print "Source host $source does not have the alias $alias\n";
			} else {
				print "Searching for destination host $destination\n";
				my @hosts = $session->search(object => 'Infoblox::DNS::Host', 'name' => $destination);
				if ($session->status_code()) {
					if($session->status_code() == 1003) {
						print "Could not find destination host $destination\n"
					} else {
						handleError $session, "Host record search failed";
					}
				} else {
					my $destinationHost = $hosts[0];
					my $destinationAliases = $destinationHost->aliases();
					push(@$destinationAliases, $alias);
					$destinationHost->aliases(@$destinationAliases);
					$sourceHost->aliases([@newSourceAliases]);
					print "Removing alias $alias from source host $source\n";
					my $modified = $session->modify($sourceHost);
					if(!$modified) {
						handleError $session, "Source host $source modification failed";
					} else {
						print "Adding alias $alias to destination host $destination\n";
						$modified = $session->modify($destinationHost);
						if(!$modified) {
							handleError $session, "Destination host $destination modification failed";
						} else {
							print "Alias moved from $source to $destination\n";
						}
					}
				}
			}
		}		
	}
}
