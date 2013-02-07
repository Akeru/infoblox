# NIOS

You will find below two kind of libraries :

* The first one is a PHP wrapper arround the REST wapi
* The second ones are smal perl script using the papi

## NIOS WAPI (very early release)

The NiosClient is a small PHP library with interacts with the WAPI. It uses [guzzle](http://guzzlephp.org) to ease curl calls.

The goal of the library is to expose low level access (CRUD operations) as well as higher levels functions :

* finding next free name based on a prefix
* sorting aliases
* moving aliases from one host to another

As the WAPI does not currently allow to retrieve all fields for a given record type, the library will fetch them by parsing the HTML wapidoc and create an array from it.

### Installation

The only dependency is Guzzle and it should be resolved using [composer](http://getcomposer.org).

### Usage

Here are a few sample calls. Please see source code for more details.

Create a new instance :

    $gridMaster = '10.1.11.1';
    $username = 'wapiuser';
    $password = 'wapipassword';
    $sslCheck = false;
    $client = new NiosClient($gridMaster, $username, $password, $sslCheck);

Get a host record by its address :

    $host = $client->getHostAddressByAddress('10.1.11.10'));

Get the network record which contains the given address only if its comment contains DMZ

    $networkAddress = '10.1.10.0';
    $network = $client->getNetwork(array(array('comment', '~:', 'DMZ'), array('contains_address', $networkAddress),));

Get the next 3 addresses ignoring the first 2 :

    $ignores = array('10.1.10.1', '10.1.10.2');
    $ipCount = 3;
    $ref = $network['_ref'];
    print_r($client->getNextAvailableIpsForNetwork($ref, $ipCount, $ignores));

Get 10 next available names starting with the prefix with the given minimum padding :

    $prefix = 'wapi-prefix';
    $count = 10;
    $minPadding = 1;
    print_r($client->getNextAvailableNames($prefix, $count, $minPadding));

Sort aliases of the given hostname :

    $hostname = 'host.example.net';
    $host = $client->getHostByName($hostname);
    $client->sortHostAliases($host);

## NIOS PAPI (deprecated)

### update-api.sh

Since CPAN upgrade is obsolete and manual upgrade cumbersome, this script will install the Perl API from the given Grid Master after some sanity checks.

Sample usage :

	$ ./update-api.sh 10.1.11.1

### api-sample.pl

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

### move-alias.pl

Since the host aliases GUI is sometimes a bit weak, this simple script can be used to move an alias from a source host to a destination host.

Sample usage :

	$ ./move-alias.pl www.example.org oldserver.example.org newserver.example.org

### sort-aliases.pl

Since the host aliases GUI is sometimes a bit weak, this simple script can be used to sort all the aliases of the given host.

**Warning**, this is done by removing all the aliases to add them back sorted, so for a few seconds, none of the aliases are available.

Sample usage :

	$ ./sort-aliases.pl server.example.org

### add-fixedaddress-template.pl

This simple script was inspired by the "Convert to Host" functionality of a FixedAddress record.
So, it can be used to replace the IP address of the given host by its fixed address equivalent created from the given mac-address and template.

**Warning**, this is done by removing the current IP address to add it back once created using the template, so for a few seconds, the DNS is unavailable.

Sample usage :

	$ ./add-fixedaddress-template server02.example.org 019e.b528.cfd2 "Camera address template"
