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

use web\lib\admin\Authentication;
use web\lib\admin\view\DefaultAjaxPage;
use web\lib\admin\http\SilverbulletAjaxController;
use web\lib\admin\http\DefaultContext;

$auth = new Authentication();
$auth->authenticate();

$ajaxPage = new DefaultAjaxPage();
$context = new DefaultContext($ajaxPage);
$controller = new SilverbulletAjaxController($context);
$controller->parseRequest();

$ajaxPage->render();
