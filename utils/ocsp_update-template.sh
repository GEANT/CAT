#!/bin/bash

# this script should run every minute ($TUNE?) to ensure that revocations
# are propagated to the web server timely.

TARGETHOST="ocsp-test.hosted.eduroam.org"
TARGETUSER="statements"
TARGETDIR="/home/statements/files"
TARGETSCRIPT="/home/statements/mv_files.sh"
SSH_KEY="/root/.ssh/statements_id_rsa"

script="$0"
basename="$(dirname $script)"

php $basename/ocsp_update.php
scp -i $SSH_KEY $basename/temp_ocsp/* $TARGETUSER@$TARGETHOST:$TARGETDIR
rm -R $basename/temp_ocsp
ssh -l $TARGETUSER -i $SSH_KEY $TARGETHOST $TARGETSCRIPT
