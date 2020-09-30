<?php

/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
 */

require_once "autoloader.php";
require_once __DIR__ . "/../packageRoot.php";

// enable Composer autoloader, if exists
if (file_exists(__DIR__ . "/../vendor/autoload.php") !== FALSE) {
    include_once __DIR__ . "/../vendor/autoload.php";
}

if (!file_exists(ROOT . "/config/Master.php")) {
    echo "Master configuration file not found. You need to configure the product! At least config/Master.php is required!";
    throw new Exception("Master config file not found!");
}

/* load sub-configs if we are dealing with those in this installation */

if (\config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_SILVERBULLET'] == 'LOCAL' || \config\Master::FUNCTIONALITY_LOCATIONS['CONFASSISTANT_RADIUS'] == 'LOCAL') {
    if (!file_exists(ROOT . "/config/ConfAssistant.php")) {
        echo "ConfAssistant configuration file not found. You need to configure the product!";
        throw new Exception("ConfAssistant config file not found!");
    }
}

if (\config\Master::FUNCTIONALITY_LOCATIONS['DIAGNOSTICS'] == 'LOCAL') {
    if (!file_exists(ROOT . "/config/Diagnostics.php")) {
        echo "Diagnostics configuration file not found. You need to configure the product!";
        throw new Exception("Diagnostics config file not found!");
    }
}