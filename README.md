Infoblox scripts repository
=============================

This repository contains various Infoblox related scripts.

License
---------

They works for me, please make no other assumptions.

update-api.sh
-------------

This script will fetch the Perl API from the given Grid Master.
If the current version is older than the new one, it will be installed.
A warning will be produced in case of downgrade or if the API was not correctly installed.

Sample usage :

	$ ./update-api.sh 10.1.11.1

