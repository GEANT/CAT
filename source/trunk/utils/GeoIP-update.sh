#!/bin/bash

GeoIPDir=/usr/share/GeoIP

cd /tmp
mkdir GeoIP >& /dev/null
cd GeoIP

rm -f GeoIP.dat.gz GeoIPv6.dat.gz GeoIPCity.dat.gz GeoIPCityv6.dat.gz
wget --quiet -O GeoIP.dat.gz http://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz
wget --quiet -O GeoIPv6.dat.gz http://geolite.maxmind.com/download/geoip/database/GeoIPv6.dat.gz

wget --quiet -O GeoIPCity.dat.gz http://geolite.maxmind.com/download/geoip/database/GeoLiteCity.dat.gz

wget --quiet -O GeoIPCityv6.dat.gz  http://geolite.maxmind.com/download/geoip/database/GeoLiteCityv6-beta/GeoLiteCityv6.dat.gz

gunzip -f GeoIP.dat.gz GeoIPv6.dat.gz GeoIPCity.dat.gz GeoIPCityv6.dat.gz

cp GeoIP.dat GeoIPv6.dat GeoIPCity.dat GeoIPCityv6.dat $GeoIPDir

