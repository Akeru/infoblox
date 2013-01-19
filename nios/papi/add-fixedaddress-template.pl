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
	if ($#ARGV != 2) {
		print "Usage: add-fixedaddress-template host mac-address template-name\n";
		print "eg: ./add-fixedaddress-template server01.example.org 02:78:51:96:D5:B0 \"Camera address template\"\n";
		print "eg: ./add-fixedaddress-template server02.example.org 019e.b528.cfd2 \"Phone address template\"\n";
		exit;
	} else {
		my $hostName = $ARGV[0];
		my $macAddress = $ARGV[1];
		my $templateName = $ARGV[2];
		my @templates = $session->search(object => "Infoblox::DHCP::FixedAddrTemplate", "name" => $templateName);
		if ($session->status_code()) {
			if($session->status_code() == 1003) {
				print "Could not find template $templateName\n"
			} else {
				handleError $session, "FixedAddrTemplate record search failed";
			}
		} else {
			print "Searching for host $hostName\n";
			my @hosts = $session->search(object => "Infoblox::DNS::Host", "name" => $hostName);
			if ($session->status_code()) {
				if($session->status_code() == 1003) {
					print "Could not find host $hostName\n"
				} else {
					handleError $session, "Host record search failed";
				}
			} else {
				my $host = $hosts[0];
				my $addresses = $host->ipv4addrs();
				my $address = @$addresses[0];
				my $fixedAddress = Infoblox::DHCP::FixedAddr->new("template" => $templateName, "ipv4addr" => $address, "mac" => $macAddress);
				my $created = $session->add($fixedAddress);
				if(!$created) {
					handleError $session, "FixedAddr creation failed";
				} else {
					my @fixedAddresses = $session->search(object => "Infoblox::DHCP::FixedAddr", "ipv4addr" => $address);
					if ($session->status_code()) {
						if($session->status_code() == 1003) {
							print "Could not find fixed address $macAddress\n"
						} else {
							handleError $session, "FixedAddr record search failed";
						}
					} else {
						my $fixedAddress = $fixedAddresses[0]; 
						my $removed = $session->remove($fixedAddress);
						if($removed) {
							shift(@$addresses);
							push(@$addresses, $fixedAddress);
							$host->ipv4addrs($addresses); 
							my $modified = $session->modify($host);
							if(!$modified) {
								handleError $session, "Host $hostName modification failed";
							} else {
								print "Fixed address added on $hostName\n";
							}
						} else {
							handleError $session, "FixedAddr record removal failed";
						}
					}
				}
			}			
		}
	}
}
