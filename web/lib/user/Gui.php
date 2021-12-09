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

namespace web\lib\user;

class Gui extends \core\UserAPI {

    /**
     * various pre-translated UI texts
     * 
     * @var TextTemplates
     */
    public $textTemplates;

    /**
     * constructs a new Gui object
     */
    public function __construct() {
        $validator = new \web\lib\common\InputValidation();
        parent::__construct();
        if (!empty($_REQUEST['idp'])) { // determine skin to use based on NROs preference
            $idp = $validator->existingIdP($_REQUEST['idp']);
            $fed = $validator->existingFederation($idp->federation);
            $fedskin = $fed->getAttributes("fed:desired_skin");
        }
        $this->skinObject = new \web\lib\user\Skinjob($_REQUEST['skin'] ?? $_SESSION['skin'] ?? $fedskin[0] ?? \config\Master::APPEARANCE['skins'][0]);
        $this->languageInstance->setTextDomain("web_user");
        $this->textTemplates = new TextTemplates();
        $this->operatingSystem = $this->detectOS();
        $this->loggerInstance->debug(4, $this->operatingSystem);
    }

    /**
     * header which is needed by most front-end files
     * 
     * @param string $pagetitle content for the <title> element
     * @return void writes directly to output
     */
    public function defaultPagePrelude($pagetitle = \config\Master::APPEARANCE['productname_long']) {
        $ourlocale = $this->languageInstance->getLang();
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

    /**
     * outputs a string, replacing unsafe JavaScript quotation marks with HTML
     * entity
     * 
     * @param string $s the string to escape
     * @return void
     */
    public function javaScriptEscapedEcho($s) {
        $out = preg_replace('/"/', '&quot;', $s);
        $out = preg_replace('/\\n/', ' ', $out);
        echo $out;
    }

    /**
     * the instance of the skin factory to use
     * 
     * @var \web\lib\user\Skinjob
     */
    public $skinObject;

    /**
     * the detected operating system
     * 
     * @var array|boolean
     */
    public $operatingSystem;

    /**
     * redeclaring as public so that web front-end can access it
     * 
     * @var \core\common\Logging
     */
    public $loggerInstance;

}
