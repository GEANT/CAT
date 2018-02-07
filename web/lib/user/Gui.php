<?php

/*
 * ******************************************************************************
 * Copyright 2011-2017 DANTE Ltd. and GÉANT on behalf of the GN3, GN3+, GN4-1 
 * and GN4-2 consortia
 *
 * License: see the web/copyright.php file in the file structure
 * ******************************************************************************
 */

namespace web\lib\user;

class Gui extends \core\UserAPI {

    /**
     * various pre-translated UI texts
     * 
     * @var TextTemplates
     */
    public $textTemplates;

    public function __construct() {
        $validator = new \web\lib\common\InputValidation();
        parent::__construct();
        if (!empty($_REQUEST['idp'])) { // determine skin to use based on NROs preference
            $idp = $validator->IdP($_REQUEST['idp']);
            $fed = $validator->Federation($idp->federation);
            $fedskin = $fed->getAttributes("fed:desired_skin");
        }
        $this->skinObject = new \web\lib\user\Skinjob($_REQUEST['skin'] ?? $_SESSION['skin'] ?? $fedskin[0] ?? CONFIG['APPEARANCE']['skins'][0]);
        $this->langObject = new \core\common\Language();
        $this->textTemplates = new TextTemplates($this);
        $this->operatingSystem = $this->detectOS();
        $this->loggerInstance->debug(4, $this->operatingSystem);
    }

    public function defaultPagePrelude($pagetitle = CONFIG['APPEARANCE']['productname_long']) {
        $ourlocale = $this->langObject->getLang();
        header("Content-Type:text/html;charset=utf-8");
        echo "<!DOCTYPE html>
          <html xmlns='http://www.w3.org/1999/xhtml' lang='" . $ourlocale . "'>
          <head lang='" . $ourlocale . "'>
          <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>";
        echo "<title>" . htmlspecialchars($pagetitle) . "</title>";
        echo '<script type="text/javascript">ie_version = 0;</script>
<!--[if IE]>
<script type="text/javascript">ie_version=1;</script>
<![endif]-->
<!--[if IE 7]>
<script type="text/javascript">ie_version=7;</script>
<![endif]-->
<!--[if IE 8]>
<script type="text/javascript">ie_version=8;</script>
<![endif]-->
<!--[if IE 9]>
<script type="text/javascript">ie_version=9;</script>
<![endif]-->
<!--[if IE 10]>
<script type="text/javascript">ie_version=10;</script>
<![endif]-->
';
    }

    public $loggerInstance;
    public $skinObject;
    public $langObject;
    public $operatingSystem;

}
