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

# this script should run every minute ($TUNE?) to ensure that revocations
# are propagated to the web server timely.

TARGETHOST="ocsp-test.hosted.eduroam.org"
TARGETUSER="statements"
TARGETDIR_RSA="/home/statements-rsa/files"
TARGETDIR_ECDSA="/home/statements-ecdsa/files"
TARGETSCRIPT_RSA="/home/statements-rsa/mv_files.sh"
TARGETSCRIPT_ECDSA="/home/statements-ecdsa/mv_files.sh"
SSH_KEY="/root/.ssh/statements_id_rsa"

script="$0"
basename="$(dirname $script)"

php $basename/ocsp_update.php
scp -i $SSH_KEY $basename/temp_ocsp_RSA/* $TARGETUSER@$TARGETHOST:$TARGETDIR_RSA
scp -i $SSH_KEY $basename/temp_ocsp_ECDSA/* $TARGETUSER@$TARGETHOST:$TARGETDIR_ECDSA
rm -R $basename/temp_ocsp_RSA $basename/temp_ocsp_ECDSA
ssh -l $TARGETUSER -i $SSH_KEY $TARGETHOST $TARGETSCRIPT_RSA
ssh -l $TARGETUSER -i $SSH_KEY $TARGETHOST $TARGETSCRIPT_ECDSA
