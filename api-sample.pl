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

print "Reading config file\n";

my $config = $Credentials::data;

print "Connecting to grid master\n";

my $session = Infoblox::Session->new(master => $config->{master}, username => $config->{username}, password => $config->{password});

if ($session->status_code()) {
	handleError $session,"Connection error";
} else {
	print "Connected successfully\n";
}

