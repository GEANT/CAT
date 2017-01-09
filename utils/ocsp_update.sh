#!/bin/bash

# this script should run every minute ($TUNE?) to ensure that revocations
# are propagated to the web server timely.

TARGETHOST="ocsp-test.hosted.eduroam.org"
TARGETUSER="statements"
TARGETDIR="/var/www/html/ticker/statements/"
SSH_KEY="/root/.ssh/id_ed25519"

script="$0"
basename="$(dirname $script)"

php $basename/ocsp_update.php
scp -i $SSH_KEY $basename/temp_ocsp/* $TARGETUSER@$TARGETHOST:$TARGETDIR
rm -R $basename/temp_ocsp
