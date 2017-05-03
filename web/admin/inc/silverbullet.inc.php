<?php
/*
 *******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 *******************************************************************************
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/config/_config.php");
require_once("common.inc.php");

use web\lib\admin\Authentication;
use web\lib\admin\view\DefaultAjaxPage;
use web\lib\admin\http\AjaxController;

$auth = new Authentication();
$auth->authenticate();

$ajaxPage = new DefaultAjaxPage();

$controller = new AjaxController($ajaxPage);
$controller->parseRequest();

$ajaxPage->render();
