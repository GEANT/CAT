#!/bin/bash


# GeoIPDir is required only by the legacy version, if you use it then set this to the proper directory
GeoIPDir=/usr/share/GeoIP

# create temporary directory
mkdir /tmp/GeoIP >& /dev/null

dir=`dirname $0`
cd $dir

# first test for the GeoIP version set in the config.php
a=`php << EOF
<?php
require "../config/config.php";
print(Config::\\\$GEOIP["version"]);
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
require "../config/config.php";
print(Config::\\\$GEOIP["geoip2-path-to-db"]);
?>
EOFF`
   cd /tmp/GeoIP
   rm -f GeoLite2-City.mmdb.gz
   wget --quiet -O GeoLite2-City.mmdb.gz http://geolite.maxmind.com/download/geoip/database/GeoLite2-City.mmdb.gz
   gunzip -f GeoLite2-City.mmdb.gz
   cp GeoLite2-City.mmdb $db
fi
