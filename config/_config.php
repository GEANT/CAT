<?php
/* 
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

require_once ("autoloader.php");
require_once(__DIR__."/../packageRoot.php");
include(ROOT."/config/config-master.php");

/* load sub-configs if we are dealing with those in this installation */

if (CONFIG['FUNCTIONALITY_LOCATIONS']['CONFASSISTANT'] == 'LOCAL') {
    include(ROOT."/config/config-confassistant.php");
}

if (CONFIG['FUNCTIONALITY_LOCATIONS']['DIAGNOSTICS'] == 'LOCAL') {
    include(ROOT."/config/config-diagnostics.php");
}

