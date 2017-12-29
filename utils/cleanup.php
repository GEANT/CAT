<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

// called by cleanup.sh (which in turn should run via cron every hour)

/**
 * This script deletes obsolete directories from installer cache and siverbullet directory
 */
require_once(dirname(dirname(__FILE__)) . "/config/_config.php");

web\lib\admin\Maintenance::deleteObsoleteTempDirs();
