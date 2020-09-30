#!/bin/bash
#/*
# * *****************************************************************************
# * Contributions to this work were made on behalf of the GÉANT project, a 
# * project that has received funding from the European Union’s Framework 
# * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
# * Horizon 2020 research and innovation programme under Grant Agreements No. 
# * 691567 (GN4-1) and No. 731122 (GN4-2).
# * On behalf of the aforementioned projects, GEANT Association is the sole owner
# * of the copyright in all material which was developed by a member of the GÉANT
# * project. GÉANT Vereniging (Association) is registered with the Chamber of 
# * Commerce in Amsterdam with registration number 40535155 and operates in the 
# * UK as a branch of GÉANT Vereniging.
# * 
# * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
# * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
# *
# * License: see the web/copyright.inc.php file in the file structure or
# *          <base_url>/copyright.php after deploying the software
# */

# This script can be used to regularly update the GeoIP databases if you decide to install and use them
# GeoIP location is *not* required by CAT service to operate but can be helpful


# GeoIPDir is required only by the legacy version, if you use it then set this to the proper directory
GeoIPDir=/usr/share/GeoIP

# create temporary directory
mkdir /tmp/GeoIP >& /dev/null

dir=`dirname $0`
cd $dir

# first test for the GeoIP version set in the config.php
a=`php << EOF
<?php
require "../config/Master.php";
print(\config\Master::GEOIP["version"]);
?>
EOF`

if [ $a -eq 1 ] ; then
   cd /tmp/GeoIP
   rm -f GeoIP.dat.gz GeoIPv6.dat.gz GeoIPCity.dat.gz GeoIPCityv6.dat.gz
   wget --quiet -O GeoIP.dat.gz http://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz
   wget --quiet -O GeoIPv6.dat.gz http://geolite.maxmind.com/download/geoip/database/GeoIPv6.dat.gz
   wget --quiet -O GeoIPCity.dat.gz http://geolite.maxmind.com/download/geoip/database/GeoLiteCity.dat.gz
   wget --quiet -O GeoIPCityv6.dat.gz  http://geolite.maxmind.com/download/geoip/database/GeoLiteCityv6-beta/GeoLiteCityv6.dat.gz
   gunzip -f GeoIP.dat.gz GeoIPv6.dat.gz GeoIPCity.dat.gz GeoIPCityv6.dat.gz
   cp GeoIP.dat GeoIPv6.dat GeoIPCity.dat GeoIPCityv6.dat $GeoIPDir
fi


if [ $a -eq 2 ] ; then

db=`php << EOFF
<?php
require "../config/Master.php";
print(\config\Master::GEOIP["geoip2-path-to-db"]);
?>

lkey=`php << EOFF
<?php
require "../config/Master.php";
print(\config\Master::GEOIP["geoip2-license-key"]);
?>

EOFF`
   cd /tmp/GeoIP
   rm -f GeoLite2-City.mmdb.gz
   wget --quiet -O GeoLite2-City.mmdb.gz https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key=$lkey&suffix=tar.gz
   gunzip -f GeoLite2-City.mmdb.gz
   cp GeoLite2-City.mmdb $db
fi
