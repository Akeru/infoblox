Disclamer
=========

They work for me, please make no other assumptions.

NIOS PAPI
=========


update-api.sh
-------------

Since CPAN upgrade is obsolete and manual upgrade cumbersome, this script will install the Perl API from the given Grid Master after some sanity checks.

Sample usage :

	$ ./update-api.sh 10.1.11.1

api-sample.pl
-------------

This script can be used as the starting point of others and requires a Credentials.pm file present in your @INC path which should look like :

	package Credentials;
	$data = {
		username => "username",
		password => "password",
		master => "10.1.11.1",	
	};
	1; 

Sample usage :

	$ ./api-sample.pl

move-alias.pl
-------------

Since the host aliases GUI is sometimes a bit weak, this simple script can be used to move an alias from a source host to a destination host.

Sample usage :

	$ ./move-alias.pl www.example.org oldserver.example.org newserver.example.org

sort-aliases.pl
---------------

Since the host aliases GUI is sometimes a bit weak, this simple script can be used to sort all the aliases of the given host.

**Warning**, this is done by removing all the aliases to add them back sorted, so for a few seconds, none of the aliases are available.

Sample usage :

	$ ./sort-aliases.pl server.example.org

add-fixedaddress-template.pl
----------------------------

This simple script was inspired by the "Convert to Host" functionality of a FixedAddress record.
So, it can be used to replace the IP address of the given host by its fixed address equivalent created from the given mac-address and template.

**Warning**, this is done by removing the current IP address to add it back once created using the template, so for a few seconds, the DNS is unavailable.

Sample usage :

	$ ./add-fixedaddress-template server02.example.org 019e.b528.cfd2 "Camera address template"

