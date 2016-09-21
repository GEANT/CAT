<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
require_once(dirname(dirname(dirname(__FILE__))) . "/config/_config.php");

require_once("Federation.php");
require_once("IdP.php");
require_once("Helper.php");
require_once("CAT.php");

require_once("inc/common.inc.php");
require_once("inc/input_validation.inc.php");
require_once("../resources/inc/header.php");
require_once("../resources/inc/footer.php");
require_once("inc/option_html.inc.php");
require_once("inc/geo_widget.php");
require_once("inc/auth.inc.php");
require_once("lib/PageBuilder.php");
require_once("lib/IdpPageBuilder.php");
authenticate();

$idpPage = new IdpPageBuilder();

if(isset($_GET['inst_id'])){
    $idpPage->initiate("Editing users", PageBuilder::ADMIN_IDP_USERS);
}

$cat = $idpPage->createPagePrelude();

?>
<script src="js/option_expand.js" type="text/javascript"></script>
<script type="text/javascript" src="../external/jquery/jquery.js"></script> 
<script type="text/javascript" src="../external/jquery/jquery-migrate-1.2.1.js"></script> 
</head>
<body onload='load(1)'>
    
    <?php $idpPage->printPageHeader(); ?>
    
    <?php $idpPage->printPageFooter();

