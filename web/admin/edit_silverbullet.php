<?php
/* * *********************************************************************************
 * (c) 2011-15 GÉANT on behalf of the GN3, GN3plus and GN4 consortia
 * License: see the LICENSE file in the root directory
 * ********************************************************************************* */
?>
<?php
/*
 * Class autoloader invocation, should be included prior to any other code at the entry points to the application
 */
require_once("lib/autoloader.php");

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

use lib\view\InfoBlockTable;
use lib\view\InstitutionPageBuilder;
use lib\view\PageBuilder;
use lib\view\UserCredentialsForm;

authenticate();

$builder = new InstitutionPageBuilder(_("Managing institution users"), PageBuilder::ADMIN_IDP_USERS);

//Info block data preparation
$infoBlock = new InfoBlockTable( _('Current institution users'));
$infoBlock->addRow(array('John Doe', '4 active credentials', '0 expired credentials'));
$infoBlock->addRow(array('Mary Bernard', '3 active credentials', '0 expired credentials'));
$builder->addContentElement($infoBlock);

//Edit form data preparation
$editBlock = new UserCredentialsForm(_('Manage institution users'));
$editBlock->addTitleRow(array('user' => 'User', 'credentials' => 'Credentials', 'expirity' => 'Expirity', 'action' => 'Actions'));
$editBlock->addUserCertificateRow(array('user' => 'John Doe', 'credentials' => 'cert1', 'expirity' => '2017-03-21'));
$editBlock->addCertificateRow(array('credentials' => 'cert1', 'expirity' => '2017-03-21'));
$editBlock->addCertificateRow(array('credentials' => 'cert2', 'expirity' => '2017-05-23'));
$editBlock->addCertificateRow(array('credentials' => 'cert3', 'expirity' => '2018-02-12'));
$editBlock->addUserCertificateRow(array('user' => 'Mary Bernard', 'credentials' => 'cert1', 'expirity' => '2017-08-27'));
$editBlock->addCertificateRow(array('credentials' => 'cert1', 'expirity' => '2017-08-27'));
$editBlock->addCertificateRow(array('credentials' => 'cert2', 'expirity' => '2018-11-01'));
$builder->addContentElement($editBlock);


$cat = $builder->createPagePrelude();
?>
<script src="js/option_expand.js" type="text/javascript"></script>
<script type="text/javascript" src="../external/jquery/jquery.js"></script> 
<script type="text/javascript" src="../external/jquery/jquery-migrate-1.2.1.js"></script> 
</head>
<body>
    
    <?php $builder->renderPageHeader(); ?>
    
    <?php $builder->renderPageContent(); ?>
    
    <?php $builder->renderPageFooter();

