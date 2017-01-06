#!/bin/bash

# this script should run every minute ($TUNE?) to ensure that revocations
# are propagated to the web server timely.

TARGETHOST="ocsp-test.hosted.eduroam.org"
TARGETUSER="root"
TARGETDIR="/var/www/htdocs/ticker/statements/"

php ./ocsp_update.php
scp ./temp_ocsp/* $TARGETUSER@$TARGETHOST:$TARGETDIR
rm -R ./temp_ocsp
