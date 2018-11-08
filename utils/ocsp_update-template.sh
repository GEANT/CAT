#!/bin/bash

# this script should run every minute ($TUNE?) to ensure that revocations
# are propagated to the web server timely.

TARGETHOST="ocsp-test.hosted.eduroam.org"
TARGETUSER="statements"
TARGETDIR_RSA="/home/statements-rsa/files"
TARGETDIR_ECDSA="/home/statements-ecdsa/files"
TARGETSCRIPT_RSA="/home/statements-rsa/mv_files.sh"
TARGETSCRIPT_RSA="/home/statements-ecdsa/mv_files.sh"
SSH_KEY="/root/.ssh/statements_id_rsa"

script="$0"
basename="$(dirname $script)"

php $basename/ocsp_update.php
scp -i $SSH_KEY $basename/temp_ocsp_RSA/* $TARGETUSER@$TARGETHOST:$TARGETDIR_RSA
scp -i $SSH_KEY $basename/temp_ocsp_ECDSA/* $TARGETUSER@$TARGETHOST:$TARGETDIR_ECDSA
rm -R $basename/temp_ocsp_RSA $basename/temp_ocsp_ECDSA
ssh -l $TARGETUSER -i $SSH_KEY $TARGETHOST $TARGETSCRIPT_RSA
ssh -l $TARGETUSER -i $SSH_KEY $TARGETHOST $TARGETSCRIPT_ECDSA
