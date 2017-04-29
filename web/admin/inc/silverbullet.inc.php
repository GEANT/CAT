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
use web\lib\admin\view\AjaxPage;
use web\lib\admin\view\InstitutionPageBuilder;

$auth = new Authentication();
$auth->authenticate();

$ajaxPage = new AjaxPage();

$builder = new InstitutionPageBuilder($page, PageBuilder::ADMIN_IDP_USERS);

if($builder->isReady()){
    
}

$builder->renderPageContent();

